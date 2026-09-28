<?php

namespace App\Services;

use App\Models\Product;

class WingSauceMenu
{
    public static function groups(): array
    {
        return [
            'Salsas dulces' => ['BBQ', 'Maracuyá', 'Honey Mustard'],
            'Salsas sin picante' => ['Original', 'Broaster', 'Parrillera'],
            'Salsas picantes' => ['Hot BBQ', 'Arrocatadas', 'Acevichadas'],
            'Salsas saladas' => ['Olivo', 'Salteadas', 'Tocino Crunch', 'Ajo Parmesano', 'Ajo'],
        ];
    }

    public static function sauces(): array
    {
        return array_merge(...array_values(self::groups()));
    }

    public static function flavorLimit(Product $product): ?int
    {
        return $product->required_sauce_count;
    }
}