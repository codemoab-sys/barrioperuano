<?php

namespace App\Services;

use App\Exceptions\KitchenIdempotencyConflict;
use App\Models\Kitchen\KitchenInventoryMovement;
use App\Models\Kitchen\KitchenInventoryOperation;
use App\Models\Kitchen\KitchenOrderConsumption;
use App\Models\Kitchen\KitchenRecipeItem;
use App\Models\Kitchen\KitchenRecipeVersion;
use App\Models\Kitchen\KitchenShortageRegularization;
use App\Models\Kitchen\KitchenSupply;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KitchenInventoryService
{
    private const EPSILON = 0.0001;

    private const TRANSACTION_ATTEMPTS = 3;

    public function recordSale(string $eventKey, array $lines, bool $controlStock = false, ?int $userId = null): KitchenInventoryOperation
    {
        $normalized = $this->normalizeSaleLines($lines);
        $payload = [
            'control_stock' => $controlStock,
            'lines' => array_map(fn (array $line): array => $this->fingerprintLine($line), $normalized),
        ];

        return DB::transaction(function () use ($eventKey, $payload, $normalized, $controlStock, $userId): KitchenInventoryOperation {
            $operation = $this->operation($eventKey, 'sale', $payload, $userId);
            if ($operation->wasRecentlyCreated === false) {
                return $operation->load(['movements', 'consumptions']);
            }

            $this->resolveRecipeQuantities($normalized);

            $supplyIds = collect($normalized)->pluck('supply_id')->unique()->sort()->values();
            $supplies = KitchenSupply::query()->whereKey($supplyIds)->lockForUpdate()->get()->keyBy('id');

            if ($supplies->count() !== $supplyIds->count()) {
                throw ValidationException::withMessages(['supply_id' => 'Uno de los insumos no existe.']);
            }

            $demandBySupply = [];
            foreach ($normalized as $line) {
                $demandBySupply[$line['supply_id']] = ($demandBySupply[$line['supply_id']] ?? 0) + $line['theoretical_quantity'];
            }

            foreach ($demandBySupply as $supplyId => $demand) {
                $available = (float) $supplies[$supplyId]->stock_current;
                if ($controlStock && $available < $demand) {
                    throw ValidationException::withMessages([
                        'stock' => "Stock insuficiente para {$supplies[$supplyId]->name}. Faltan ".($demand - $available).'.',
                    ]);
                }
            }

            $remainingBySupply = $supplies->mapWithKeys(fn (KitchenSupply $supply): array => [$supply->id => (float) $supply->stock_current])->all();
            $prepared = [];
            foreach ($normalized as $line) {
                $theoretical = $line['theoretical_quantity'];
                $covered = $controlStock
                    ? $theoretical
                    : min($theoretical, $remainingBySupply[$line['supply_id']]);
                $remainingBySupply[$line['supply_id']] -= $covered;
                $shortage = $theoretical - $covered;
                $prepared[] = compact('line', 'theoretical', 'covered', 'shortage');
            }

            foreach ($prepared as $item) {
                $line = $item['line'];
                $supply = $supplies[$line['supply_id']];

                KitchenOrderConsumption::create([
                    'operation_id' => $operation->id,
                    'order_id' => $line['order_id'],
                    'order_detail_id' => $line['order_detail_id'],
                    'product_id' => $line['product_id'],
                    'recipe_version_id' => $line['recipe_version_id'],
                    'kitchen_supply_id' => $supply->id,
                    'sold_quantity' => $line['sold_quantity'],
                    'recipe_quantity' => $line['recipe_quantity'],
                    'theoretical_quantity' => $item['theoretical'],
                    'consumed_quantity' => $item['theoretical'],
                    'covered_quantity' => $item['covered'],
                    'shortage_quantity' => $item['shortage'],
                    'returned_quantity' => 0,
                    'idempotency_key' => $eventKey.':consumption:'.$supply->id.':'.$line['line_key'],
                ]);
            }

            foreach ($demandBySupply as $supplyId => $demand) {
                $supply = $supplies[$supplyId];
                $before = (float) $supply->stock_current;
                $covered = $controlStock ? $demand : $before - $remainingBySupply[$supplyId];
                $shortage = $demand - $covered;
                $after = $before - $covered;
                $this->assertNonNegative($after);

                if ($covered <= 0) {
                    continue;
                }

                $supply->update(['stock_current' => $after]);

                KitchenInventoryMovement::create([
                    'operation_id' => $operation->id,
                    'kitchen_supply_id' => $supply->id,
                    'type' => 'sale',
                    'theoretical_quantity' => $demand,
                    'quantity' => $covered,
                    'covered_quantity' => $covered,
                    'shortage_quantity' => $shortage,
                    'stock_before' => $before,
                    'stock_after' => $after,
                    'idempotency_key' => $eventKey.':movement:'.$supplyId,
                    'user_id' => $userId,
                ]);
            }

            return $operation->load(['movements', 'consumptions']);
        }, self::TRANSACTION_ATTEMPTS);
    }

    public function recordReturn(string $eventKey, array $lines, string $type = 'return', ?int $userId = null): KitchenInventoryOperation
    {
        if (! in_array($type, ['return', 'cancellation'], true)) {
            throw ValidationException::withMessages(['type' => 'Tipo de reversión inválido.']);
        }

        $normalized = $this->normalizeReturnLines($lines);
        $payload = ['lines' => $normalized, 'type' => $type];

        return DB::transaction(function () use ($eventKey, $payload, $normalized, $type, $userId): KitchenInventoryOperation {
            $operation = $this->operation($eventKey, $type, $payload, $userId);
            if ($operation->wasRecentlyCreated === false) {
                return $operation->load(['movements']);
            }

            $consumptionIds = collect($normalized)->pluck('consumption_id')->unique()->sort()->values();
            $consumptions = KitchenOrderConsumption::query()
                ->whereKey($consumptionIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($consumptions->count() !== $consumptionIds->count()) {
                throw ValidationException::withMessages(['consumption_id' => 'Uno de los consumos referenciados no existe.']);
            }

            $operationIds = $consumptions->pluck('operation_id')->unique()->sort()->values();
            $operations = KitchenInventoryOperation::query()->whereKey($operationIds)->get()->keyBy('id');

            foreach ($consumptions as $consumption) {
                $original = $operations->get($consumption->operation_id);
                if ($original === null || $original->operation_type !== 'sale' || $original->status !== 'confirmed') {
                    throw ValidationException::withMessages([
                        'consumption_id' => 'La reversión debe referenciar un consumo de una operación de venta confirmada.',
                    ]);
                }
            }

            $supplyIds = $consumptions->pluck('kitchen_supply_id')->unique()->sort()->values();
            $supplies = KitchenSupply::query()->whereKey($supplyIds)->lockForUpdate()->get()->keyBy('id');

            foreach ($normalized as $line) {
                $consumption = $consumptions[$line['consumption_id']];
                $recoverable = (float) $consumption->covered_quantity - (float) $consumption->returned_quantity;
                if ($line['quantity'] > $recoverable + self::EPSILON) {
                    throw ValidationException::withMessages([
                        'quantity' => 'La devolución supera la cantidad físicamente cubierta.',
                    ]);
                }
            }

            foreach ($normalized as $line) {
                $consumption = $consumptions[$line['consumption_id']];
                $supply = $supplies[$consumption->kitchen_supply_id];
                $before = (float) $supply->stock_current;
                $after = $before + $line['quantity'];
                $supply->update(['stock_current' => $after]);
                $consumption->update(['returned_quantity' => (float) $consumption->returned_quantity + $line['quantity']]);

                KitchenInventoryMovement::create([
                    'operation_id' => $operation->id,
                    'kitchen_supply_id' => $supply->id,
                    'type' => $type,
                    'quantity' => $line['quantity'],
                    'covered_quantity' => $line['quantity'],
                    'stock_before' => $before,
                    'stock_after' => $after,
                    'source_type' => 'kitchen_order_consumption',
                    'source_id' => $consumption->id,
                    'idempotency_key' => $eventKey.':movement:'.$consumption->id,
                    'user_id' => $userId,
                ]);
            }

            return $operation->load(['movements']);
        }, self::TRANSACTION_ATTEMPTS);
    }

    public function regularizeShortage(string $regularizationKey, array $lines, ?int $userId = null): KitchenInventoryOperation
    {
        $normalized = $this->normalizeRegularizationLines($lines);
        $payload = ['lines' => $normalized];

        return DB::transaction(function () use ($regularizationKey, $payload, $normalized, $userId): KitchenInventoryOperation {
            $operation = $this->operation($regularizationKey, 'shortage_regularization', $payload, $userId);
            if ($operation->wasRecentlyCreated === false) {
                return $operation->load(['regularizations']);
            }

            $consumptionIds = collect($normalized)->pluck('consumption_id')->unique()->sort()->values();
            $consumptions = KitchenOrderConsumption::query()
                ->whereKey($consumptionIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($consumptions->count() !== $consumptionIds->count()) {
                throw ValidationException::withMessages(['consumption_id' => 'Uno de los consumos referenciados no existe.']);
            }

            foreach ($normalized as $line) {
                $consumption = $consumptions[$line['consumption_id']];
                $regularized = (float) KitchenShortageRegularization::query()
                    ->where('kitchen_order_consumption_id', $consumption->id)
                    ->sum('quantity');
                $pending = (float) $consumption->shortage_quantity - $regularized;

                if ($line['quantity'] > $pending + self::EPSILON) {
                    throw ValidationException::withMessages([
                        'quantity' => 'La regularización supera el faltante pendiente.',
                    ]);
                }
            }

            foreach ($normalized as $line) {
                $consumption = $consumptions[$line['consumption_id']];

                KitchenShortageRegularization::create([
                    'operation_id' => $operation->id,
                    'kitchen_order_consumption_id' => $consumption->id,
                    'kitchen_supply_id' => $consumption->kitchen_supply_id,
                    'user_id' => $userId,
                    'quantity' => $line['quantity'],
                    'regularization_key' => $regularizationKey,
                    'note' => $line['note'],
                ]);
            }

            return $operation->load(['regularizations']);
        }, self::TRANSACTION_ATTEMPTS);
    }

    public function adjustStock(string $eventKey, string $type, int $supplyId, float $quantity, ?int $userId = null): KitchenInventoryOperation
    {
        if (! in_array($type, ['entry', 'adjustment_in', 'adjustment_out', 'waste'], true) || ! is_finite($quantity) || $quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'El movimiento o la cantidad no son válidos.']);
        }

        $payload = ['quantity' => $quantity, 'supplyId' => $supplyId, 'type' => $type];

        return DB::transaction(function () use ($eventKey, $type, $supplyId, $quantity, $userId, $payload): KitchenInventoryOperation {
            $operation = $this->operation($eventKey, $type, $payload, $userId);
            if ($operation->wasRecentlyCreated === false) {
                return $operation->load(['movements']);
            }

            $supply = KitchenSupply::query()->lockForUpdate()->findOrFail($supplyId);
            $adds = in_array($type, ['entry', 'adjustment_in'], true);
            $before = (float) $supply->stock_current;
            $after = $adds ? $before + $quantity : $before - $quantity;
            if (! $adds && $quantity > $before + self::EPSILON) {
                throw ValidationException::withMessages(['quantity' => 'El movimiento supera el stock físico.']);
            }

            $supply->update(['stock_current' => $after]);
            KitchenInventoryMovement::create([
                'operation_id' => $operation->id,
                'kitchen_supply_id' => $supply->id,
                'type' => $type,
                'quantity' => $quantity,
                'stock_before' => $before,
                'stock_after' => $after,
                'idempotency_key' => $eventKey.':movement:'.$supplyId,
                'user_id' => $userId,
            ]);

            return $operation->load(['movements']);
        }, self::TRANSACTION_ATTEMPTS);
    }

    private function operation(string $eventKey, string $type, array $payload, ?int $userId): KitchenInventoryOperation
    {
        $hash = hash('sha256', json_encode($this->canonicalize($payload), JSON_THROW_ON_ERROR));

        $operation = KitchenInventoryOperation::query()->where('event_key', $eventKey)->lockForUpdate()->first();
        if ($operation !== null) {
            return $this->assertMatchingOperation($operation, $hash, $type);
        }

        try {
            $operation = KitchenInventoryOperation::create([
                'event_key' => $eventKey,
                'operation_type' => $type,
                'request_hash' => $hash,
                'status' => 'confirmed',
                'user_id' => $userId,
            ]);
        } catch (QueryException $exception) {
            if (! $this->isDuplicateKeyViolation($exception)) {
                throw $exception;
            }

            $operation = KitchenInventoryOperation::query()->where('event_key', $eventKey)->lockForUpdate()->first();
            if ($operation === null) {
                throw $exception;
            }

            return $this->assertMatchingOperation($operation, $hash, $type);
        }

        $operation->wasRecentlyCreated = true;

        return $operation;
    }

    private function assertMatchingOperation(KitchenInventoryOperation $operation, string $hash, string $type): KitchenInventoryOperation
    {
        if ($operation->request_hash !== $hash || $operation->operation_type !== $type) {
            throw new KitchenIdempotencyConflict('La clave idempotente ya fue utilizada con datos diferentes.');
        }

        return $operation;
    }

    private function isDuplicateKeyViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        return in_array($sqlState, ['23000', '23505'], true) || $driverCode === 1062 || $driverCode === 19;
    }

    private function normalizeSaleLines(array $lines): array
    {
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => 'Debe registrar al menos una línea de consumo.']);
        }

        $normalized = [];
        $identities = [];
        foreach (array_values($lines) as $index => $line) {
            if (! is_array($line)) {
                throw ValidationException::withMessages(['lines' => 'Cada línea de consumo debe ser un arreglo.']);
            }

            $soldQuantity = $this->positive($line['sold_quantity'] ?? 0, 'sold_quantity');
            $supplyId = (int) ($line['supply_id'] ?? 0);
            $recipeVersionId = (int) ($line['recipe_version_id'] ?? 0);

            if ($supplyId <= 0) {
                throw ValidationException::withMessages(['supply_id' => 'El insumo es obligatorio.']);
            }
            if ($recipeVersionId <= 0) {
                throw ValidationException::withMessages(['recipe_version_id' => 'La versión de receta es obligatoria.']);
            }

            $orderDetailId = isset($line['order_detail_id']) && $line['order_detail_id'] !== null ? (int) $line['order_detail_id'] : null;
            if ($orderDetailId !== null) {
                $identity = $supplyId.':'.$orderDetailId;
                if (isset($identities[$identity])) {
                    throw ValidationException::withMessages([
                        'lines' => 'Línea duplicada: el mismo insumo y detalle de pedido no pueden repetirse en una solicitud.',
                    ]);
                }
                $identities[$identity] = true;
            }

            $normalized[] = [
                'supply_id' => $supplyId,
                'recipe_version_id' => $recipeVersionId,
                'sold_quantity' => $soldQuantity,
                'caller_recipe_quantity' => isset($line['recipe_quantity']) ? (float) $line['recipe_quantity'] : null,
                'caller_theoretical_quantity' => isset($line['theoretical_quantity']) ? (float) $line['theoretical_quantity'] : null,
                'order_id' => isset($line['order_id']) && $line['order_id'] !== null ? (int) $line['order_id'] : null,
                'order_detail_id' => $orderDetailId,
                'product_id' => isset($line['product_id']) && $line['product_id'] !== null ? (int) $line['product_id'] : null,
            ];
        }

        usort($normalized, fn (array $a, array $b): int => json_encode($this->fingerprintLine($a), JSON_THROW_ON_ERROR) <=> json_encode($this->fingerprintLine($b), JSON_THROW_ON_ERROR));

        foreach ($normalized as $index => &$line) {
            $line['line_key'] = (string) ($line['order_detail_id'] ?? 'pos'.$index);
        }
        unset($line);

        return $normalized;
    }

    private function normalizeReturnLines(array $lines): array
    {
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => 'Debe registrar al menos una línea de devolución.']);
        }

        $merged = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                throw ValidationException::withMessages(['lines' => 'Cada línea de devolución debe ser un arreglo.']);
            }

            $consumptionId = (int) ($line['consumption_id'] ?? 0);
            if ($consumptionId <= 0) {
                throw ValidationException::withMessages(['consumption_id' => 'El consumo referenciado es obligatorio.']);
            }

            $quantity = $this->positive($line['quantity'] ?? 0, 'quantity');
            $merged[$consumptionId] = ($merged[$consumptionId] ?? 0) + $quantity;
        }

        ksort($merged);

        return array_map(
            fn (int $consumptionId, float $quantity): array => ['consumption_id' => $consumptionId, 'quantity' => $quantity],
            array_keys($merged),
            array_values($merged)
        );
    }

    private function normalizeRegularizationLines(array $lines): array
    {
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => 'Debe registrar al menos una línea de regularización.']);
        }

        $normalized = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                throw ValidationException::withMessages(['lines' => 'Cada línea de regularización debe ser un arreglo.']);
            }

            $consumptionId = (int) ($line['consumption_id'] ?? 0);
            if ($consumptionId <= 0) {
                throw ValidationException::withMessages(['consumption_id' => 'El consumo referenciado es obligatorio.']);
            }
            if (isset($normalized[$consumptionId])) {
                throw ValidationException::withMessages([
                    'consumption_id' => 'Línea duplicada: el mismo consumo no puede regularizarse dos veces en una solicitud.',
                ]);
            }

            $normalized[$consumptionId] = [
                'consumption_id' => $consumptionId,
                'quantity' => $this->positive($line['quantity'] ?? 0, 'quantity'),
                'note' => $line['note'] ?? null,
            ];
        }

        ksort($normalized);

        return array_values($normalized);
    }

    private function resolveRecipeQuantities(array &$lines): void
    {
        $versionIds = collect($lines)->pluck('recipe_version_id')->unique()->sort()->values();
        $versions = KitchenRecipeVersion::query()->whereKey($versionIds)->get()->keyBy('id');
        $itemsByVersion = KitchenRecipeItem::query()
            ->whereIn('recipe_version_id', $versionIds)
            ->get()
            ->groupBy('recipe_version_id');

        foreach ($lines as &$line) {
            if (! isset($versions[$line['recipe_version_id']])) {
                throw ValidationException::withMessages(['recipe_version_id' => 'La versión de receta indicada no existe.']);
            }

            $item = $itemsByVersion->get($line['recipe_version_id'])?->firstWhere('kitchen_supply_id', $line['supply_id']);
            if ($item === null) {
                throw ValidationException::withMessages(['supply_id' => 'El insumo no pertenece a la versión de receta indicada.']);
            }

            $recipeQuantity = (float) $item->quantity;
            if (! is_finite($recipeQuantity) || $recipeQuantity <= 0) {
                throw ValidationException::withMessages(['recipe_quantity' => 'La cantidad de receta almacenada no es válida.']);
            }
            if ($line['caller_recipe_quantity'] !== null && abs($line['caller_recipe_quantity'] - $recipeQuantity) > self::EPSILON) {
                throw ValidationException::withMessages(['recipe_quantity' => 'La cantidad de receta informada no coincide con la versión almacenada.']);
            }

            $theoretical = $line['sold_quantity'] * $recipeQuantity;
            if ($line['caller_theoretical_quantity'] !== null && abs($line['caller_theoretical_quantity'] - $theoretical) > self::EPSILON) {
                throw ValidationException::withMessages(['theoretical_quantity' => 'El consumo teórico informado no coincide con la receta almacenada.']);
            }

            $line['recipe_quantity'] = $recipeQuantity;
            $line['theoretical_quantity'] = $theoretical;
        }
        unset($line);
    }

    private function fingerprintLine(array $line): array
    {
        return [
            'order_detail_id' => $line['order_detail_id'],
            'order_id' => $line['order_id'],
            'product_id' => $line['product_id'],
            'recipe_version_id' => $line['recipe_version_id'],
            'sold_quantity' => $this->canonicalNumber($line['sold_quantity']),
            'supply_id' => $line['supply_id'],
        ];
    }

    private function canonicalize(mixed $value): mixed
    {
        if (is_array($value)) {
            $canonical = [];
            foreach ($value as $key => $item) {
                $canonical[$key] = $this->canonicalize($item);
            }

            if ($canonical !== [] && array_keys($canonical) === range(0, count($canonical) - 1)) {
                usort($canonical, fn (mixed $a, mixed $b): int => json_encode($a, JSON_THROW_ON_ERROR) <=> json_encode($b, JSON_THROW_ON_ERROR));

                return $canonical;
            }

            ksort($canonical);

            return $canonical;
        }

        if (is_int($value) || is_float($value)) {
            return $this->canonicalNumber((float) $value);
        }

        if (is_string($value) && is_numeric($value)) {
            return $this->canonicalNumber((float) $value);
        }

        return $value;
    }

    private function canonicalNumber(float $value): string
    {
        if (! is_finite($value)) {
            throw ValidationException::withMessages(['quantity' => 'La cantidad no es un número válido.']);
        }

        $trimmed = rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');

        return $trimmed === '' || $trimmed === '-0' ? '0' : $trimmed;
    }

    private function positive(float|int|string $value, string $field): float
    {
        $number = (float) $value;
        if ($number <= 0 || ! is_finite($number)) {
            throw ValidationException::withMessages([$field => 'La cantidad debe ser positiva.']);
        }

        return $number;
    }

    private function assertNonNegative(float $value): void
    {
        if ($value < 0) {
            throw ValidationException::withMessages(['stock' => 'El stock físico no puede ser negativo.']);
        }
    }
}
