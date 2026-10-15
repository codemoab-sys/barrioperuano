<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Delivery;
use App\Models\Kitchen\KitchenCategory;
use App\Models\Kitchen\KitchenInventoryMovement;
use App\Models\Kitchen\KitchenInventoryOperation;
use App\Models\Kitchen\KitchenOrderConsumption;
use App\Models\Kitchen\KitchenRecipe;
use App\Models\Kitchen\KitchenRecipeItem;
use App\Models\Kitchen\KitchenRecipeVersion;
use App\Models\Kitchen\KitchenSupply;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Table;
use App\Models\User;
use App\Services\KitchenInventoryService;
use App\Services\KitchenStockControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class KitchenDeliveryIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Product $product;

    private KitchenSupply $supply;

    private KitchenRecipeVersion $version;

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
        ]);

        $kitchenCategory = KitchenCategory::create(['name' => 'Insumos']);
        $this->supply = KitchenSupply::create([
            'category_id' => $kitchenCategory->id,
            'name' => 'Queso',
            'unit' => 'kg',
            'stock_current' => 5,
        ]);

        $recipe = KitchenRecipe::create(['product_id' => $this->product->id, 'name' => 'Receta']);
        $this->version = KitchenRecipeVersion::create([
            'recipe_id' => $recipe->id,
            'version' => 1,
            'status' => 'published',
        ]);
        KitchenRecipeItem::create([
            'recipe_version_id' => $this->version->id,
            'kitchen_supply_id' => $this->supply->id,
            'quantity' => 2,
        ]);
    }

    public function test_delivery_creation_consumes_kitchen_atomically_when_control_enabled(): void
    {
        $this->enableControl();

        $this->storeDelivery(2)
            ->assertStatus(302)
            ->assertSessionHas('success');

        $order = Order::whereNull('table_id')->firstOrFail();
        $this->assertSame('pending', $order->status);
        $this->assertSame(2, $order->details()->sum('quantity'));

        $delivery = Delivery::where('order_id', $order->id)->firstOrFail();
        $this->assertSame('pending', $delivery->status);

        $this->assertSame(8.0, (float) $this->product->fresh()->stock);
        $this->assertDatabaseCount('bape_inventory_logs', 1);
        $this->assertSame('1.0000', $this->supply->fresh()->stock_current);

        $this->assertDatabaseHas('bape_kitchen_inventory_operations', [
            'event_key' => 'sale:order:'.$order->id,
            'operation_type' => 'sale',
            'status' => 'confirmed',
        ]);
        $this->assertSame(1, KitchenInventoryOperation::count());
        $this->assertSame(1, KitchenOrderConsumption::count());
        $this->assertSame(1, KitchenInventoryMovement::count());

        $consumption = KitchenOrderConsumption::firstOrFail();
        $this->assertSame($order->id, (int) $consumption->order_id);
        $this->assertSame('2.0000', $consumption->sold_quantity);
        $this->assertSame('4.0000', $consumption->covered_quantity);
        $this->assertSame('0.0000', $consumption->shortage_quantity);

        $movement = KitchenInventoryMovement::firstOrFail();
        $this->assertSame('sale', $movement->type);
        $this->assertSame('4.0000', $movement->quantity);
    }

    public function test_delivery_creation_with_insufficient_kitchen_supply_rolls_back_everything(): void
    {
        $this->enableControl();
        $this->supply->update(['stock_current' => 1]);

        $this->storeDelivery(1)
            ->assertStatus(302)
            ->assertSessionHasErrors(['stock']);

        $this->assertDatabaseCount('bape_orders', 0);
        $this->assertDatabaseCount('bape_deliveries', 0);
        $this->assertDatabaseCount('bape_order_details', 0);
        $this->assertSame(10.0, (float) $this->product->fresh()->stock);
        $this->assertDatabaseCount('bape_inventory_logs', 0);
        $this->assertSame('1.0000', $this->supply->fresh()->stock_current);
        $this->assertDatabaseCount('bape_kitchen_inventory_operations', 0);
        $this->assertDatabaseCount('bape_kitchen_order_consumptions', 0);
        $this->assertDatabaseCount('bape_kitchen_inventory_movements', 0);
    }

    public function test_retrying_kitchen_consumption_for_the_same_delivery_order_is_idempotent(): void
    {
        $this->enableControl();
        $this->storeDelivery(1)->assertStatus(302);
        $order = Order::whereNull('table_id')->firstOrFail();

        $lines = app(KitchenStockControl::class)->consumptionLinesFor($order);
        $first = app(KitchenInventoryService::class)
            ->recordSale('sale:order:'.$order->id, $lines, true, $this->user->id);
        $retry = app(KitchenInventoryService::class)
            ->recordSale('sale:order:'.$order->id, $lines, true, $this->user->id);

        $this->assertSame($first->id, $retry->id);
        $this->assertSame(1, KitchenInventoryOperation::count());
        $this->assertSame(1, KitchenOrderConsumption::count());
        $this->assertSame(1, KitchenInventoryMovement::count());
        $this->assertSame('3.0000', $this->supply->fresh()->stock_current);
    }

    public function test_cancel_with_control_disabled_never_touches_kitchen_inventory(): void
    {
        $this->supply->update(['stock_current' => 1]);
        $this->storeDelivery(1)->assertStatus(302);
        $order = Order::whereNull('table_id')->firstOrFail();
        $delivery = Delivery::where('order_id', $order->id)->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('delivery.cancel', $delivery))
            ->assertStatus(302)
            ->assertSessionHas('success');

        $this->assertSame('1.0000', $this->supply->fresh()->stock_current);
        $this->assertSame(10.0, (float) $this->product->fresh()->stock);
        $this->assertDatabaseCount('bape_kitchen_inventory_operations', 0);
        $this->assertDatabaseCount('bape_kitchen_order_consumptions', 0);
        $this->assertDatabaseCount('bape_kitchen_inventory_movements', 0);
        $this->assertSame('cancelled', $delivery->fresh()->status);
        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_cancel_after_consumption_returns_all_remaining_kitchen_stock(): void
    {
        $this->enableControl();
        $this->storeDelivery(2)->assertStatus(302);
        $order = Order::whereNull('table_id')->firstOrFail();
        $delivery = Delivery::where('order_id', $order->id)->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('delivery.cancel', $delivery))
            ->assertStatus(302)
            ->assertSessionHas('success');

        $this->assertSame('cancelled', $delivery->fresh()->status);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(10.0, (float) $this->product->fresh()->stock);

        $this->assertSame('5.0000', $this->supply->fresh()->stock_current);
        $this->assertDatabaseHas('bape_kitchen_inventory_operations', [
            'event_key' => 'cancel:order:'.$order->id,
            'operation_type' => 'cancellation',
            'status' => 'confirmed',
        ]);
        $this->assertSame(2, KitchenInventoryOperation::count());
        $this->assertSame('4.0000', KitchenOrderConsumption::firstOrFail()->returned_quantity);
        $this->assertSame(1, KitchenInventoryMovement::where('type', 'cancellation')->count());

        $this->actingAs($this->user)
            ->post(route('delivery.cancel', $delivery))
            ->assertStatus(302)
            ->assertSessionHas('error');

        $this->assertSame(2, KitchenInventoryOperation::count());
        $this->assertSame('5.0000', $this->supply->fresh()->stock_current);
    }

    public function test_physical_partial_return_over_return_and_cancel_of_remainder(): void
    {
        $this->enableControl();
        $this->storeDelivery(2)->assertStatus(302);
        $order = Order::whereNull('table_id')->firstOrFail();
        $delivery = Delivery::where('order_id', $order->id)->firstOrFail();
        $consumption = KitchenOrderConsumption::firstOrFail();
        $kitchen = app(KitchenInventoryService::class);

        $kitchen->recordReturn(
            'return:order:'.$order->id.':r1',
            [['consumption_id' => $consumption->id, 'quantity' => 1]],
            'return',
            $this->user->id
        );
        $this->assertSame('2.0000', $this->supply->fresh()->stock_current);
        $this->assertSame('1.0000', $consumption->fresh()->returned_quantity);

        $kitchen->recordReturn(
            'return:order:'.$order->id.':r1',
            [['consumption_id' => $consumption->id, 'quantity' => 1]],
            'return',
            $this->user->id
        );
        $this->assertSame('2.0000', $this->supply->fresh()->stock_current);
        $this->assertSame(1, KitchenInventoryMovement::where('type', 'return')->count());

        try {
            $kitchen->recordReturn(
                'return:order:'.$order->id.':r2',
                [['consumption_id' => $consumption->id, 'quantity' => 4]],
                'return',
                $this->user->id
            );
            $this->fail('Una devolución que supera el saldo cubierto debe ser rechazada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }
        $this->assertSame('2.0000', $this->supply->fresh()->stock_current);
        $this->assertSame('1.0000', $consumption->fresh()->returned_quantity);

        $this->actingAs($this->user)
            ->post(route('delivery.cancel', $delivery))
            ->assertStatus(302)
            ->assertSessionHas('success');

        $this->assertSame('5.0000', $this->supply->fresh()->stock_current);
        $this->assertSame('4.0000', $consumption->fresh()->returned_quantity);
        $cancellation = KitchenInventoryMovement::where('type', 'cancellation')->firstOrFail();
        $this->assertSame('3.0000', $cancellation->quantity);
    }

    public function test_availability_accounts_for_consumed_delivery_and_reserved_table_orders(): void
    {
        $this->enableControl();

        $area = Area::create(['name' => 'Salón']);
        $table = Table::create(['area_id' => $area->id, 'name' => 'Mesa 1']);
        $tableOrder = Order::create([
            'table_id' => $table->id,
            'user_id' => $this->user->id,
            'status' => 'pending',
            'total' => 15,
        ]);
        $tableOrder->details()->create([
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => 15,
            'status' => 'pending',
        ]);

        $this->storeDelivery(1)->assertStatus(302);
        $this->assertSame('3.0000', $this->supply->fresh()->stock_current);

        $control = app(KitchenStockControl::class);
        $this->assertNotNull(
            $control->availabilityError($this->product, 1, null),
            'La reserva de la mesa pendiente (2) sobre stock 3 deja 1 disponible: 1 unidad más debe rechazarse.'
        );

        $tableOrder->details()->delete();
        $tableOrder->delete();

        $this->assertNull(
            $control->availabilityError($this->product, 1, null),
            'Liberada la reserva de la mesa, el delivery ya consumido no debe volver a descontarse.'
        );
    }

    public function test_delivery_checkout_after_consumption_does_not_duplicate_or_return_kitchen_stock(): void
    {
        $this->enableControl();
        $this->storeDelivery(1)->assertStatus(302);
        $order = Order::whereNull('table_id')->firstOrFail();
        $delivery = Delivery::where('order_id', $order->id)->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('delivery.checkout', $delivery), [
                'received_amount' => (float) $delivery->total_with_fee,
                'document_type' => 'Ticket',
            ])
            ->assertStatus(302)
            ->assertSessionHas('success');

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame('delivered', $delivery->fresh()->status);
        $this->assertSame('3.0000', $this->supply->fresh()->stock_current);
        $this->assertSame(1, KitchenInventoryOperation::count());
        $this->assertSame(1, KitchenInventoryMovement::where('type', 'sale')->count());
        $this->assertSame('0.0000', KitchenOrderConsumption::firstOrFail()->returned_quantity);
    }

    public function test_flag_disabled_preserves_current_delivery_behavior(): void
    {
        $this->supply->update(['stock_current' => 0]);

        $this->storeDelivery(2)->assertStatus(302);

        $order = Order::whereNull('table_id')->firstOrFail();
        $this->assertSame('pending', $order->status);
        $this->assertSame(8.0, (float) $this->product->fresh()->stock);
        $this->assertSame('0.0000', $this->supply->fresh()->stock_current);
        $this->assertDatabaseCount('bape_kitchen_inventory_operations', 0);
        $this->assertDatabaseCount('bape_kitchen_order_consumptions', 0);
        $this->assertDatabaseCount('bape_kitchen_inventory_movements', 0);

        $delivery = Delivery::where('order_id', $order->id)->firstOrFail();
        $this->actingAs($this->user)
            ->post(route('delivery.cancel', $delivery))
            ->assertStatus(302)
            ->assertSessionHas('success');

        $this->assertSame(10.0, (float) $this->product->fresh()->stock);
        $this->assertSame('0.0000', $this->supply->fresh()->stock_current);
        $this->assertDatabaseCount('bape_kitchen_inventory_operations', 0);
    }

    private function enableControl(): void
    {
        Setting::create(['key' => 'kitchen_stock_control', 'value' => '1']);
    }

    private function storeDelivery(int $quantity)
    {
        return $this->actingAs($this->user)->post(route('delivery.store'), [
            'client_name' => 'Cliente Delivery',
            'client_phone' => '999999999',
            'address' => 'Av. Los Olivos 123',
            'payment_method' => 'transfer',
            'products' => [
                ['id' => $this->product->id, 'qty' => $quantity],
            ],
        ]);
    }
}
