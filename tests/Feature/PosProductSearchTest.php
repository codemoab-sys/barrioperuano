<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Product;
use App\Models\Table;
use App\Models\User;
use Tests\TestCase;

class PosProductSearchTest extends TestCase
{
    public function test_pos_order_page_filters_products_by_name_while_typing(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);

        $this->actingAs($cashier)->post(route('cash_registers.store'), [
            'opening_amount' => 100,
        ]);

        $area = Area::create(['name' => 'Salón']);
        $table = Table::create([
            'area_id' => $area->id,
            'name' => 'Mesa 1',
            'status' => 'available',
        ]);

        $category = Category::create(['name' => 'Platos', 'is_active' => true]);
        Product::create([
            'name' => 'Ceviche',
            'category_id' => $category->id,
            'price' => 35,
            'cost' => 12,
            'stock' => 10,
            'is_active' => true,
            'is_saleable' => true,
        ]);

        $response = $this->actingAs($cashier)->get(route('pos.order', $table));

        $response->assertOk();
        $response->assertSee('barcodeInput');
        $response->assertSee("barcodeInput.addEventListener('input'");
    }
}
