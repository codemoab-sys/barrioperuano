<?php

namespace Tests\Feature;

use App\Models\Kitchen\KitchenCategory;
use App\Models\Kitchen\KitchenInventoryMovement;
use App\Models\Kitchen\KitchenSupply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitchenKardexTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_movements_are_listed_with_type_label_user_and_note(): void
    {
        $category = KitchenCategory::create(['name' => 'Base']);
        $harina = KitchenSupply::create(['category_id' => $category->id, 'name' => 'Harina', 'unit' => 'kg', 'stock_current' => 0]);
        $azucar = KitchenSupply::create(['category_id' => $category->id, 'name' => 'Azúcar', 'unit' => 'kg', 'stock_current' => 0]);

        KitchenInventoryMovement::create([
            'kitchen_supply_id' => $harina->id,
            'type' => 'entry',
            'quantity' => 10,
            'stock_before' => 0,
            'stock_after' => 10,
            'idempotency_key' => 'kardex-1',
            'user_id' => $this->admin->id,
            'note' => 'Recepción de compra 001',
        ]);
        KitchenInventoryMovement::create([
            'kitchen_supply_id' => $azucar->id,
            'type' => 'sale',
            'quantity' => 2,
            'stock_before' => 5,
            'stock_after' => 3,
            'idempotency_key' => 'kardex-2',
        ]);

        $response = $this->actingAs($this->admin)->get(route('kitchen_inventory.movements.index'));
        $response->assertOk()
            ->assertSee('Recepción de compra 001')
            ->assertSee($this->admin->name)
            ->assertSee('Entrada')
            ->assertSee('Venta');
    }

    public function test_kardex_filters_by_supply_and_type(): void
    {
        $category = KitchenCategory::create(['name' => 'Base']);
        $harina = KitchenSupply::create(['category_id' => $category->id, 'name' => 'Harina', 'unit' => 'kg', 'stock_current' => 0]);
        $azucar = KitchenSupply::create(['category_id' => $category->id, 'name' => 'Azúcar', 'unit' => 'kg', 'stock_current' => 0]);

        KitchenInventoryMovement::create(['kitchen_supply_id' => $harina->id, 'type' => 'entry', 'quantity' => 10, 'stock_before' => 0, 'stock_after' => 10, 'idempotency_key' => 'f-1', 'note' => 'Entrada harina']);
        KitchenInventoryMovement::create(['kitchen_supply_id' => $azucar->id, 'type' => 'waste', 'quantity' => 1, 'stock_before' => 4, 'stock_after' => 3, 'idempotency_key' => 'f-2', 'note' => 'Merma azúcar']);

        $this->actingAs($this->admin)
            ->get(route('kitchen_inventory.movements.index', ['supply_id' => $harina->id]))
            ->assertOk()
            ->assertSee('Entrada harina')
            ->assertDontSee('Merma azúcar');

        $this->actingAs($this->admin)
            ->get(route('kitchen_inventory.movements.index', ['type' => 'waste']))
            ->assertOk()
            ->assertSee('Merma azúcar')
            ->assertDontSee('Entrada harina');
    }

    public function test_kitchen_role_cannot_view_kardex(): void
    {
        $kitchen = User::factory()->create(['role' => 'kitchen']);

        $this->actingAs($kitchen)
            ->get(route('kitchen_inventory.movements.index'))
            ->assertForbidden();
    }
}
