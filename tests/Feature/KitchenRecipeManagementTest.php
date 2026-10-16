<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Kitchen\KitchenCategory;
use App\Models\Kitchen\KitchenInventoryOperation;
use App\Models\Kitchen\KitchenOrderConsumption;
use App\Models\Kitchen\KitchenRecipe;
use App\Models\Kitchen\KitchenRecipeItem;
use App\Models\Kitchen\KitchenRecipeVersion;
use App\Models\Kitchen\KitchenSupply;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitchenRecipeManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_product_can_have_only_one_recipe(): void
    {
        $product = $this->product('Ceviche');
        KitchenRecipe::create(['product_id' => $product->id, 'name' => 'Ceviche']);

        $this->actingAs($this->admin)
            ->post(route('kitchen_inventory.recipes.store'), ['product_id' => $product->id, 'name' => 'Otra'])
            ->assertSessionHasErrors('product_id');

        $this->assertDatabaseCount('bape_kitchen_recipes', 1);
    }

    public function test_recipe_store_and_version_auto_numbering(): void
    {
        $product = $this->product('Arroz Chaufa');

        $this->actingAs($this->admin)
            ->post(route('kitchen_inventory.recipes.store'), ['product_id' => $product->id, 'name' => 'Chaufa'])
            ->assertRedirect();

        $recipe = KitchenRecipe::query()->where('product_id', $product->id)->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('kitchen_inventory.recipes.versions.store', $recipe))
            ->assertRedirect();

        $first = KitchenRecipeVersion::query()->where('recipe_id', $recipe->id)->firstOrFail();
        $this->assertSame(1, (int) $first->version);
        $this->assertSame('draft', $first->status);

        $this->actingAs($this->admin)
            ->post(route('kitchen_inventory.recipes.versions.store', $recipe));

        $this->assertSame([1, 2], KitchenRecipeVersion::query()->where('recipe_id', $recipe->id)->orderBy('version')->pluck('version')->map(fn ($v) => (int) $v)->all());
    }

    public function test_draft_items_are_saved_and_replaced(): void
    {
        [$recipe, $version, $harina, $azucar] = $this->draftRecipe();

        $this->actingAs($this->admin)
            ->put(route('kitchen_inventory.versions.update', $version), [
                'items' => [
                    ['kitchen_supply_id' => $harina->id, 'quantity' => 2],
                    ['kitchen_supply_id' => $azucar->id, 'quantity' => 0.5],
                ],
            ])
            ->assertRedirect(route('kitchen_inventory.recipes.show', $recipe));

        $this->assertDatabaseCount('bape_kitchen_recipe_items', 2);

        $this->actingAs($this->admin)
            ->put(route('kitchen_inventory.versions.update', $version), [
                'items' => [
                    ['kitchen_supply_id' => $harina->id, 'quantity' => 3],
                ],
            ]);

        $this->assertDatabaseCount('bape_kitchen_recipe_items', 1);
        $this->assertSame('3.0000', KitchenRecipeItem::query()->firstOrFail()->quantity);
    }

    public function test_draft_rejects_duplicate_supplies_and_invalid_quantities(): void
    {
        [, $version, $harina] = $this->draftRecipe();

        $this->actingAs($this->admin)
            ->put(route('kitchen_inventory.versions.update', $version), [
                'items' => [
                    ['kitchen_supply_id' => $harina->id, 'quantity' => 1],
                    ['kitchen_supply_id' => $harina->id, 'quantity' => 2],
                ],
            ])
            ->assertSessionHasErrors('items');

        $this->actingAs($this->admin)
            ->put(route('kitchen_inventory.versions.update', $version), [
                'items' => [['kitchen_supply_id' => $harina->id, 'quantity' => 0]],
            ])
            ->assertSessionHasErrors('items.0.quantity');

        $this->assertDatabaseCount('bape_kitchen_recipe_items', 0);
    }

    public function test_publish_requires_items_and_makes_version_immutable(): void
    {
        [, $version] = $this->draftRecipe();

        $this->actingAs($this->admin)
            ->post(route('kitchen_inventory.versions.publish', $version))
            ->assertSessionHasErrors('version');

        $this->actingAs($this->admin)
            ->put(route('kitchen_inventory.versions.update', $version), [
                'items' => [['kitchen_supply_id' => KitchenSupply::query()->firstOrFail()->id, 'quantity' => 1]],
            ]);

        $this->actingAs($this->admin)
            ->post(route('kitchen_inventory.versions.publish', $version))
            ->assertRedirect();

        $version->refresh();
        $this->assertSame('published', $version->status);
        $this->assertNotNull($version->published_at);

        $this->actingAs($this->admin)
            ->put(route('kitchen_inventory.versions.update', $version), [
                'items' => [['kitchen_supply_id' => KitchenSupply::query()->firstOrFail()->id, 'quantity' => 9]],
            ])
            ->assertSessionHasErrors('version');

        $this->assertSame('1.0000', KitchenRecipeItem::query()->firstOrFail()->quantity);
    }

    public function test_publish_is_idempotent_when_already_published(): void
    {
        [, $version] = $this->draftRecipe();
        $this->actingAs($this->admin)
            ->put(route('kitchen_inventory.versions.update', $version), [
                'items' => [['kitchen_supply_id' => KitchenSupply::query()->firstOrFail()->id, 'quantity' => 1]],
            ]);
        $this->actingAs($this->admin)->post(route('kitchen_inventory.versions.publish', $version));

        $this->actingAs($this->admin)
            ->post(route('kitchen_inventory.versions.publish', $version))
            ->assertSessionHas('success');

        $this->assertSame('published', $version->fresh()->status);
    }

    public function test_publish_rejects_version_with_inactive_supply(): void
    {
        [, $version, $harina] = $this->draftRecipe();
        $this->actingAs($this->admin)
            ->put(route('kitchen_inventory.versions.update', $version), [
                'items' => [['kitchen_supply_id' => $harina->id, 'quantity' => 1]],
            ]);

        $harina->update(['is_active' => false]);

        $this->actingAs($this->admin)
            ->post(route('kitchen_inventory.versions.publish', $version))
            ->assertSessionHasErrors('version');

        $this->assertSame('draft', $version->fresh()->status);
    }

    public function test_rapid_sequential_version_creations_get_unique_numbers(): void
    {
        $product = $this->product('Lomo Saltado');
        KitchenRecipe::create(['product_id' => $product->id, 'name' => 'Lomo']);
        $recipe = KitchenRecipe::query()->where('product_id', $product->id)->firstOrFail();

        foreach (range(1, 3) as $expected) {
            $this->actingAs($this->admin)
                ->post(route('kitchen_inventory.recipes.versions.store', $recipe))
                ->assertRedirect();

            $this->assertSame(
                $expected,
                (int) KitchenRecipeVersion::query()->where('recipe_id', $recipe->id)->max('version'),
            );
        }

        $this->assertSame(
            [1, 2, 3],
            KitchenRecipeVersion::query()->where('recipe_id', $recipe->id)->orderBy('version')->pluck('version')->map(fn ($v) => (int) $v)->all(),
        );
    }

    public function test_recipes_index_hides_detail_link_from_kitchen_role(): void
    {
        $product = $this->product('Tacu Tacu');
        $recipe = KitchenRecipe::create(['product_id' => $product->id, 'name' => 'Tacu Tacu']);
        $kitchen = User::factory()->create(['role' => 'kitchen']);

        $this->actingAs($kitchen)
            ->get(route('kitchen_inventory.recipes.index'))
            ->assertOk()
            ->assertDontSee(route('kitchen_inventory.recipes.show', $recipe));

        $this->actingAs($this->admin)
            ->get(route('kitchen_inventory.recipes.index'))
            ->assertOk()
            ->assertSee(route('kitchen_inventory.recipes.show', $recipe));
    }

    public function test_kitchen_role_can_list_recipes_but_not_manage_them(): void
    {
        $kitchen = User::factory()->create(['role' => 'kitchen']);

        $this->actingAs($kitchen)
            ->get(route('kitchen_inventory.recipes.index'))
            ->assertOk();

        $product = $this->product('Encogido');
        $this->actingAs($kitchen)
            ->post(route('kitchen_inventory.recipes.store'), ['product_id' => $product->id, 'name' => 'X'])
            ->assertForbidden();
    }

    public function test_admin_can_delete_draft_version_with_its_items(): void
    {
        [$recipe, $version, $harina] = $this->draftRecipe();

        $this->actingAs($this->admin)
            ->put(route('kitchen_inventory.versions.update', $version), [
                'items' => [['kitchen_supply_id' => $harina->id, 'quantity' => 2]],
            ]);
        $this->assertDatabaseCount('bape_kitchen_recipe_items', 1);

        $this->actingAs($this->admin)
            ->delete(route('kitchen_inventory.versions.destroy', $version))
            ->assertRedirect(route('kitchen_inventory.recipes.show', $recipe));

        $this->assertDatabaseMissing('bape_kitchen_recipe_versions', ['id' => $version->id]);
        $this->assertDatabaseCount('bape_kitchen_recipe_items', 0);
    }

    public function test_admin_can_delete_published_version_without_consumption_history(): void
    {
        [, $version, $harina] = $this->draftRecipe();
        $this->actingAs($this->admin)
            ->put(route('kitchen_inventory.versions.update', $version), [
                'items' => [['kitchen_supply_id' => $harina->id, 'quantity' => 1]],
            ]);
        $this->actingAs($this->admin)->post(route('kitchen_inventory.versions.publish', $version));

        $this->actingAs($this->admin)
            ->delete(route('kitchen_inventory.versions.destroy', $version))
            ->assertRedirect();

        $this->assertDatabaseMissing('bape_kitchen_recipe_versions', ['id' => $version->id]);
    }

    public function test_published_version_with_consumption_history_cannot_be_deleted(): void
    {
        [, $version, $harina] = $this->draftRecipe();
        $this->actingAs($this->admin)
            ->put(route('kitchen_inventory.versions.update', $version), [
                'items' => [['kitchen_supply_id' => $harina->id, 'quantity' => 1]],
            ]);
        $this->actingAs($this->admin)->post(route('kitchen_inventory.versions.publish', $version));

        $operation = KitchenInventoryOperation::create([
            'event_key' => 'evt-'.uniqid(),
            'operation_type' => 'sale',
            'request_hash' => str_repeat('a', 64),
            'status' => 'confirmed',
        ]);
        KitchenOrderConsumption::create([
            'operation_id' => $operation->id,
            'recipe_version_id' => $version->id,
            'kitchen_supply_id' => $harina->id,
            'sold_quantity' => 1,
            'recipe_quantity' => 0.2,
            'theoretical_quantity' => 0.2,
            'consumed_quantity' => 0.2,
            'covered_quantity' => 0.2,
            'shortage_quantity' => 0,
            'idempotency_key' => 'key-'.uniqid(),
        ]);

        $this->actingAs($this->admin)
            ->delete(route('kitchen_inventory.versions.destroy', $version))
            ->assertSessionHasErrors('version');

        $this->assertDatabaseHas('bape_kitchen_recipe_versions', ['id' => $version->id]);
    }

    public function test_kitchen_role_cannot_delete_version(): void
    {
        [, $version] = $this->draftRecipe();
        $kitchen = User::factory()->create(['role' => 'kitchen']);

        $this->actingAs($kitchen)
            ->delete(route('kitchen_inventory.versions.destroy', $version))
            ->assertForbidden();

        $this->assertDatabaseHas('bape_kitchen_recipe_versions', ['id' => $version->id]);
    }

    public function test_draft_edit_shows_item_modal(): void
    {
        [, $version] = $this->draftRecipe();

        $this->actingAs($this->admin)
            ->get(route('kitchen_inventory.versions.edit', $version))
            ->assertOk()
            ->assertSee('itemModal', false)
            ->assertSee('Agregar insumo')
            ->assertSee('Guardar Borrador');
    }

    public function test_recipe_show_exposes_version_items_for_modal(): void
    {
        [$recipe, $version, $harina] = $this->draftRecipe();

        $this->actingAs($this->admin)
            ->put(route('kitchen_inventory.versions.update', $version), [
                'items' => [['kitchen_supply_id' => $harina->id, 'quantity' => 2.5]],
            ]);

        $this->actingAs($this->admin)
            ->get(route('kitchen_inventory.recipes.show', $recipe))
            ->assertOk()
            ->assertSee('versionItemsModal', false)
            ->assertSee('draftEditorModal', false)
            ->assertSee('itemModal', false)
            ->assertDontSee('newVersionModal', false)
            ->assertSee('Ver insumos')
            ->assertSee($harina->name);
    }

    public function test_store_version_redirects_to_recipe_with_open_draft(): void
    {
        $product = $this->product('Jalea');
        $recipe = KitchenRecipe::create(['product_id' => $product->id, 'name' => 'Jalea Mixta']);

        $response = $this->actingAs($this->admin)
            ->post(route('kitchen_inventory.recipes.versions.store', $recipe));

        $version = KitchenRecipeVersion::query()->where('recipe_id', $recipe->id)->firstOrFail();
        $response
            ->assertRedirect(route('kitchen_inventory.recipes.show', ['recipe' => $recipe, 'open_draft' => $version->id]))
            ->assertSessionHas('success');
    }

    public function test_version_data_endpoint_serves_draft_and_rejects_published(): void
    {
        [$recipe, $version, $harina] = $this->draftRecipe();

        $this->actingAs($this->admin)
            ->put(route('kitchen_inventory.versions.update', $version), [
                'items' => [['kitchen_supply_id' => $harina->id, 'quantity' => 2.5]],
            ]);

        $this->actingAs($this->admin)
            ->get(route('kitchen_inventory.versions.data', $version))
            ->assertOk()
            ->assertJsonPath('id', $version->id)
            ->assertJsonPath('version', 1)
            ->assertJsonPath('update_url', route('kitchen_inventory.versions.update', $version))
            ->assertJsonPath('items.0.supply_id', $harina->id)
            ->assertJsonPath('items.0.name', $harina->name);

        $kitchen = User::factory()->create(['role' => 'kitchen']);
        $this->actingAs($kitchen)
            ->getJson(route('kitchen_inventory.versions.data', $version))
            ->assertForbidden();

        $this->actingAs($this->admin)->post(route('kitchen_inventory.versions.publish', $version));

        $this->actingAs($this->admin)
            ->getJson(route('kitchen_inventory.versions.data', $version))
            ->assertStatus(422);
    }

    private function product(string $name): Product
    {
        return Product::create([
            'category_id' => Category::create(['name' => 'Platos '.$name])->id,
            'name' => $name,
            'price' => 20,
            'stock' => null,
            'is_active' => true,
        ]);
    }

    private function draftRecipe(): array
    {
        $product = $this->product('Tallarín');
        $recipe = KitchenRecipe::create(['product_id' => $product->id, 'name' => 'Tallarín Rojo']);
        $version = KitchenRecipeVersion::create(['recipe_id' => $recipe->id, 'version' => 1, 'status' => 'draft']);

        $category = KitchenCategory::create(['name' => 'Insumos '.uniqid()]);
        $harina = KitchenSupply::create(['category_id' => $category->id, 'name' => 'Fideos', 'unit' => 'kg', 'stock_current' => 10]);
        $azucar = KitchenSupply::create(['category_id' => $category->id, 'name' => 'Azúcar', 'unit' => 'kg', 'stock_current' => 10]);

        return [$recipe, $version, $harina, $azucar];
    }
}
