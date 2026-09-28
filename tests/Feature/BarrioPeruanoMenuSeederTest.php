<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\WingSauceMenu;
use Database\Seeders\BarrioPeruanoMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BarrioPeruanoMenuSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_adds_menu_items_idempotently_without_changing_existing_products(): void
    {
        $category = Category::create(['name' => 'Alitas', 'is_active' => true]);
        $existingProduct = Product::create([
            'category_id' => $category->id,
            'name' => 'El Al Toque (6 alas, 1 sabor)',
            'price' => 99,
            'stock' => 7,
            'is_active' => true,
            'is_saleable' => true,
        ]);

        $seeder = new BarrioPeruanoMenuSeeder();
        $seeder->run();
        $productCount = Product::count();
        $seeder->run();

        $this->assertSame(39, $productCount);
        $this->assertSame($productCount, Product::count());
        $this->assertDatabaseHas('bape_products', [
            'id' => $existingProduct->id,
            'price' => 99,
            'stock' => 7,
        ]);
        $this->assertSame(38, Product::whereNull('stock')->count());

        $barcoGrande = Product::where('name', 'like', 'Barco Grande%')->firstOrFail();
        $barcoPequeno = Product::where('name', 'like', 'Barco Pequeño%')->firstOrFail();
        $this->assertSame(1.0, (float) $barcoGrande->price);
        $this->assertSame(1.0, (float) $barcoPequeno->price);
        $this->assertSame(1, WingSauceMenu::flavorLimit($barcoGrande));
        $this->assertSame('Barcos', $barcoGrande->category->name);
        $this->assertSame(10, Product::where('name', 'El Padrino (50 alas, 10 sabores)')->value('required_sauce_count'));
    }
}