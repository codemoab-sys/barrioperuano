<?php

namespace App\Services;

use App\Models\InventoryLog;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;

class SalesService
{
    public function validateOrderForStock(Order $order): void
    {
        $order->loadMissing('details.product.ingredients');

        foreach ($order->details as $detail) {
            $product = $detail->product;

            if (!$product) {
                continue;
            }

            if ($product->ingredients->isNotEmpty()) {
                foreach ($product->ingredients as $ingredient) {
                    $requiredQuantity = (float) $ingredient->pivot->quantity * $detail->quantity;

                    if (!is_null($ingredient->stock) && $ingredient->stock < $requiredQuantity) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'stock' => 'Stock insuficiente para ' . $ingredient->name . '.',
                        ]);
                    }
                }

                continue;
            }

            if (!is_null($product->stock) && $product->stock < $detail->quantity) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'stock' => 'Stock insuficiente para ' . $product->name . '.',
                ]);
            }
        }
    }

    public function applyStockReductionForOrder(Order $order, string $context = 'sale'): void
    {
        $order->loadMissing('details.product.ingredients');

        foreach ($order->details as $detail) {
            $product = $detail->product;

            if (!$product) {
                continue;
            }

            $ingredients = $product->ingredients;

            if ($ingredients->isNotEmpty()) {
                foreach ($ingredients as $ingredient) {
                    $requiredQuantity = (float) $ingredient->pivot->quantity * $detail->quantity;

                    $this->reduceStockForItem($ingredient, $requiredQuantity, $order, $context);
                }

                continue;
            }

            $this->reduceStockForItem($product, (float) $detail->quantity, $order, $context);
        }
    }

    public function restoreInventoryForOrder(Order $order): void
    {
        $order->loadMissing('details.product.ingredients');

        foreach ($order->details as $detail) {
            $product = $detail->product;

            if (!$product) {
                continue;
            }

            if ($product->ingredients->count() > 0) {
                foreach ($product->ingredients as $ingredient) {
                    $qty = (float) $ingredient->pivot->quantity * $detail->quantity;

                    if (!is_null($ingredient->stock)) {
                        $ingredient->increment('stock', $qty);
                    }
                }

                continue;
            }

            if (!is_null($product->stock)) {
                $product->increment('stock', $detail->quantity);
            }
        }
    }

    protected function reduceStockForItem(Product $product, float $requiredQuantity, Order $order, string $context): void
    {
        $lockedProduct = Product::query()
            ->lockForUpdate()
            ->findOrFail($product->id);

        if (is_null($lockedProduct->stock)) {
            return;
        }

        if ((float) $lockedProduct->stock < $requiredQuantity) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'stock' => 'Stock insuficiente para ' . $lockedProduct->name . '.',
            ]);
        }

        $oldStock = (float) $lockedProduct->stock;
        $lockedProduct->decrement('stock', $requiredQuantity);

        InventoryLog::create([
            'product_id' => $lockedProduct->id,
            'user_id' => Auth::id() ?? $order->user_id,
            'type' => 'sale',
            'quantity' => -$requiredQuantity,
            'old_stock' => $oldStock,
            'new_stock' => $oldStock - $requiredQuantity,
            'note' => $context . ' #' . $order->id,
        ]);

        if ((float) $lockedProduct->fresh()->stock <= 0) {
            $lockedProduct->update(['is_saleable' => false]);
        }
    }
}
