<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\CashRegister;
use App\Models\Order;
use App\Models\Table;
use App\Models\User;
use Tests\TestCase;

class SaleCancellationTest extends TestCase
{
    private User $cashier;

    private Table $table;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->cashier = User::factory()->create(['role' => 'cashier']);

        $area = Area::create(['name' => 'Salón']);
        $this->table = Table::create(['area_id' => $area->id, 'name' => 'Mesa 1']);

        CashRegister::create([
            'user_id' => $this->cashier->id,
            'opening_time' => now(),
            'opening_amount' => 0,
            'status' => 'open',
        ]);
    }

    private function completedOrder(array $attributes = []): Order
    {
        return Order::create(array_merge([
            'table_id' => $this->table->id,
            'user_id' => $this->cashier->id,
            'status' => 'completed',
            'total' => 50,
            'payment_method' => 'cash',
            'document_type' => 'Ticket',
            'client_name' => 'Cliente Varios',
            'issued_at' => now(),
        ], $attributes));
    }

    public function test_last_sales_panel_offers_annulment_for_tickets(): void
    {
        $ticket = $this->completedOrder();
        $boleta = $this->completedOrder([
            'document_type' => 'Boleta',
            'sunat_status' => 'ACCEPTED',
        ]);

        $response = $this->actingAs($this->cashier)->get(route('pos.index'));

        $response->assertOk();
        $response->assertSee('Anular');
        $response->assertSee('cancelSaleModal');
        $response->assertSee('/sales/'.$ticket->id.'/cancel', false);
        $response->assertSee('/billing/'.$boleta->id.'/cancel', false);
    }

    public function test_waiters_do_not_see_the_annul_action(): void
    {
        $this->completedOrder();
        $waiter = User::factory()->create(['role' => 'waiter']);

        $response = $this->actingAs($waiter)->get(route('pos.index'));

        $response->assertOk();
        $response->assertDontSee('cancelSaleModal');
    }

    public function test_ticket_is_annulled_locally_with_motivo(): void
    {
        $order = $this->completedOrder();

        $response = $this->actingAs($this->cashier)->post(route('sales.cancel', $order), [
            'motivo' => 'ERROR DE SISTEMA',
        ]);

        $response->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('CANCELLED', $order->anulacion_status);
        $this->assertSame('ERROR DE SISTEMA', $order->anulacion_description);
    }

    public function test_ticket_cancellation_requires_motivo(): void
    {
        $order = $this->completedOrder();

        $response = $this->actingAs($this->cashier)->post(route('sales.cancel', $order), []);

        $response->assertSessionHasErrors('motivo');
        $this->assertSame('completed', $order->refresh()->status);
    }

    public function test_only_tickets_are_annulled_locally(): void
    {
        $order = $this->completedOrder([
            'document_type' => 'Boleta',
            'sunat_status' => 'ACCEPTED',
        ]);

        $response = $this->actingAs($this->cashier)->post(route('sales.cancel', $order), [
            'motivo' => 'ERROR DE SISTEMA',
        ]);

        $response->assertNotFound();
        $this->assertSame('completed', $order->refresh()->status);
    }
}
