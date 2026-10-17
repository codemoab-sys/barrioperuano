<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Kitchen\KitchenCategory;
use App\Models\Kitchen\KitchenInventoryMovement;
use App\Models\Kitchen\KitchenRecipe;
use App\Models\Kitchen\KitchenRecipeItem;
use App\Models\Kitchen\KitchenRecipeVersion;
use App\Models\Kitchen\KitchenSupply;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class KitchenCatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $kitchen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->kitchen = User::factory()->create(['role' => 'kitchen']);
    }

    public function test_admin_can_create_category_and_duplicate_name_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('kitchen_inventory.categories.store'), ['name' => 'Secos'])
            ->assertRedirect(route('kitchen_inventory.categories.index'));

        $this->assertDatabaseHas('bape_kitchen_categories', ['name' => 'Secos']);

        $this->actingAs($this->admin)
            ->from(route('kitchen_inventory.categories.index'))
            ->post(route('kitchen_inventory.categories.store'), ['name' => 'secos'])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('bape_kitchen_categories', 1);
    }

    public function test_categories_index_renders_edit_modal_for_admin(): void
    {
        KitchenCategory::create(['name' => 'Secos']);

        $this->actingAs($this->admin)
            ->get(route('kitchen_inventory.categories.index'))
            ->assertOk()
            ->assertSee('Secos')
            ->assertSee('editCategoryModal', false)
            ->assertSee('createCategoryModal', false);
    }

    public function test_category_update_persists_changes_and_rejects_duplicates(): void
    {
        $category = KitchenCategory::create(['name' => 'Legumbres']);
        $other = KitchenCategory::create(['name' => 'Lácteos']);

        $this->actingAs($this->admin)
            ->put(route('kitchen_inventory.categories.update', $category), ['name' => 'Granos', 'is_active' => '1'])
            ->assertRedirect(route('kitchen_inventory.categories.index'));

        $this->assertDatabaseHas('bape_kitchen_categories', ['id' => $category->id, 'name' => 'Granos']);

        $this->actingAs($this->admin)
            ->from(route('kitchen_inventory.categories.index'))
            ->put(route('kitchen_inventory.categories.update', $category), ['name' => 'lácteos'])
            ->assertSessionHasErrors('name');

        $this->assertSame('Granos', $category->fresh()->name);

        $this->actingAs($this->admin)
            ->put(route('kitchen_inventory.categories.update', $other), ['name' => 'Lácteos', 'is_active' => '0'])
            ->assertRedirect();

        $this->assertFalse((bool) $other->fresh()->is_active);
    }

    public function test_category_with_supplies_cannot_be_deleted(): void
    {
        $category = KitchenCategory::create(['name' => 'Lácteos']);
        KitchenSupply::create(['category_id' => $category->id, 'name' => 'Queso', 'unit' => 'kg', 'stock_current' => 0]);

        $this->actingAs($this->admin)
            ->delete(route('kitchen_inventory.categories.destroy', $category))
            ->assertSessionHasErrors('category');

        $this->assertDatabaseHas('bape_kitchen_categories', ['id' => $category->id]);
    }

    public function test_empty_category_can_be_deleted(): void
    {
        $category = KitchenCategory::create(['name' => 'Vacía']);

        $this->actingAs($this->admin)
            ->delete(route('kitchen_inventory.categories.destroy', $category))
            ->assertRedirect(route('kitchen_inventory.categories.index'));

        $this->assertDatabaseMissing('bape_kitchen_categories', ['id' => $category->id]);
    }

    public function test_new_supply_starts_with_zero_stock(): void
    {
        $category = KitchenCategory::create(['name' => 'Harinas']);

        $this->actingAs($this->admin)
            ->post(route('kitchen_inventory.supplies.store'), [
                'category_id' => $category->id,
                'name' => 'Harina',
                'unit' => 'kg',
            ])
            ->assertRedirect(route('kitchen_inventory.supplies.index'));

        $supply = KitchenSupply::query()->where('name', 'Harina')->firstOrFail();
        $this->assertSame('0.0000', $supply->stock_current);
        $this->assertDatabaseCount('bape_kitchen_inventory_movements', 0);
    }

    public function test_supply_name_is_unique_within_category_case_insensitive(): void
    {
        $category = KitchenCategory::create(['name' => 'Harinas']);
        KitchenSupply::create(['category_id' => $category->id, 'name' => 'Harina', 'unit' => 'kg', 'stock_current' => 0]);

        $this->actingAs($this->admin)
            ->post(route('kitchen_inventory.supplies.store'), [
                'category_id' => $category->id,
                'name' => 'harina',
                'unit' => 'kg',
            ])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('bape_kitchen_supplies', 1);
    }

    public function test_supply_with_history_cannot_be_deleted_and_gets_deactivation_guidance(): void
    {
        $category = KitchenCategory::create(['name' => 'Harinas']);
        $supply = KitchenSupply::create(['category_id' => $category->id, 'name' => 'Harina', 'unit' => 'kg', 'stock_current' => 0]);
        KitchenInventoryMovement::create([
            'kitchen_supply_id' => $supply->id,
            'type' => 'entry',
            'quantity' => 5,
            'stock_before' => 0,
            'stock_after' => 5,
            'idempotency_key' => 'test-movement-1',
        ]);

        $this->actingAs($this->admin)
            ->delete(route('kitchen_inventory.supplies.destroy', $supply))
            ->assertSessionHasErrors('supply');

        $this->assertDatabaseHas('bape_kitchen_supplies', ['id' => $supply->id]);
    }

    public function test_supply_referenced_by_recipe_cannot_be_deleted(): void
    {
        $category = KitchenCategory::create(['name' => 'Harinas']);
        $supply = KitchenSupply::create(['category_id' => $category->id, 'name' => 'Harina', 'unit' => 'kg', 'stock_current' => 0]);
        $product = Product::create(['category_id' => Category::create(['name' => 'Platos'])->id, 'name' => 'Plato X', 'price' => 10, 'stock' => null, 'is_active' => true]);
        $recipe = KitchenRecipe::create(['product_id' => $product->id, 'name' => 'Receta X']);
        $version = KitchenRecipeVersion::create(['recipe_id' => $recipe->id, 'version' => 1, 'status' => 'draft']);
        KitchenRecipeItem::create(['recipe_version_id' => $version->id, 'kitchen_supply_id' => $supply->id, 'quantity' => 1]);

        $this->actingAs($this->admin)
            ->delete(route('kitchen_inventory.supplies.destroy', $supply))
            ->assertSessionHasErrors('supply');

        $this->assertDatabaseHas('bape_kitchen_supplies', ['id' => $supply->id]);
    }

    public function test_history_free_supply_can_be_deleted(): void
    {
        $category = KitchenCategory::create(['name' => 'Harinas']);
        $supply = KitchenSupply::create(['category_id' => $category->id, 'name' => 'Harina', 'unit' => 'kg', 'stock_current' => 0]);

        $this->actingAs($this->admin)
            ->delete(route('kitchen_inventory.supplies.destroy', $supply))
            ->assertRedirect(route('kitchen_inventory.supplies.index'));

        $this->assertDatabaseMissing('bape_kitchen_supplies', ['id' => $supply->id]);
    }

    public function test_kitchen_role_has_read_only_access(): void
    {
        $this->actingAs($this->kitchen)
            ->get(route('kitchen_inventory.supplies.index'))
            ->assertOk();

        $this->actingAs($this->kitchen)
            ->get(route('kitchen_inventory.recipes.index'))
            ->assertOk();

        $this->actingAs($this->kitchen)
            ->get(route('kitchen_inventory.supplies.create'))
            ->assertForbidden();

        $category = KitchenCategory::create(['name' => 'X']);
        $this->actingAs($this->kitchen)
            ->post(route('kitchen_inventory.supplies.store'), ['category_id' => $category->id, 'name' => 'Y', 'unit' => 'kg'])
            ->assertForbidden();

        $supply = KitchenSupply::create(['category_id' => $category->id, 'name' => 'Z', 'unit' => 'kg', 'stock_current' => 0]);
        $this->actingAs($this->kitchen)
            ->post(route('kitchen_inventory.supplies.adjust', $supply), [
                'form_uuid' => (string) Str::uuid(),
                'type' => 'entry',
                'quantity' => 1,
                'note' => 'Intento no autorizado',
            ])
            ->assertForbidden();
    }

    public function test_supplies_index_hides_admin_links_from_kitchen_role(): void
    {
        $this->actingAs($this->kitchen)
            ->get(route('kitchen_inventory.supplies.index'))
            ->assertOk()
            ->assertDontSee(route('kitchen_inventory.movements.index'))
            ->assertDontSee(route('kitchen_inventory.categories.index'))
            ->assertSee(route('kitchen_inventory.recipes.index'));

        $this->actingAs($this->admin)
            ->get(route('kitchen_inventory.supplies.index'))
            ->assertOk()
            ->assertSee(route('kitchen_inventory.movements.index'))
            ->assertSee(route('kitchen_inventory.categories.index'));
    }

    public function test_supply_edit_includes_current_inactive_category_without_changing_it(): void
    {
        $inactive = KitchenCategory::create(['name' => 'Legumbres', 'is_active' => false]);
        KitchenCategory::create(['name' => 'Granos']);
        $supply = KitchenSupply::create(['category_id' => $inactive->id, 'name' => 'Frejol', 'unit' => 'kg', 'stock_current' => 0]);

        $this->actingAs($this->admin)
            ->get(route('kitchen_inventory.supplies.edit', $supply))
            ->assertOk()
            ->assertSee('Legumbres');

        $this->assertSame($inactive->id, (int) $supply->fresh()->category_id);
        $this->assertFalse((bool) $inactive->fresh()->is_active);
    }

    public function test_cashier_role_cannot_access_kitchen_inventory(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);

        $this->actingAs($cashier)
            ->get(route('kitchen_inventory.supplies.index'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('kitchen_inventory.supplies.index'))
            ->assertRedirect(route('login'));
    }

    public function test_new_supply_modal_is_in_index_and_json_creation_works(): void
    {
        $category = KitchenCategory::create(['name' => 'Lácteos']);

        $this->actingAs($this->admin)
            ->get(route('kitchen_inventory.supplies.index'))
            ->assertOk()
            ->assertSee('newSupplyModal', false)
            ->assertSee('data-new-supply', false)
            ->assertSee('nsSubmit', false);

        $this->actingAs($this->admin)
            ->postJson(route('kitchen_inventory.supplies.store'), [
                'category_id' => $category->id,
                'name' => 'Queso fresco',
                'unit' => 'kg',
            ])
            ->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('bape_kitchen_supplies', ['name' => 'Queso fresco', 'stock_current' => 0]);
    }
}
