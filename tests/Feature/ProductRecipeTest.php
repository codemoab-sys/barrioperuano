<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ProductRecipeTest extends TestCase
{
    use WithFaker;

    public function test_admin_can_create_product_with_recipe_ingredients(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $category = Category::create([
            'name' => 'Platos principales',
            'is_active' => true,
        ]);

        $ingredient = Product::create([
            'name' => 'Pan de hamburguesa',
            'category_id' => $category->id,
            'price' => 1.50,
            'cost' => 0.80,
            'stock' => 50,
            'is_active' => true,
            'is_saleable' => false,
            'is_new' => false,
            'is_chef_recommendation' => false,
        ]);

        $response = $this
            ->actingAs($user)
            ->post('/products', [
                'name' => 'Hamburguesa especial',
                'category_id' => $category->id,
                'price' => 24.90,
                'cost' => 12.00,
                'stock' => 0,
                'is_saleable' => 'on',
                'is_new' => 'on',
                'is_chef_recommendation' => 'on',
                'ingredients' => [
                    $ingredient->id => '0.50',
                ],
            ]);

        $response->assertRedirect('/products');

        $product = Product::where('name', 'Hamburguesa especial')->firstOrFail();

        $this->assertDatabaseHas('bape_product_ingredients', [
            'product_id' => $product->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => '0.50',
        ]);

        $this->assertTrue($product->ingredients()->where('ingredient_id', $ingredient->id)->exists());
    }

    public function test_admin_can_update_product_recipe_ingredients(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $category = Category::create([
            'name' => 'Platos principales',
            'is_active' => true,
        ]);

        $ingredientA = Product::create([
            'name' => 'Pan',
            'category_id' => $category->id,
            'price' => 1.00,
            'cost' => 0.50,
            'stock' => 30,
            'is_active' => true,
            'is_saleable' => false,
            'is_new' => false,
            'is_chef_recommendation' => false,
        ]);

        $ingredientB = Product::create([
            'name' => 'Carne',
            'category_id' => $category->id,
            'price' => 6.50,
            'cost' => 4.00,
            'stock' => 20,
            'is_active' => true,
            'is_saleable' => false,
            'is_new' => false,
            'is_chef_recommendation' => false,
        ]);

        $product = Product::create([
            'name' => 'Burger de prueba',
            'category_id' => $category->id,
            'price' => 18.00,
            'cost' => 8.00,
            'stock' => 0,
            'is_active' => true,
            'is_saleable' => true,
            'is_new' => false,
            'is_chef_recommendation' => false,
        ]);

        $product->ingredients()->sync([
            $ingredientA->id => ['quantity' => 0.30],
        ]);

        $this->actingAs($user)
            ->put('/products/' . $product->id, [
                'name' => 'Burger de prueba',
                'category_id' => $category->id,
                'price' => 18.00,
                'cost' => 8.00,
                'stock' => 0,
                'is_saleable' => 'on',
                'is_new' => 'off',
                'is_chef_recommendation' => 'off',
                'ingredients' => [
                    $ingredientB->id => '0.75',
                ],
            ]);

        $this->assertDatabaseHas('bape_product_ingredients', [
            'product_id' => $product->id,
            'ingredient_id' => $ingredientB->id,
            'quantity' => '0.75',
        ]);

        $this->assertDatabaseMissing('bape_product_ingredients', [
            'product_id' => $product->id,
            'ingredient_id' => $ingredientA->id,
        ]);
    }

    public function test_pos_cannot_add_recipe_product_when_ingredient_stock_is_insufficient(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'role' => 'admin',
        ]);
        CashRegister::create([
            'user_id' => $user->id,
            'opening_time' => now(),
            'opening_amount' => 0,
            'status' => 'open',
        ]);

        $area = Area::create(['name' => 'Salón']);
        $table = Table::create([
            'area_id' => $area->id,
            'name' => 'Mesa 1',
            'seats' => 4,
            'status' => 'available',
        ]);

        $category = Category::create([
            'name' => 'Platos principales',
            'is_active' => true,
        ]);

        $ingredient = Product::create([
            'name' => 'Pan de hamburguesa',
            'category_id' => $category->id,
            'price' => 1.50,
            'cost' => 0.80,
            'stock' => 1,
            'is_active' => true,
            'is_saleable' => false,
            'is_new' => false,
            'is_chef_recommendation' => false,
        ]);

        $product = Product::create([
            'name' => 'Hamburguesa especial',
            'category_id' => $category->id,
            'price' => 24.90,
            'cost' => 12.00,
            'stock' => 10,
            'is_active' => true,
            'is_saleable' => true,
            'is_new' => false,
            'is_chef_recommendation' => false,
        ]);

        $product->ingredients()->sync([
            $ingredient->id => ['quantity' => 2],
        ]);

        $response = $this
            ->actingAs($user)
            ->post('/pos/order/' . $table->id . '/add', [
                'product_id' => $product->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['error' => 'Stock insuficiente para el ingrediente: Pan de hamburguesa']);
    }

    public function test_pos_checkout_reduces_ingredient_stock_for_recipe_products(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'role' => 'admin',
        ]);
        CashRegister::create([
            'user_id' => $user->id,
            'opening_time' => now(),
            'opening_amount' => 0,
            'status' => 'open',
        ]);

        $area = Area::create(['name' => 'Salón']);
        $table = Table::create([
            'area_id' => $area->id,
            'name' => 'Mesa 2',
            'seats' => 4,
            'status' => 'available',
        ]);

        $category = Category::create([
            'name' => 'Platos principales',
            'is_active' => true,
        ]);

        $ingredient = Product::create([
            'name' => 'Pan de hamburguesa',
            'category_id' => $category->id,
            'price' => 1.50,
            'cost' => 0.80,
            'stock' => 10,
            'is_active' => true,
            'is_saleable' => false,
            'is_new' => false,
            'is_chef_recommendation' => false,
        ]);

        $product = Product::create([
            'name' => 'Hamburguesa especial',
            'category_id' => $category->id,
            'price' => 24.90,
            'cost' => 12.00,
            'stock' => 50,
            'is_active' => true,
            'is_saleable' => true,
            'is_new' => false,
            'is_chef_recommendation' => false,
        ]);

        $product->ingredients()->sync([
            $ingredient->id => ['quantity' => 2],
        ]);

        $this->actingAs($user)
            ->post('/pos/order/' . $table->id . '/add', [
                'product_id' => $product->id,
            ]);

        $order = $table->fresh()->orders()->where('status', 'pending')->firstOrFail();

        $response = $this->actingAs($user)
            ->post('/pos/order/' . $order->id . '/checkout', [
                'payment_method' => 'cash',
                'received_amount' => 25.00,
                'document_type' => 'Ticket',
            ]);

        $response->assertRedirect('/pos');
        $this->assertDatabaseHas('bape_orders', [
            'id' => $order->id,
            'status' => 'completed',
        ]);

        $ingredient->refresh();
        $this->assertSame(8, $ingredient->stock);
    }

    public function test_delivery_cannot_be_created_without_open_cash_register(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $category = Category::create([
            'name' => 'Platos principales',
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'Hamburguesa especial',
            'category_id' => $category->id,
            'price' => 24.90,
            'cost' => 12.00,
            'stock' => 10,
            'is_active' => true,
            'is_saleable' => true,
            'is_new' => false,
            'is_chef_recommendation' => false,
        ]);

        $response = $this->actingAs($user)->from('/delivery/create')->post('/delivery', [
            'client_name' => 'Cliente test',
            'client_phone' => '987654321',
            'address' => 'Av. Principal 123',
            'payment_method' => 'cash',
            'products' => [[
                'id' => $product->id,
                'qty' => 1,
            ]],
        ]);

        $response->assertSessionHasErrors(['general']);
    }

    public function test_delivery_cancellation_reverts_product_stock(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        CashRegister::create([
            'user_id' => $user->id,
            'opening_time' => now(),
            'opening_amount' => 0,
            'status' => 'open',
        ]);

        $category = Category::create([
            'name' => 'Platos principales',
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'Ceviche',
            'category_id' => $category->id,
            'price' => 35.00,
            'cost' => 15.00,
            'stock' => 5,
            'is_active' => true,
            'is_saleable' => true,
            'is_new' => false,
            'is_chef_recommendation' => false,
        ]);

        $order = Order::create([
            'table_id' => null,
            'user_id' => $user->id,
            'status' => 'pending',
            'total' => 70.00,
            'payment_method' => 'cash',
            'cash_register_id' => $user->activeCashRegister->id,
            'client_name' => 'Cliente test',
        ]);

        $order->details()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => $product->price,
            'status' => 'pending',
        ]);

        $delivery = Delivery::create([
            'order_id' => $order->id,
            'client_name' => 'Cliente test',
            'client_phone' => '987654321',
            'address' => 'Av. Principal 123',
            'driver_id' => null,
            'user_id' => $user->id,
            'cash_register_id' => $user->activeCashRegister->id,
            'status' => 'pending',
            'payment_method' => 'cash',
            'delivery_fee' => 0,
        ]);

        $product->decrement('stock', 2);

        $this->actingAs($user)->post('/delivery/' . $delivery->id . '/cancel');

        $product->refresh();
        $this->assertSame(5, $product->stock);
    }

    public function test_delivery_cannot_be_created_when_recipe_ingredient_stock_is_insufficient(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        CashRegister::create([
            'user_id' => $user->id,
            'opening_time' => now(),
            'opening_amount' => 0,
            'status' => 'open',
        ]);

        $category = Category::create([
            'name' => 'Platos principales',
            'is_active' => true,
        ]);

        $ingredient = Product::create([
            'name' => 'Pan de hamburguesa',
            'category_id' => $category->id,
            'price' => 1.50,
            'cost' => 0.80,
            'stock' => 1,
            'is_active' => true,
            'is_saleable' => false,
            'is_new' => false,
            'is_chef_recommendation' => false,
        ]);

        $product = Product::create([
            'name' => 'Hamburguesa especial',
            'category_id' => $category->id,
            'price' => 24.90,
            'cost' => 12.00,
            'stock' => 10,
            'is_active' => true,
            'is_saleable' => true,
            'is_new' => false,
            'is_chef_recommendation' => false,
        ]);

        $product->ingredients()->sync([
            $ingredient->id => ['quantity' => 2],
        ]);

        $response = $this->actingAs($user)->from('/delivery/create')->post('/delivery', [
            'client_name' => 'Cliente test',
            'client_phone' => '987654321',
            'address' => 'Av. Principal 123',
            'payment_method' => 'cash',
            'products' => [[
                'id' => $product->id,
                'qty' => 1,
            ]],
        ]);

        $response->assertSessionHasErrors(['products']);
    }

    public function test_sales_service_reduces_stock_for_order_details(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $category = Category::create([
            'name' => 'Platos principales',
            'is_active' => true,
        ]);

        $ingredient = Product::create([
            'name' => 'Pan de hamburguesa',
            'category_id' => $category->id,
            'price' => 1.50,
            'cost' => 0.80,
            'stock' => 10,
            'is_active' => true,
            'is_saleable' => false,
            'is_new' => false,
            'is_chef_recommendation' => false,
        ]);

        $product = Product::create([
            'name' => 'Hamburguesa especial',
            'category_id' => $category->id,
            'price' => 24.90,
            'cost' => 12.00,
            'stock' => 50,
            'is_active' => true,
            'is_saleable' => true,
            'is_new' => false,
            'is_chef_recommendation' => false,
        ]);

        $product->ingredients()->sync([
            $ingredient->id => ['quantity' => 2],
        ]);

        $order = Order::create([
            'table_id' => null,
            'user_id' => $user->id,
            'status' => 'pending',
            'total' => 24.90,
            'payment_method' => 'cash',
            'client_name' => 'Cliente test',
        ]);

        $order->details()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => $product->price,
            'status' => 'pending',
        ]);

        app(\App\Services\SalesService::class)->applyStockReductionForOrder($order, 'TEST');

        $ingredient->refresh();
        $this->assertSame(6, $ingredient->stock);
    }
}
