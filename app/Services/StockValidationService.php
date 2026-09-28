<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

class StockValidationService
{
    /**
     * Valida stock del producto principal e ingredientes para una cantidad pedida.
     */
    public function validateForQuantity(Product $product, int $quantity): void
    {
        if (!is_null($product->stock) && $product->stock < $quantity) {
            throw ValidationException::withMessages([
                'products' => ['Stock insuficiente para ' . $product->name . '.'],
            ]);
        }

        foreach ($product->ingredients as $ingredient) {
            if (is_null($ingredient->stock)) {
                continue;
            }

            $required = (float) $ingredient->pivot->quantity * $quantity;
            if ($ingredient->stock < $required) {
                throw ValidationException::withMessages([
                    'products' => ['Stock insuficiente para el ingrediente: ' . $ingredient->name],
                ]);
            }
        }
    }

    /**
     * Valida un array de productos con cantidades para checkout de venta/delivery.
     */
    public function validateItems(array $items): void
    {
        $quantitiesByProduct = [];

        foreach ($items as $item) {
            $productId = (int) ($item['id'] ?? 0);
            $quantity = (int) ($item['qty'] ?? 0);

            if ($productId < 1 || $quantity < 1) {
                continue;
            }

            $quantitiesByProduct[$productId] = ($quantitiesByProduct[$productId] ?? 0) + $quantity;
        }

        foreach ($quantitiesByProduct as $productId => $quantity) {
            $product = Product::with('ingredients')->find($productId);
            if (!$product) {
                continue;
            }

            $this->validateForQuantity($product, $quantity);
        }
    }
}
