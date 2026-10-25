<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Order;
use App\Models\Table;
use App\Models\User;
use Tests\TestCase;

class PrecheckTicketTest extends TestCase
{
    private User $cashier;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->cashier = User::factory()->create(['role' => 'cashier']);

        $area = Area::create(['name' => 'Salón']);
        $table = Table::create(['area_id' => $area->id, 'name' => 'Mesa 1']);

        $this->order = Order::create([
            'table_id' => $table->id,
            'user_id' => $this->cashier->id,
            'status' => 'pending',
            'total' => 50,
            'payment_method' => 'cash',
            'document_type' => 'Ticket',
            'client_name' => 'Cliente Varios',
        ]);
    }

    public function test_precheck_prints_larger_fonts(): void
    {
        $response = $this->actingAs($this->cashier)->get(route('pos.precheck', $this->order->id));

        $response->assertOk();
        $response->assertSee('font-size: 16px');
        $response->assertSee('font-size: 21px');
    }

    public function test_sale_ticket_keeps_default_font_size(): void
    {
        $response = $this->actingAs($this->cashier)->get(route('sales.ticket', $this->order->id));

        $response->assertOk();
        $response->assertSee('font-size: 12px');
        $response->assertDontSee('font-size: 21px');
    }
}
