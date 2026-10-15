<?php

namespace App\Services;

use App\Models\Kitchen\KitchenRecipe;
use App\Models\Kitchen\KitchenRecipeVersion;
use App\Models\Kitchen\KitchenSupply;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;

class KitchenStockControl
{
    public const SETTING_KEY = 'kitchen_stock_control';

    private const EPSILON = 0.0001;

    public function enabled(): bool
    {
        $value = Setting::query()->where('key', self::SETTING_KEY)->value('value');

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes'], true);
    }

    public function consumptionLinesFor(Order $order): array
    {
        $lines = [];

        foreach ($order->details as $detail) {
            if (! $detail->product_id || (float) $detail->quantity <= 0) {
                continue;
            }

            $version = $this->publishedVersionFor((int) $detail->product_id);
            if ($version === null) {
                continue;
            }

            foreach ($version->items as $item) {
                $lines[] = [
                    'order_id' => $order->id,
                    'order_detail_id' => $detail->id,
                    'product_id' => $detail->product_id,
                    'recipe_version_id' => $version->id,
                    'supply_id' => $item->kitchen_supply_id,
                    'sold_quantity' => (float) $detail->quantity,
                ];
            }
        }

        return $lines;
    }

    public function availabilityError(Product $product, int $prospectiveQuantity, ?int $currentOrderId): ?string
    {
        $version = $this->publishedVersionFor((int) $product->id);
        if ($version === null || $version->items->isEmpty()) {
            return null;
        }

        $currentQuantity = 0;
        if ($currentOrderId !== null) {
            $currentOrder = Order::query()->find($currentOrderId);
            if ($currentOrder !== null && $currentOrder->status === 'pending') {
                $currentQuantity = (int) $currentOrder->details()
                    ->where('product_id', $product->id)
                    ->sum('quantity');
            }
        }

        $reservedBySupply = $this->pendingTableDemandBySupply();

        $supplies = KitchenSupply::query()
            ->whereKey($version->items->pluck('kitchen_supply_id')->unique()->values())
            ->get()
            ->keyBy('id');

        foreach ($version->items as $item) {
            $supply = $supplies->get((int) $item->kitchen_supply_id);
            if ($supply === null) {
                continue;
            }

            $perUnit = (float) $item->quantity;
            $reserved = (float) ($reservedBySupply[$supply->id] ?? 0);
            $available = (float) $supply->stock_current - $reserved + $perUnit * $currentQuantity;
            $required = $perUnit * $prospectiveQuantity;

            if ($available + self::EPSILON < $required) {
                $missing = $required - $available;

                return 'Stock insuficiente para cocina: '.$supply->name.'. Faltan '.number_format($missing, 4, '.', '').'.';
            }
        }

        return null;
    }

    private function publishedVersionFor(int $productId): ?KitchenRecipeVersion
    {
        return $this->publishedVersionsFor([$productId])[$productId] ?? null;
    }

    private function publishedVersionsFor(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $recipes = KitchenRecipe::query()
            ->whereIn('product_id', $productIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        if ($recipes->isEmpty()) {
            return [];
        }

        $latestByRecipe = KitchenRecipeVersion::query()
            ->whereIn('recipe_id', $recipes->keys())
            ->where('status', 'published')
            ->with('items')
            ->get()
            ->groupBy('recipe_id')
            ->map(fn ($versions) => $versions
                ->sortByDesc(fn (KitchenRecipeVersion $version): array => [(int) $version->version, (int) $version->id])
                ->first());

        $byProduct = [];
        foreach ($recipes as $recipe) {
            $version = $latestByRecipe->get($recipe->id);
            if ($version !== null) {
                $byProduct[(int) $recipe->product_id] = $version;
            }
        }

        return $byProduct;
    }

    private function pendingTableDemandBySupply(): array
    {
        $orders = Order::query()
            ->where('status', 'pending')
            ->whereNotNull('table_id')
            ->with('details')
            ->get();

        $productIds = $orders->flatMap(fn (Order $order) => $order->details)
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $versions = $this->publishedVersionsFor($productIds);

        $demand = [];
        foreach ($orders as $order) {
            foreach ($order->details as $detail) {
                $version = $versions[(int) $detail->product_id] ?? null;
                if ($version === null) {
                    continue;
                }

                foreach ($version->items as $item) {
                    $supplyId = (int) $item->kitchen_supply_id;
                    $demand[$supplyId] = ($demand[$supplyId] ?? 0)
                        + (float) $item->quantity * (float) $detail->quantity;
                }
            }
        }

        return $demand;
    }
}
