<?php

namespace App\Services;

use App\Models\CashRegister;
use App\Models\Kitchen\KitchenInventoryOperation;
use App\Models\Kitchen\KitchenOrderConsumption;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class DeliveryService
{
    private const EPSILON = 0.0001;

    public function __construct(
        protected \App\Services\SalesService $salesService,
        protected KitchenStockControl $kitchenStockControl,
        protected KitchenInventoryService $kitchenInventoryService
    ) {}

    public function ensureCashRegisterIsOpen(): void
    {
        $cashRegister = CashRegister::current();

        if (!$cashRegister) {
            throw ValidationException::withMessages([
                'general' => ['Debes abrir tu turno de caja antes de crear o cobrar un delivery.'],
            ]);
        }
    }

    public function getCashRegisterId(): ?int
    {
        return CashRegister::current()?->id;
    }

    public function restoreInventoryForOrder(Order $order): void
    {
        $this->salesService->restoreInventoryForOrder($order);
    }

    /**
     * Consumo de cocina al crear un delivery (mirror de la costura de PosCheckoutService):
     * sólo con kitchen_stock_control activado, con la clave idempotente sale:order:{id} y
     * controlStock=true. Debe invocarse dentro de la transacción que crea la orden y descuenta
     * el inventario comercial para que un fallo de cocina revierta todo (SAVEPOINT anidado).
     */
    public function consumeKitchenForOrder(Order $order): void
    {
        if (!$this->kitchenStockControl->enabled()) {
            return;
        }

        $lines = $this->kitchenStockControl->consumptionLinesFor($order);
        if ($lines === []) {
            return;
        }

        $this->kitchenInventoryService->recordSale(
            'sale:order:' . $order->id,
            $lines,
            true,
            Auth::id()
        );
    }

    /**
     * Devuelve el inventario de cocina realmente consumido y aún no devuelto por una orden.
     * Cancelación comercial: tipo 'cancellation' con clave idempotente cancel:order:{id}.
     * Si nunca hubo consumo registrado (flag desactivado al crear, o productos sin receta)
     * no se emite ningún movimiento de cocina.
     */
    public function returnKitchenForOrderIfConsumed(Order $order): void
    {
        $saleOperation = KitchenInventoryOperation::query()
            ->where('event_key', 'sale:order:' . $order->id)
            ->where('operation_type', 'sale')
            ->where('status', 'confirmed')
            ->first();

        if ($saleOperation === null) {
            return;
        }

        $lines = KitchenOrderConsumption::query()
            ->where('operation_id', $saleOperation->id)
            ->get()
            ->map(fn (KitchenOrderConsumption $consumption): array => [
                'consumption_id' => $consumption->id,
                'quantity' => (float) $consumption->covered_quantity - (float) $consumption->returned_quantity,
            ])
            ->filter(fn (array $line): bool => $line['quantity'] > self::EPSILON)
            ->values()
            ->all();

        if ($lines === []) {
            return;
        }

        $this->kitchenInventoryService->recordReturn(
            'cancel:order:' . $order->id,
            $lines,
            'cancellation',
            Auth::id()
        );
    }
}
