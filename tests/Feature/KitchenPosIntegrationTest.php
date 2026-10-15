<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Kitchen\KitchenCategory;
use App\Models\Kitchen\KitchenInventoryMovement;
use App\Models\Kitchen\KitchenInventoryOperation;
use App\Models\Kitchen\KitchenOrderConsumption;
use App\Models\Kitchen\KitchenRecipe;
use App\Models\Kitchen\KitchenRecipeItem;
use App\Models\Kitchen\KitchenRecipeVersion;
use App\Models\Kitchen\KitchenSupply;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitchenPosIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Table $table;

    private Product $product;

    private KitchenSupply $supply;

    private KitchenRecipeVersion $version;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->user = User::factory()->create(['role' => 'cashier']);

        $area = Area::create(['name' => 'Salón']);
        $this->table = Table::create(['area_id' => $area->id, 'name' => 'Mesa 1']);

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

    public function test_flag_absent_by_default_keeps_commercial_checkout_untouched_without_kitchen_writes(): void
    {
        $this->supply->update(['stock_current' => 1]);
        $order = $this->pendingOrder(1);

        $this->checkout($order)
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertNotNull($order->cash_register_id);
        $this->assertSame(9.0, (float) $this->product->fresh()->stock);
        $this->assertDatabaseCount('bape_inventory_logs', 1);
        $this->assertSame('1.0000', $this->supply->fresh()->stock_current);
        $this->assertDatabaseCount('bape_kitchen_inventory_operations', 0);
        $this->assertDatabaseCount('bape_kitchen_order_consumptions', 0);
        $this->assertDatabaseCount('bape_kitchen_inventory_movements', 0);
    }

    public function test_flag_explicitly_disabled_keeps_kitchen_disengaged(): void
    {
        Setting::create(['key' => 'kitchen_stock_control', 'value' => '0']);
        $this->supply->update(['stock_current' => 1]);
        $order = $this->pendingOrder(1);

        $this->checkout($order)
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSame('completed', $order->refresh()->status);
        $this->assertSame('1.0000', $this->supply->fresh()->stock_current);
        $this->assertDatabaseCount('bape_kitchen_inventory_operations', 0);
    }

    public function test_flag_enabled_records_consumption_derived_from_stored_recipe_atomically(): void
    {
        $this->enableControl();
        $order = $this->pendingOrder(2);

        $this->checkout($order)
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertNotNull($order->cash_register_id);

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
        $this->assertSame($this->product->id, (int) $consumption->product_id);
        $this->assertSame($this->version->id, (int) $consumption->recipe_version_id);
        $this->assertSame('2.0000', $consumption->sold_quantity);
        $this->assertSame('2.0000', $consumption->recipe_quantity);
        $this->assertSame('4.0000', $consumption->theoretical_quantity);
        $this->assertSame('4.0000', $consumption->covered_quantity);
        $this->assertSame('0.0000', $consumption->shortage_quantity);

        $movement = KitchenInventoryMovement::firstOrFail();
        $this->assertSame('sale', $movement->type);
        $this->assertSame('4.0000', $movement->quantity);
        $this->assertSame('5.0000', $movement->stock_before);
        $this->assertSame('1.0000', $movement->stock_after);

        $this->assertSame('1.0000', $this->supply->fresh()->stock_current);
        $this->assertSame(8.0, (float) $this->product->fresh()->stock);
        $this->assertDatabaseCount('bape_inventory_logs', 1);
    }

    public function test_flag_enabled_with_insufficient_supply_aborts_the_whole_checkout(): void
    {
        $this->enableControl();
        $this->supply->update(['stock_current' => 1]);
        $order = $this->pendingOrder(1);
        $total = (float) $order->total;

        $this->checkout($order)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['stock']);

        $order->refresh();
        $this->assertSame('pending', $order->status);
        $this->assertNull($order->cash_register_id);
        $this->assertNull($order->issued_at);
        $this->assertSame($total, (float) $order->total);
        $this->assertSame(10.0, (float) $this->product->fresh()->stock);
        $this->assertDatabaseCount('bape_inventory_logs', 0);
        $this->assertSame('1.0000', $this->supply->fresh()->stock_current);
        $this->assertDatabaseCount('bape_kitchen_inventory_operations', 0);
        $this->assertDatabaseCount('bape_kitchen_order_consumptions', 0);
        $this->assertDatabaseCount('bape_kitchen_inventory_movements', 0);
    }

    public function test_repeated_checkout_is_rejected_without_duplicating_consumption(): void
    {
        $this->enableControl();
        $order = $this->pendingOrder(1);

        $this->checkout($order)
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->checkout($order)->assertStatus(403);

        $this->assertSame('completed', $order->refresh()->status);
        $this->assertDatabaseCount('bape_kitchen_inventory_operations', 1);
        $this->assertDatabaseCount('bape_kitchen_order_consumptions', 1);
        $this->assertDatabaseCount('bape_kitchen_inventory_movements', 1);
        $this->assertSame('3.0000', $this->supply->fresh()->stock_current);
        $this->assertSame(9.0, (float) $this->product->fresh()->stock);
        $this->assertDatabaseCount('bape_inventory_logs', 1);
    }

    public function test_products_without_published_recipe_complete_checkout_without_kitchen_requirements(): void
    {
        $this->enableControl();
        $this->supply->update(['stock_current' => 0]);

        $plain = Product::create([
            'category_id' => $this->product->category_id,
            'name' => 'Anticucho sin receta',
            'price' => 20,
            'stock' => null,
            'is_active' => true,
        ]);

        $order = Order::create([
            'table_id' => $this->table->id,
            'user_id' => $this->user->id,
            'status' => 'pending',
            'total' => 20,
        ]);
        $order->details()->create([
            'product_id' => $plain->id,
            'quantity' => 1,
            'price' => 20,
            'status' => 'pending',
        ]);

        $this->checkout($order)
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSame('completed', $order->refresh()->status);
        $this->assertDatabaseCount('bape_kitchen_inventory_operations', 0);
        $this->assertDatabaseCount('bape_kitchen_order_consumptions', 0);
        $this->assertSame('0.0000', $this->supply->fresh()->stock_current);
    }

    public function test_draft_recipe_version_generates_no_kitchen_requirement(): void
    {
        $this->enableControl();
        $this->supply->update(['stock_current' => 0]);

        $this->version->update(['status' => 'draft']);

        $order = $this->pendingOrder(1);

        $this->checkout($order)
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSame('completed', $order->refresh()->status);
        $this->assertDatabaseCount('bape_kitchen_inventory_operations', 0);
        $this->assertSame('0.0000', $this->supply->fresh()->stock_current);
    }

    public function test_add_to_order_is_blocked_when_kitchen_supply_is_insufficient_and_control_enabled(): void
    {
        $this->enableControl();
        $this->supply->update(['stock_current' => 1]);

        $response = $this->actingAs($this->user)
            ->postJson(route('pos.add', $this->table), ['product_id' => $this->product->id]);

        $response->assertStatus(422);
        $this->assertStringContainsString('cocina', (string) $response->json('error'));
        $this->assertDatabaseCount('bape_order_details', 0);
        $this->assertDatabaseCount('bape_orders', 0);
    }

    public function test_add_to_order_skips_kitchen_validation_when_control_disabled(): void
    {
        $this->supply->update(['stock_current' => 0]);

        $this->actingAs($this->user)
            ->postJson(route('pos.add', $this->table), ['product_id' => $this->product->id])
            ->assertStatus(200);

        $this->assertSame(1, Order::where('table_id', $this->table->id)->where('status', 'pending')->count());
        $this->assertDatabaseCount('bape_order_details', 1);
        $this->assertSame('0.0000', $this->supply->fresh()->stock_current);
        $this->assertDatabaseCount('bape_kitchen_inventory_operations', 0);
    }

    public function test_add_validation_counts_supply_reserved_by_other_pending_table_orders(): void
    {
        $this->enableControl();
        $this->supply->update(['stock_current' => 5]);

        $secondArea = Area::create(['name' => 'Terraza']);
        $secondTable = Table::create(['area_id' => $secondArea->id, 'name' => 'Mesa 2']);
        $otherOrder = Order::create([
            'table_id' => $secondTable->id,
            'user_id' => $this->user->id,
            'status' => 'pending',
            'total' => 30,
        ]);
        $otherOrder->details()->create([
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 15,
            'status' => 'pending',
        ]);

        $this->actingAs($this->user)
            ->postJson(route('pos.add', $this->table), ['product_id' => $this->product->id])
            ->assertStatus(422);
        $this->assertDatabaseCount('bape_order_details', 1);

        $otherOrder->details()->delete();
        $otherOrder->delete();

        $this->actingAs($this->user)
            ->postJson(route('pos.add', $this->table), ['product_id' => $this->product->id])
            ->assertStatus(200);
        $this->assertSame(1, OrderDetail::count());
        $this->assertSame(1, Order::where('table_id', $this->table->id)->where('status', 'pending')->count());
    }

    public function test_update_quantity_increase_beyond_projected_kitchen_demand_is_rejected_when_control_enabled(): void
    {
        $this->enableControl();
        $order = $this->pendingOrder(1);
        $detail = $order->details()->firstOrFail();

        $blocked = $this->actingAs($this->user)
            ->postJson(route('pos.update', $detail), ['quantity' => 3]);
        $blocked->assertStatus(422);
        $this->assertStringContainsString('cocina', (string) $blocked->json('error'));
        $this->assertSame(1, $detail->fresh()->quantity);
        $this->assertSame('5.0000', $this->supply->fresh()->stock_current);

        $this->actingAs($this->user)
            ->postJson(route('pos.update', $detail), ['quantity' => 2])
            ->assertStatus(200);
        $this->assertSame(2, $detail->fresh()->quantity);
        $this->assertSame('5.0000', $this->supply->fresh()->stock_current);
        $this->assertDatabaseCount('bape_kitchen_inventory_operations', 0);
    }

    public function test_update_quantity_counts_reservations_of_other_pending_table_orders_when_control_enabled(): void
    {
        $this->enableControl();

        $secondArea = Area::create(['name' => 'Terraza']);
        $secondTable = Table::create(['area_id' => $secondArea->id, 'name' => 'Mesa 2']);
        $otherOrder = Order::create([
            'table_id' => $secondTable->id,
            'user_id' => $this->user->id,
            'status' => 'pending',
            'total' => 15,
        ]);
        $otherOrder->details()->create([
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => 15,
            'status' => 'pending',
        ]);

        $order = $this->pendingOrder(1);
        $detail = $order->details()->firstOrFail();

        $blocked = $this->actingAs($this->user)
            ->postJson(route('pos.update', $detail), ['quantity' => 2]);
        $blocked->assertStatus(422);
        $this->assertStringContainsString('cocina', (string) $blocked->json('error'));
        $this->assertSame(1, $detail->fresh()->quantity);

        $otherOrder->details()->delete();
        $otherOrder->delete();

        $this->actingAs($this->user)
            ->postJson(route('pos.update', $detail), ['quantity' => 2])
            ->assertStatus(200);
        $this->assertSame(2, $detail->fresh()->quantity);
    }

    public function test_update_quantity_keeps_current_behavior_when_control_disabled(): void
    {
        $secondArea = Area::create(['name' => 'Terraza']);
        $secondTable = Table::create(['area_id' => $secondArea->id, 'name' => 'Mesa 2']);
        $otherOrder = Order::create([
            'table_id' => $secondTable->id,
            'user_id' => $this->user->id,
            'status' => 'pending',
            'total' => 15,
        ]);
        $otherOrder->details()->create([
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => 15,
            'status' => 'pending',
        ]);

        $order = $this->pendingOrder(1);
        $detail = $order->details()->firstOrFail();

        $this->actingAs($this->user)
            ->postJson(route('pos.update', $detail), ['quantity' => 3])
            ->assertStatus(200);

        $this->assertSame(3, $detail->fresh()->quantity);
        $this->assertSame('5.0000', $this->supply->fresh()->stock_current);
        $this->assertDatabaseCount('bape_kitchen_inventory_operations', 0);
    }

    public function test_update_quantity_decrease_and_removal_still_work_when_control_enabled(): void
    {
        $this->enableControl();
        $order = $this->pendingOrder(2);
        $detail = $order->details()->firstOrFail();

        $this->actingAs($this->user)
            ->postJson(route('pos.update', $detail), ['quantity' => 1])
            ->assertStatus(200);
        $this->assertSame(1, $detail->fresh()->quantity);
        $this->assertSame('5.0000', $this->supply->fresh()->stock_current);

        $this->actingAs($this->user)
            ->postJson(route('pos.update', $detail), ['quantity' => 0])
            ->assertStatus(200);
        $this->assertDatabaseCount('bape_order_details', 0);
        $this->assertDatabaseCount('bape_orders', 0);
        $this->assertSame('5.0000', $this->supply->fresh()->stock_current);
        $this->assertDatabaseCount('bape_kitchen_inventory_operations', 0);
    }

    public function test_update_quantity_projects_total_across_lines_of_same_product_when_control_enabled(): void
    {
        $this->enableControl();
        $order = $this->pendingOrder(1);
        $order->details()->create([
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => 15,
            'status' => 'pending',
        ]);

        $lines = $order->details()->orderBy('id')->get();
        $firstLine = $lines->first();

        $blocked = $this->actingAs($this->user)
            ->postJson(route('pos.update', $firstLine), ['quantity' => 2]);
        $blocked->assertStatus(422);
        $this->assertStringContainsString('cocina', (string) $blocked->json('error'));
        $this->assertSame(1, $firstLine->fresh()->quantity);
        $this->assertSame(1, $lines->last()->fresh()->quantity);
    }

    private function enableControl(): void
    {
        Setting::create(['key' => 'kitchen_stock_control', 'value' => '1']);
    }

    private function pendingOrder(int $quantity): Order
    {
        $order = Order::create([
            'table_id' => $this->table->id,
            'user_id' => $this->user->id,
            'status' => 'pending',
            'total' => 15 * $quantity,
        ]);

        $order->details()->create([
            'product_id' => $this->product->id,
            'quantity' => $quantity,
            'price' => 15,
            'status' => 'pending',
        ]);

        return $order;
    }

    private function checkout(Order $order)
    {
        return $this->actingAs($this->user)->postJson(route('pos.checkout', $order), [
            'payment_method' => 'cash',
            'received_amount' => (float) $order->total,
            'document_type' => 'Ticket',
        ]);
    }
}
