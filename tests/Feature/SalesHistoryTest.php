<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Order;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_index_shows_completed_sales_and_currency_metadata(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin']);
        $area = Area::create(['name' => 'Salón']);
        $table = Table::create([
            'area_id' => $area->id,
            'name' => 'Mesa 1',
            'seats' => 4,
            'status' => 'available',
        ]);

        Order::create([
            'table_id' => $table->id,
            'user_id' => $admin->id,
            'status' => 'completed',
            'total' => 80,
            'payment_method' => 'cash',
            'document_type' => 'Boleta',
            'client_name' => 'Cliente Uno',
            'created_at' => now(),
        ]);

        Order::create([
            'table_id' => $table->id,
            'user_id' => $admin->id,
            'status' => 'completed',
            'total' => 140,
            'payment_method' => 'card',
            'document_type' => 'Factura',
            'client_name' => 'Cliente Dos',
            'created_at' => now(),
        ]);

        Order::create([
            'table_id' => $table->id,
            'user_id' => $admin->id,
            'status' => 'pending',
            'total' => 999,
            'payment_method' => 'cash',
            'document_type' => 'Boleta',
            'client_name' => 'Cliente Tres',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('sales.index', [
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ]));

        $response->assertOk();
        $response->assertViewHas('currencySymbol', 'S/');
        $response->assertViewHas('totalSales', 220.0);
        $response->assertViewHas('orders', function ($orders) {
            return $orders->count() === 2;
        });
    }
}
