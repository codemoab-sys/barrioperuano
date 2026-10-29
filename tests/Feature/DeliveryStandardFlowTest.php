<?php

namespace Tests\Feature;

use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryStandardFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->user = User::factory()->create(['role' => 'cashier']);
        CashRegister::create([
            'user_id' => $this->user->id,
            'opening_time' => now(),
            'opening_amount' => 0,
            'status' => 'open',
        ]);
        $category = Category::create(['name' => 'Platos']);
        $this->product = Product::create([
            'category_id' => $category->id,
            'name' => 'Lomo Saltado',
            'price' => 20,
            'stock' => 10,
            'is_active' => true,
            'is_saleable' => true,
        ]);
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'client_name'    => 'Cliente Delivery',
            'client_phone'   => '999111222',
            'address'        => 'Calle Principal 123',
            'payment_method' => 'transfer',
            'delivery_fee'   => 7,
            'products'       => [['id' => $this->product->id, 'qty' => 2]],
        ], $extra);
    }

    public function test_flujo_normal_store_then_checkout(): void
    {
        $response = $this->actingAs($this->user)->post(route('delivery.store'), $this->payload());
        $response->assertRedirect(route('delivery.index'));
        $response->assertSessionHasNoErrors();

        $order = Order::latest('id')->first();
        $delivery = Delivery::latest('id')->first();

        $this->assertSame('pending', $order->status);
        $this->assertSame(40.0, (float) $order->total);
        $this->assertSame('pending', $delivery->status);
        $this->assertSame(7.0, (float) $delivery->delivery_fee);
        $this->assertSame('Cliente Delivery', $delivery->client_name);
        $this->assertSame('Calle Principal 123', $delivery->address);
        $this->assertSame(8, (int) $this->product->fresh()->stock);

        // Cobro
        $response = $this->actingAs($this->user)->post(route('delivery.checkout', $delivery), [
            'received_amount' => 47,
            'document_type'   => 'Ticket',
        ]);
        $response->assertRedirect(route('delivery.index'));

        $order = $order->fresh();
        $delivery = $delivery->fresh();

        $this->assertSame('completed', $order->status);
        $this->assertSame('Ticket', $order->document_type);
        $this->assertSame(47.0, (float) $order->total);       // 40 + 7
        $this->assertSame(0.0, (float) $order->change_amount);
        $this->assertSame('delivered', $delivery->status);
        $this->assertNotNull($delivery->delivered_at);
    }

    public function test_flujo_normal_cobro_con_efectivo_genera_cambio(): void
    {
        $this->actingAs($this->user)->post(route('delivery.store'), $this->payload());
        $delivery = Delivery::latest('id')->first();

        $this->actingAs($this->user)->post(route('delivery.checkout', $delivery), [
            'received_amount' => 50,
            'document_type'   => 'Ticket',
        ])->assertRedirect(route('delivery.index'));

        $this->assertSame(3.0, (float) $delivery->order->fresh()->change_amount);
    }

    public function test_flujo_normal_cancelar_devuelve_el_stock(): void
    {
        $this->actingAs($this->user)->post(route('delivery.store'), $this->payload());
        $delivery = Delivery::latest('id')->first();
        $this->assertSame(8, (int) $this->product->fresh()->stock);

        $this->actingAs($this->user)->post(route('delivery.cancel', $delivery))
            ->assertRedirect(route('delivery.index'));

        $this->assertSame('cancelled', $delivery->fresh()->status);
        $this->assertSame(10, (int) $this->product->fresh()->stock);
    }

    public function test_no_se_puede_marcar_entregado_sin_cobro_pero_si_con_pedido_pagado(): void
    {
        $this->actingAs($this->user)->post(route('delivery.store'), $this->payload());
        $delivery = Delivery::latest('id')->first();

        // Sin cobrar → 422
        $this->actingAs($this->user)
            ->postJson(route('delivery.status', $delivery), ['status' => 'delivered'])
            ->assertStatus(422);

        // Con el pedido pagado (delivery directo) → permitido
        $order = $delivery->order;
        $order->update(['status' => 'completed']);
        $this->actingAs($this->user)
            ->postJson(route('delivery.status', $delivery), ['status' => 'delivered'])
            ->assertOk()
            ->assertJsonPath('success', true);
        $this->assertSame('delivered', $delivery->fresh()->status);
    }

    public function test_paginas_del_modulo_delivery_siguen_respondiendo(): void
    {
        $this->actingAs($this->user)->get(route('delivery.index'))->assertOk();
        $this->actingAs($this->user)->get(route('delivery.create'))->assertOk();
        $this->actingAs($this->user)->get(route('delivery.drivers'))->assertOk();

        $this->actingAs($this->user)->post(route('delivery.store'), $this->payload());
        $delivery = Delivery::latest('id')->first();
        $this->actingAs($this->user)->get(route('delivery.show', $delivery))->assertOk();
    }
}
