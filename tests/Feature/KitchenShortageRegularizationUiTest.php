<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Kitchen\KitchenCategory;
use App\Models\Kitchen\KitchenOrderConsumption;
use App\Models\Kitchen\KitchenRecipe;
use App\Models\Kitchen\KitchenRecipeItem;
use App\Models\Kitchen\KitchenRecipeVersion;
use App\Models\Kitchen\KitchenShortageRegularization;
use App\Models\Kitchen\KitchenSupply;
use App\Models\Product;
use App\Models\User;
use App\Services\KitchenInventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class KitchenShortageRegularizationUiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_pending_shortages_are_listed_and_regularized_with_note(): void
    {
        $consumption = $this->consumptionWithShortage();

        $this->actingAs($this->admin)
            ->get(route('kitchen_inventory.regularizations.index'))
            ->assertOk()
            ->assertSee('Harina')
            ->assertSee('>50<', false);

        $this->actingAs($this->admin)
            ->post(route('kitchen_inventory.regularizations.store'), [
                'form_uuid' => (string) Str::uuid(),
                'consumption_id' => $consumption->id,
                'quantity' => 20,
                'note' => 'Diferencia verificada en conteo físico',
            ])
            ->assertRedirect(route('kitchen_inventory.regularizations.index'));

        $regularization = KitchenShortageRegularization::query()->sole();
        $this->assertSame('20.0000', $regularization->quantity);
        $this->assertSame('Diferencia verificada en conteo físico', $regularization->note);
        $this->assertSame($this->admin->id, $regularization->user_id);

        $this->actingAs($this->admin)
            ->get(route('kitchen_inventory.regularizations.index'))
            ->assertOk()
            ->assertSee('>30<', false);
    }

    public function test_regularization_cannot_exceed_pending_shortage(): void
    {
        $consumption = $this->consumptionWithShortage();

        $this->actingAs($this->admin)
            ->from(route('kitchen_inventory.regularizations.index'))
            ->post(route('kitchen_inventory.regularizations.store'), [
                'form_uuid' => (string) Str::uuid(),
                'consumption_id' => $consumption->id,
                'quantity' => 60,
                'note' => 'Regularización mayor al faltante',
            ])
            ->assertSessionHasErrors('quantity');

        $this->assertDatabaseCount('bape_kitchen_shortage_regularizations', 0);
    }

    public function test_note_is_required(): void
    {
        $consumption = $this->consumptionWithShortage();

        $this->actingAs($this->admin)
            ->post(route('kitchen_inventory.regularizations.store'), [
                'form_uuid' => (string) Str::uuid(),
                'consumption_id' => $consumption->id,
                'quantity' => 10,
                'note' => 'x',
            ])
            ->assertSessionHasErrors('note');

        $this->assertDatabaseCount('bape_kitchen_shortage_regularizations', 0);
    }

    public function test_fully_regularized_consumption_disappears_from_pending_list(): void
    {
        $consumption = $this->consumptionWithShortage();

        $this->actingAs($this->admin)->post(route('kitchen_inventory.regularizations.store'), [
            'form_uuid' => (string) Str::uuid(),
            'consumption_id' => $consumption->id,
            'quantity' => 50,
            'note' => 'Regularización total del faltante',
        ]);

        $this->actingAs($this->admin)
            ->get(route('kitchen_inventory.regularizations.index'))
            ->assertOk()
            ->assertDontSee('Harina');
    }

    public function test_double_submission_with_same_uuid_registers_one_regularization(): void
    {
        $consumption = $this->consumptionWithShortage();
        $uuid = (string) Str::uuid();
        $payload = [
            'form_uuid' => $uuid,
            'consumption_id' => $consumption->id,
            'quantity' => 10,
            'note' => 'Doble envío del formulario',
        ];

        $this->actingAs($this->admin)->post(route('kitchen_inventory.regularizations.store'), $payload);
        $this->actingAs($this->admin)->post(route('kitchen_inventory.regularizations.store'), $payload);

        $this->assertSame(1, KitchenShortageRegularization::query()->count());
        $this->assertSame('10.0000', KitchenShortageRegularization::query()->sole()->quantity);
    }

    public function test_kitchen_role_cannot_regularize(): void
    {
        $kitchen = User::factory()->create(['role' => 'kitchen']);

        $this->actingAs($kitchen)
            ->get(route('kitchen_inventory.regularizations.index'))
            ->assertForbidden();

        $this->actingAs($kitchen)
            ->post(route('kitchen_inventory.regularizations.store'), [
                'form_uuid' => (string) Str::uuid(),
                'consumption_id' => 1,
                'quantity' => 1,
                'note' => 'Sin autorización',
            ])
            ->assertForbidden();
    }

    private function consumptionWithShortage(): KitchenOrderConsumption
    {
        $category = KitchenCategory::create(['name' => 'Insumos']);
        $supply = KitchenSupply::create(['category_id' => $category->id, 'name' => 'Harina', 'unit' => 'kg', 'stock_current' => 100]);
        $product = Product::create(['category_id' => Category::create(['name' => 'Platos'])->id, 'name' => 'Pan', 'price' => 5, 'stock' => null, 'is_active' => true]);
        $recipe = KitchenRecipe::create(['product_id' => $product->id, 'name' => 'Pan']);
        $version = KitchenRecipeVersion::create(['recipe_id' => $recipe->id, 'version' => 1, 'status' => 'published']);
        KitchenRecipeItem::create(['recipe_version_id' => $version->id, 'kitchen_supply_id' => $supply->id, 'quantity' => 150]);

        $operation = app(KitchenInventoryService::class)->recordSale('reg-ui-shortage', [[
            'supply_id' => $supply->id,
            'recipe_version_id' => $version->id,
            'sold_quantity' => 1,
        ]]);

        return $operation->consumptions->first();
    }
}
