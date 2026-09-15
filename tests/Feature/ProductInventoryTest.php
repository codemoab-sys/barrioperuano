<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_page_exposes_currency_and_ajax_adjustment_works(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Platos', 'is_active' => true]);
        $product = Product::create([
            'name' => 'Ceviche',
            'category_id' => $category->id,
            'price' => 35,
            'cost' => 12,
            'stock' => 10,
            'is_active' => true,
            'is_saleable' => true,
        ]);

        $pageResponse = $this->actingAs($admin)->get(route('products.index'));
        $pageResponse->assertOk();
        $pageResponse->assertViewHas('currencySymbol', 'S/');

        $adjustResponse = $this->actingAs($admin)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->post(route('products.adjust', $product->id), [
                'quantity' => 3,
                'type' => 'add',
            ]);

        $adjustResponse->assertOk();
        $adjustResponse->assertJsonPath('new_stock', 13);
        $adjustResponse->assertJsonPath('message', 'Stock ajustado.');
    }
}
