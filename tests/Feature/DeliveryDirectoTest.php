<?php

namespace Tests\Feature;

use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Client;
use App\Models\Delivery;
use App\Models\DocumentSeries;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryDirectoTest extends TestCase
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
            'name' => 'Papa a la Huancaína',
            'price' => 15,
            'stock' => 10,
            'is_active' => true,
            'is_saleable' => true,
        ]);
    }

    public function test_nota_de_venta_asigna_el_cliente_varios_y_cobra_el_pedido(): void
    {
        $varios = Client::create(['name' => 'Cliente - Varios', 'document_number' => '999999999']);

        $response = $this->actingAs($this->user)->post(route('delivery-direct.store'), [
            'client_phone'    => '999888777',
            'client_name'     => 'Juan Perez',
            'delivery_fee'    => 5,
            'products'        => [['id' => $this->product->id, 'qty' => 2]],
            'document_type'   => 'Ticket',
            'payment_mode'   => 'now',
            'payment_method'  => 'cash',
            'received_amount' => 40,
        ]);

        $response->assertRedirect(route('delivery.index'));
        $response->assertSessionHasNoErrors();

        $order = Order::latest('id')->first();
        $this->assertSame('completed', $order->status);
        $this->assertSame('Ticket', $order->document_type);
        $this->assertSame($varios->id, $order->client_id);
        $this->assertSame(35.0, (float) $order->total);
        $this->assertSame(5.0, (float) $order->change_amount);
        $this->assertSame('cash', $order->payment_method);
        $this->assertNull($order->serie);

        $delivery = Delivery::latest('id')->first();
        $this->assertSame('pending', $delivery->status);
        $this->assertSame('999888777', $delivery->client_phone);
        $this->assertSame(5.0, (float) $delivery->delivery_fee);
        $this->assertSame($order->id, $delivery->order_id);

        $this->assertSame(8, (int) $this->product->fresh()->stock);
    }

    public function test_boleta_usa_el_cliente_elegido_y_asigna_serie(): void
    {
        $client = Client::create(['name' => 'Cliente Boleta', 'document_number' => '12345678']);
        DocumentSeries::create([
            'document_code' => '03',
            'document_type' => 'boleta',
            'serie' => 'B001',

            'last_number' => 0,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->postJson(route('delivery-direct.store'), [
            'client_phone'    => '999888777',
            'client_name'     => 'Juan Perez',
            'delivery_fee'    => 0,
            'products'        => [['id' => $this->product->id, 'qty' => 1]],
            'document_type'   => 'Boleta',
            'payment_mode'   => 'now',
            'payment_method'  => 'transfer',
            'client_id'       => $client->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $order = Order::latest('id')->first();
        $this->assertSame('Boleta', $order->document_type);
        $this->assertSame($client->id, $order->client_id);
        $this->assertSame('B001', $order->serie);
        $this->assertSame(1, (int) $order->correlativo);
        $this->assertSame(15.0, (float) $order->total);
        $this->assertSame(15.0, (float) $order->total_gravada + (float) $order->igv);
    }

    public function test_factura_sin_ruc_valido_es_rechazada(): void
    {
        $client = Client::create(['name' => 'Sin Ruc', 'document_number' => '12345678']);

        $response = $this->actingAs($this->user)->postJson(route('delivery-direct.store'), [
            'client_phone'   => '999888777',
            'products'       => [['id' => $this->product->id, 'qty' => 1]],
            'document_type'  => 'Factura',
            'payment_mode'   => 'now',
            'payment_method' => 'transfer',
            'client_id'      => $client->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Para emitir Factura el cliente debe tener RUC de 11 dígitos.');

        $this->assertSame(0, Order::count());
        $this->assertSame(0, Delivery::count());
        $this->assertSame(10, (int) $this->product->fresh()->stock);
    }

    public function test_monto_recibido_insuficiente_no_deja_pedido_registrado(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('delivery-direct.store'), [
            'client_phone'    => '999888777',
            'products'        => [['id' => $this->product->id, 'qty' => 1]],
            'document_type'   => 'Ticket',
            'payment_mode'   => 'now',
            'payment_method'  => 'cash',
            'received_amount' => 5,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'El monto recibido es insuficiente.');

        $this->assertSame(0, Order::count());
        $this->assertSame(0, Delivery::count());
        $this->assertSame(10, (int) $this->product->fresh()->stock);
    }

    public function test_sin_caja_abierta_no_se_puede_cobrar(): void
    {
        CashRegister::query()->update(['status' => 'closed']);

        $response = $this->actingAs($this->user)->post(route('delivery-direct.store'), [
            'client_phone'   => '999888777',
            'products'       => [['id' => $this->product->id, 'qty' => 1]],
            'payment_mode'   => 'now',
            'document_type'  => 'Ticket',
            'payment_method' => 'transfer',
        ]);


        $response->assertRedirect(route('cash_registers.create'));
        $this->assertSame(0, Order::count());
    }

    public function test_cobrar_despues_deja_el_pedido_pendiente_sin_comprobante(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('delivery-direct.store'), [
            'client_phone'   => '999888777',
            'client_name'    => 'Juan Perez',
            'delivery_fee'   => 5,
            'products'       => [['id' => $this->product->id, 'qty' => 2]],
            'payment_mode'   => 'later',
            'payment_method' => 'transfer',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('pending', true);
        $this->assertNotEmpty($response->json('pre_order_url'));

        $order = Order::latest('id')->first();
        $delivery = Delivery::latest('id')->first();

        // Queda pendiente: sin cobrar y sin comprobante emitido, el fee se suma al cobrar
        $this->assertSame('pending', $order->status);
        $this->assertNull($order->issued_at);
        $this->assertNull($order->serie);
        $this->assertNull($order->correlativo);
        $this->assertSame(30.0, (float) $order->total);

        $this->assertSame('pending', $delivery->status);
        $this->assertSame(5.0, (float) $delivery->delivery_fee);
        $this->assertSame('transfer', $delivery->payment_method);
        $this->assertSame(8, (int) $this->product->fresh()->stock);

        // Se cobra después con el checkout normal (total = subtotal + delivery)
        $this->actingAs($this->user)->post(route('delivery.checkout', $delivery), [
            'received_amount' => 45,
            'document_type'   => 'Ticket',
        ])->assertRedirect(route('delivery.index'));


        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(35.0, (float) $order->fresh()->total);
        $this->assertSame('delivered', $delivery->fresh()->status);

    }

    public function test_la_pre_orden_se_puede_imprimir(): void
    {
        $this->actingAs($this->user)->post(route('delivery.store'), [
            'client_name'    => 'Cliente Delivery',
            'client_phone'   => '999111222',
            'address'        => 'Calle Principal 123',
            'payment_method' => 'transfer',
            'products'       => [['id' => $this->product->id, 'qty' => 2]],
        ]);

        $delivery = Delivery::latest('id')->first();

        $response = $this->actingAs($this->user)->get(route('delivery.pre-order', $delivery));
        $response->assertOk();
        $response->assertSee('PEDIDO DELIVERY');
        $response->assertSee($this->product->name);
        $response->assertSee('Calle Principal 123');

    }
}
