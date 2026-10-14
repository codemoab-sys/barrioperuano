<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BarrioPeruanoMenuSeeder extends Seeder
{
    public function run(): void
    {
        $menu = [
            'Alitas' => [
                ['El Al Toque (6 alas, 1 sabor)', 18],
                ['El Monse (10 alas, 2 sabores)', 29],
                ['El Firme (20 alas, 4 sabores)', 49],
                ['El Bravazo (30 alas, 5 sabores)', 69],
                ['El Respetado (40 alas, 8 sabores)', 95],
                ['El Padrino (50 alas, 10 sabores)', 129],
                ['El Intocable (70 alas, 10 sabores)', 165],
            ],
            'Barcos' => [
                ['Barco Pequeño (salchipapa + 2 huevos)', 1],
                ['Barco Grande (70 alitas, papas fritas y arroz chaufa, cualquier sabor)', 1],
            ],
            'Cenas' => [
                ['Pechuga a la plancha', 15],
                ['Mostrito Broaster', 15],
                ['Chicharron de pollo', 15],
                ['Arroz a la cubana', 10],
                ['Broaster 1 pieza', 13],
                ['Broaster 2 piezas', 18],
                ['Chuleta flameada', 15],
                ['Churrasco flameado', 18],
                ['Lomo de pollo saltado', 15],
                ['Lomo saltado', 18],
                ['Chaufa de pollo', 12],
                ['Salchipapa', 10],
                ['Salchipollo', 12],
                ['Mollejitas', 15],
            ],
            'Adicionales' => [
                ['Arroz blanco', 3],
                ['Arroz chaufa', 5],
                ['Ensalada', 5],
                ['Papas fritas', 7],
            ],
            'Cervezas' => [
                ['Pilsen Callao', 8],
                ['Pilsen Trujillo', 8],
                ['Corona', 10],
            ],
            'Bebidas' => [
                ['Agua mineral 625 ml', 2],
                ['Inka Cola 600 ml', 5],
                ['Coca Cola 600 ml', 5],
                ['Fanta 500 ml', 5],
                ['Inka Cola 1 L', 8],
                ['Coca Cola 1 L', 8],
                ['Inka Cola 1.5 L', 10],
                ['Coca Cola 1.5 L', 10],
                ['Pepsi 1.5 L', 10],
            ],
            'Envases' => [
                ['Taper', 1],
            ],
        ];
        $requiredSauceCounts = [
            'El Al Toque (6 alas, 1 sabor)' => 1,
            'El Monse (10 alas, 2 sabores)' => 2,
            'El Firme (20 alas, 4 sabores)' => 4,
            'El Bravazo (30 alas, 5 sabores)' => 5,
            'El Respetado (40 alas, 8 sabores)' => 8,
            'El Padrino (50 alas, 10 sabores)' => 10,
            'El Intocable (70 alas, 10 sabores)' => 10,
            'Barco Grande (70 alitas, papas fritas y arroz chaufa, cualquier sabor)' => 10,
        ];

        DB::transaction(function () use ($menu, $requiredSauceCounts): void {
            foreach ($menu as $categoryName => $items) {
                $category = Category::firstOrCreate(['name' => $categoryName], ['is_active' => true]);

                foreach ($items as [$name, $price]) {
                    Product::firstOrCreate(
                        ['category_id' => $category->id, 'name' => $name],
                        [
                            'price' => $price,
                            'cost' => null,
                            'stock' => null,
                            'is_active' => true,
                            'is_saleable' => true,
                            'required_sauce_count' => $requiredSauceCounts[$name] ?? null,
                        ]
                    );
                }
            }
        });
    }
}