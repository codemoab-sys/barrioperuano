<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Category;
use App\Models\Kitchen\KitchenRecipe;
use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitchenRecipeDeactivationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $product;

    private KitchenRecipe $recipe;

    private Table $table;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->product = Product::create(['category_id' => Category::create(['name' => 'Platos'])->id, 'name' => 'Tallarín Rojo', 'price' => 20, 'stock' => null, 'is_active' => true]);
        $this->recipe = KitchenRecipe::create(['product_id' => $this->product->id, 'name' => 'Tallarín']);
        $this->table = Table::create(['area_id' => Area::create(['name' => 'Salón'])->id, 'name' => 'Mesa 9']);
    }

    public function test_deactivate_succeeds_without_pending_orders(): void
    {
        $this->actingAs($this->admin)
            ->post(route('kitchen_inventory.recipes.deactivate', $this->recipe))
            ->assertRedirect(route('kitchen_inventory.recipes.show', $this->recipe));

        $this->assertFalse($this->recipe->fresh()->is_active);
    }

    public function test_activate_restores_the_recipe(): void
    {
        $this->recipe->update(['is_active' => false]);

        $this->actingAs($this->admin)
            ->post(route('kitchen_inventory.recipes.activate', $this->recipe))
            ->assertRedirect();

        $this->assertTrue($this->recipe->fresh()->is_active);
    }

    public function test_deactivation_is_blocked_by_pending_table_order(): void
    {
        $this->pendingTableOrder();

        $this->actingAs($this->admin)
            ->from(route('kitchen_inventory.recipes.show', $this->recipe))
            ->post(route('kitchen_inventory.recipes.deactivate', $this->recipe))
            ->assertSessionHasErrors('recipe');

        $this->assertTrue($this->recipe->fresh()->is_active);
    }

    public function test_deactivation_is_blocked_by_pending_delivery_order(): void
    {
        $this->pendingDeliveryOrder();

        $this->actingAs($this->admin)
            ->post(route('kitchen_inventory.recipes.deactivate', $this->recipe))
            ->assertSessionHasErrors('recipe');

        $this->assertTrue($this->recipe->fresh()->is_active);
    }

    public function test_completed_order_does_not_block_deactivation(): void
    {
        $order = $this->pendingTableOrder();
        $order->update(['status' => 'completed']);

        $this->actingAs($this->admin)
            ->post(route('kitchen_inventory.recipes.deactivate', $this->recipe))
            ->assertRedirect();

        $this->assertFalse($this->recipe->fresh()->is_active);
    }

    public function test_pending_order_for_another_product_does_not_block(): void
    {
        $other = Product::create(['category_id' => Category::create(['name' => 'Otros'])->id, 'name' => 'Otro Plato', 'price' => 10, 'stock' => null, 'is_active' => true]);
        $this->pendingTableOrder($other);

        $this->actingAs($this->admin)
            ->post(route('kitchen_inventory.recipes.deactivate', $this->recipe))
            ->assertRedirect();

        $this->assertFalse($this->recipe->fresh()->is_active);
    }

    public function test_guard_works_independently_of_the_disabled_flag(): void
    {
        $this->assertDatabaseMissing('bape_settings', ['key' => 'kitchen_stock_control']);
        $this->pendingTableOrder();

        $this->actingAs($this->admin)
            ->post(route('kitchen_inventory.recipes.deactivate', $this->recipe))
            ->assertSessionHasErrors('recipe');

        $this->assertTrue($this->recipe->fresh()->is_active);
    }

    public function test_kitchen_role_cannot_deactivate(): void
    {
        $kitchen = User::factory()->create(['role' => 'kitchen']);

        $this->actingAs($kitchen)
            ->post(route('kitchen_inventory.recipes.deactivate', $this->recipe))
            ->assertForbidden();

        $this->assertTrue($this->recipe->fresh()->is_active);
    }

    private function pendingTableOrder(?Product $product = null): Order
    {
        $order = Order::create([
            'table_id' => $this->table->id,
            'user_id' => $this->admin->id,
            'status' => 'pending',
            'total' => 20,
        ]);
        $order->details()->create([
            'product_id' => ($product ?? $this->product)->id,
            'quantity' => 1,
            'price' => 20,
        ]);

        return $order;
    }

    private function pendingDeliveryOrder(): Order
    {
        $order = Order::create([
            'table_id' => null,
            'user_id' => $this->admin->id,
            'status' => 'pending',
            'total' => 20,
        ]);
        $order->details()->create([
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => 20,
        ]);

        return $order;
    }
}
