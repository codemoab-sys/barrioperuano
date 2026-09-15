<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use Tests\TestCase;

class DeliveryProcessTest extends TestCase
{
    public function test_status_endpoint_cannot_bypass_checkout_or_cancellation(): void
    {
        $user = User::factory()->create(['role' => 'cashier']);
        $order = Order::create([
            'table_id' => null,
            'user_id' => $user->id,
            'status' => 'pending',
            'total' => 25,
        ]);
        $delivery = Delivery::create([
            'order_id' => $order->id,
            'client_name' => 'Cliente',
            'client_phone' => '999999999',
            'address' => 'Av. Principal 123',
            'user_id' => $user->id,
            'status' => 'pending',
            'payment_method' => 'cash',
        ]);

        $this->actingAs($user)
            ->postJson(route('delivery.status', $delivery), ['status' => 'delivered'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Un delivery solo se marca como entregado al completar el cobro.');

        $this->actingAs($user)
            ->postJson(route('delivery.status', $delivery), ['status' => 'cancelled'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Usa la acción de cancelar para liberar correctamente el pedido y su inventario.');

        $this->assertDatabaseHas('bape_deliveries', [
            'id' => $delivery->id,
            'status' => 'pending',
        ]);
    }
}
