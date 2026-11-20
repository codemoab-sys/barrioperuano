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
use App\Models\Product;
use App\Models\Setting;
use App\Models\Table;
use App\Models\User;
use App\Services\PosCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PosCheckoutDoubleCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Table $table;

    private Product $product;

    private KitchenSupply $supply;

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
        $version = KitchenRecipeVersion::create([
            'recipe_id' => $recipe->id,
            'version' => 1,
            'status' => 'published',
        ]);
        KitchenRecipeItem::create([
            'recipe_version_id' => $version->id,
            'kitchen_supply_id' => $this->supply->id,
            'quantity' => 2,
        ]);
    }

    public function test_stale_second_checkout_is_blocked_and_stock_is_reduced_only_once_with_control_disabled(): void
    {
        $order = $this->pendingOrder(1);
        $service = $this->app->make(PosCheckoutService::class);

        $first = $service->completeCheckout($order, $this->payload($order));
        $this->assertSame('completed', $first->status);
        $this->assertSame(9.0, (float) $this->product->fresh()->stock);
        $this->assertDatabaseCount('bape_inventory_logs', 1);

        try {
            $service->completeCheckout($order, $this->payload($order));
            $this->fail('La segunda ejecución con instancia obsoleta debió ser bloqueada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('general', $exception->errors());
            $this->assertStringContainsString('Orden cerrada', $exception->errors()['general'][0]);
        }

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(9.0, (float) $this->product->fresh()->stock);
        $this->assertDatabaseCount('bape_inventory_logs', 1);
        $this->assertDatabaseCount('bape_kitchen_inventory_operations', 0);
    }

    public function test_stale_second_checkout_is_blocked_and_kitchen_consumes_only_once_with_control_enabled(): void
    {
        Setting::create(['key' => 'kitchen_stock_control', 'value' => '1']);
        $order = $this->pendingOrder(1);
        $service = $this->app->make(PosCheckoutService::class);

        $first = $service->completeCheckout($order, $this->payload($order));
        $this->assertSame('completed', $first->status);
        $this->assertSame(9.0, (float) $this->product->fresh()->stock);
        $this->assertSame('3.0000', $this->supply->fresh()->stock_current);
        $this->assertSame(1, KitchenInventoryOperation::count());

        try {
            $service->completeCheckout($order, $this->payload($order));
            $this->fail('La segunda ejecución con instancia obsoleta debió ser bloqueada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('general', $exception->errors());
            $this->assertStringContainsString('Orden cerrada', $exception->errors()['general'][0]);
        }

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(9.0, (float) $this->product->fresh()->stock);
        $this->assertSame('3.0000', $this->supply->fresh()->stock_current);
        $this->assertDatabaseCount('bape_inventory_logs', 1);
        $this->assertSame(1, KitchenInventoryOperation::count());
        $this->assertSame(1, KitchenOrderConsumption::count());
        $this->assertSame(1, KitchenInventoryMovement::count());
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

    private function payload(Order $order): array
    {
        return [
            'payment_method' => 'cash',
            'received' => (float) $order->total,
            'change' => 0.0,
            'client_name' => 'Público',
            'document_type' => 'Ticket',
        ];
    }
}
