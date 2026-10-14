<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Table;
use App\Models\User;
use Tests\TestCase;

class WingSauceSelectionTest extends TestCase
{
    public function test_wing_combo_requires_allowed_sauces_and_keeps_different_choices_separate(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'all_areas' => true]);
        CashRegister::create([
            'user_id' => $cashier->id,
            'opening_time' => now(),
            'opening_amount' => 0,
            'status' => 'open',
        ]);

        $area = Area::create(['name' => 'Salon']);
        $table = Table::create(['area_id' => $area->id, 'name' => 'Mesa 1']);
        $category = Category::create(['name' => 'Alitas', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'El Al Toque (6 alas, 1 sabor)',
            'price' => 18,
            'stock' => null,
            'required_sauce_count' => 1,
            'is_active' => true,
            'is_saleable' => true,
        ]);

        $endpoint = route('pos.add', $table);
        $barcoGrande = Product::create([
            'category_id' => $category->id,
            'name' => 'Barco Grande (70 alitas, papas fritas y arroz chaufa, cualquier sabor)',
            'price' => 1,
            'stock' => null,
            'required_sauce_count' => 10,
            'is_active' => true,
            'is_saleable' => true,
        ]);
        $this->actingAs($cashier)->postJson($endpoint, ['product_id' => $barcoGrande->id])
            ->assertOk()
            ->assertJsonPath('requires_sauces', true)
            ->assertJsonPath('max_sauces', 10);

        $this->actingAs($cashier)->postJson($endpoint, ['product_id' => $product->id])
            ->assertOk()
            ->assertJsonPath('requires_sauces', true)
            ->assertJsonPath('max_sauces', 1);
        $this->assertDatabaseCount('bape_orders', 0);

        $this->postJson($endpoint, [
            'product_id' => $product->id,
            'sauce_choices' => ['BBQ', 'Ajo'],
        ])->assertUnprocessable();
        $this->assertDatabaseCount('bape_orders', 0);

        $this->postJson($endpoint, [
            'product_id' => $product->id,
            'sauce_choices' => ['BBQ'],
        ])->assertOk();

        $this->postJson($endpoint, [
            'product_id' => $product->id,
            'sauce_choices' => ['Ajo'],
        ])->assertOk();

        $order = Order::with('details')->firstOrFail();
        $this->assertCount(2, $order->details);
        $this->assertEqualsCanonicalizing(
            [['BBQ'], ['Ajo']],
            $order->details->pluck('sauce_choices')->all()
        );
    }

    public function test_delivery_requires_and_persists_wing_sauce_choices(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'all_areas' => true]);
        CashRegister::create([
            'user_id' => $cashier->id,
            'opening_time' => now(),
            'opening_amount' => 0,
            'status' => 'open',
        ]);

        $category = Category::create(['name' => 'Alitas', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'El Monse (10 alas, 2 sabores)',
            'price' => 29,
            'stock' => null,
            'required_sauce_count' => 2,
            'is_active' => true,
            'is_saleable' => true,
        ]);
        $payload = [
            'client_name' => 'Cliente Delivery',
            'client_phone' => '999111222',
            'address' => 'Calle Principal 123',
            'payment_method' => 'cash',
            'products' => [['id' => $product->id, 'qty' => 1]],
        ];

        $this->actingAs($cashier)->post(route('delivery.store'), $payload)
            ->assertSessionHasErrors('products.0.sauce_choices');
        $this->assertDatabaseCount('bape_orders', 0);

        $payload['products'][0]['sauce_choices'] = ['BBQ', 'Original'];
        $payload['products'][0]['sauce_choices'] = ['BBQ'];
        $this->post(route('delivery.store'), $payload)
            ->assertRedirect(route('delivery.index'));
        $this->assertSame(['BBQ'], OrderDetail::firstOrFail()->sauce_choices);

        $payload['products'][0]['sauce_choices'] = ['BBQ', 'Original'];
        $this->post(route('delivery.store'), $payload)
            ->assertRedirect(route('delivery.index'));

        $this->assertEqualsCanonicalizing(
            [['BBQ'], ['BBQ', 'Original']],
            OrderDetail::query()->orderBy('id')->pluck('sauce_choices')->all()
        );
    }

    public function test_delivery_sums_duplicate_product_lines_before_validating_stock(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'all_areas' => true]);
        CashRegister::create([
            'user_id' => $cashier->id,
            'opening_time' => now(),
            'opening_amount' => 0,
            'status' => 'open',
        ]);

        $category = Category::create(['name' => 'Alitas', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'El Monse (10 alas, 2 sabores)',
            'price' => 29,
            'stock' => 5,
            'required_sauce_count' => 2,
            'is_active' => true,
            'is_saleable' => true,
        ]);

        $response = $this->actingAs($cashier)->from('/delivery/create')->post(route('delivery.store'), [
            'client_name' => 'Cliente Delivery',
            'client_phone' => '999111222',
            'address' => 'Calle Principal 123',
            'payment_method' => 'cash',
            'products' => [
                ['id' => $product->id, 'qty' => 3, 'sauce_choices' => ['BBQ', 'Original']],
                ['id' => $product->id, 'qty' => 3, 'sauce_choices' => ['Ajo', 'Parrillera']],
            ],
        ]);

        $response->assertSessionHasErrors('products');
        $this->assertDatabaseCount('bape_orders', 0);
        $this->assertSame(5, $product->fresh()->stock);
    }
}