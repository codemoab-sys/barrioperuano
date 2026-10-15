<?php

namespace Tests\Feature;

use App\Exceptions\KitchenIdempotencyConflict;
use App\Models\Category;
use App\Models\Kitchen\KitchenCategory;
use App\Models\Kitchen\KitchenInventoryMovement;
use App\Models\Kitchen\KitchenInventoryOperation;
use App\Models\Kitchen\KitchenOrderConsumption;
use App\Models\Kitchen\KitchenRecipe;
use App\Models\Kitchen\KitchenRecipeItem;
use App\Models\Kitchen\KitchenRecipeVersion;
use App\Models\Kitchen\KitchenShortageRegularization;
use App\Models\Kitchen\KitchenSupply;
use App\Models\Product;
use App\Services\KitchenInventoryService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class KitchenInventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_records_theoretical_consumption_covered_stock_and_shortage_without_negative_stock(): void
    {
        [$supply, $version] = $this->supplyAndVersion(100, 150);

        $operation = app(KitchenInventoryService::class)->recordSale('sale-1', [[
            'supply_id' => $supply->id,
            'recipe_version_id' => $version->id,
            'sold_quantity' => 1,
        ]]);

        $this->assertSame('0.0000', $supply->fresh()->stock_current);
        $this->assertSame('150.0000', $operation->consumptions->first()->consumed_quantity);
        $this->assertSame('100.0000', $operation->consumptions->first()->covered_quantity);
        $this->assertSame('50.0000', $operation->consumptions->first()->shortage_quantity);
        $this->assertSame('150.0000', $operation->consumptions->first()->recipe_quantity);
        $this->assertSame('100.0000', $operation->movements->first()->quantity);
        $this->assertSame('0.0000', $operation->movements->first()->stock_after);
    }

    public function test_sale_derives_quantities_from_stored_recipe_version(): void
    {
        [$supply, $version] = $this->supplyAndVersion(10, 2.5);

        $operation = app(KitchenInventoryService::class)->recordSale('sale-derived', [[
            'supply_id' => $supply->id,
            'recipe_version_id' => $version->id,
            'sold_quantity' => 4,
        ]]);

        $consumption = $operation->consumptions->first();
        $this->assertSame('2.5000', $consumption->recipe_quantity);
        $this->assertSame('10.0000', $consumption->theoretical_quantity);
        $this->assertSame('0.0000', $supply->fresh()->stock_current);
    }

    public function test_sale_rejects_caller_quantities_that_do_not_match_the_stored_recipe(): void
    {
        [$supply, $version] = $this->supplyAndVersion(10, 2);

        try {
            app(KitchenInventoryService::class)->recordSale('sale-tampered', [[
                'supply_id' => $supply->id,
                'recipe_version_id' => $version->id,
                'sold_quantity' => 1,
                'recipe_quantity' => 999,
            ]]);
            $this->fail('Se esperaba ValidationException por cantidad de receta discrepante.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('bape_kitchen_inventory_operations', 0);
            $this->assertDatabaseCount('bape_kitchen_order_consumptions', 0);
            $this->assertSame('10.0000', $supply->fresh()->stock_current);
        }
    }

    public function test_sale_rejects_supply_missing_from_recipe_version(): void
    {
        [$supply, $version] = $this->supplyAndVersion(10);
        $other = KitchenSupply::create(['category_id' => $supply->category_id, 'name' => 'Azúcar', 'unit' => 'kg', 'stock_current' => 5]);

        try {
            app(KitchenInventoryService::class)->recordSale('sale-foreign-supply', [[
                'supply_id' => $other->id,
                'recipe_version_id' => $version->id,
                'sold_quantity' => 1,
            ]]);
            $this->fail('Se esperaba ValidationException por insumo ajeno a la versión de receta.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('bape_kitchen_inventory_operations', 0);
            $this->assertDatabaseCount('bape_kitchen_order_consumptions', 0);
            $this->assertSame('5.0000', $other->fresh()->stock_current);
        }
    }

    public function test_repeated_sale_returns_existing_operation_and_different_payload_is_rejected(): void
    {
        [$supply, $version] = $this->supplyAndVersion(10);
        $service = app(KitchenInventoryService::class);
        $line = [[
            'supply_id' => $supply->id,
            'recipe_version_id' => $version->id,
            'sold_quantity' => 1,
        ]];

        $first = $service->recordSale('sale-repeat', $line);
        $second = $service->recordSale('sale-repeat', $line);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, KitchenInventoryMovement::where('operation_id', $first->id)->count());

        try {
            $service->recordSale('sale-repeat', [array_replace($line[0], ['sold_quantity' => 2])]);
            $this->fail('Se esperaba conflicto por huella distinta con la misma clave.');
        } catch (KitchenIdempotencyConflict) {
            $this->assertSame('8.0000', $supply->fresh()->stock_current);
            $this->assertSame(1, KitchenInventoryMovement::count());
        }
    }

    public function test_reordered_lines_with_same_key_replay_the_same_operation(): void
    {
        [$supply, $version] = $this->supplyAndVersion(10);
        $other = KitchenSupply::create(['category_id' => $supply->category_id, 'name' => 'Sal', 'unit' => 'kg', 'stock_current' => 10]);
        KitchenRecipeItem::create(['recipe_version_id' => $version->id, 'kitchen_supply_id' => $other->id, 'quantity' => 1]);
        $service = app(KitchenInventoryService::class);

        $lineA = ['supply_id' => $supply->id, 'recipe_version_id' => $version->id, 'sold_quantity' => 1];
        $lineB = ['supply_id' => $other->id, 'recipe_version_id' => $version->id, 'sold_quantity' => 2];

        $first = $service->recordSale('sale-order', [$lineA, $lineB]);
        $second = $service->recordSale('sale-order', [$lineB, $lineA]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(2, $first->movements->count());
        $this->assertSame('8.0000', $supply->fresh()->stock_current);
        $this->assertSame('8.0000', $other->fresh()->stock_current);
    }

    public function test_controlled_sale_rejects_insufficient_stock_atomically(): void
    {
        [$first, $version] = $this->supplyAndVersion(10, 2);
        $second = KitchenSupply::create(['category_id' => $first->category_id, 'name' => 'Aceite', 'unit' => 'l', 'stock_current' => 2]);
        KitchenRecipeItem::create(['recipe_version_id' => $version->id, 'kitchen_supply_id' => $second->id, 'quantity' => 3]);

        try {
            app(KitchenInventoryService::class)->recordSale('sale-atomic', [
                ['supply_id' => $first->id, 'recipe_version_id' => $version->id, 'sold_quantity' => 1],
                ['supply_id' => $second->id, 'recipe_version_id' => $version->id, 'sold_quantity' => 1],
            ], true);
            $this->fail('Se esperaba ValidationException por stock insuficiente.');
        } catch (ValidationException) {
            $this->assertSame('10.0000', $first->fresh()->stock_current);
            $this->assertSame('2.0000', $second->fresh()->stock_current);
            $this->assertDatabaseCount('bape_kitchen_inventory_movements', 0);
            $this->assertDatabaseCount('bape_kitchen_order_consumptions', 0);
            $this->assertDatabaseCount('bape_kitchen_inventory_operations', 0);
        }
    }

    public function test_repeated_supply_lines_are_aggregated_before_stock_is_decreased(): void
    {
        [$supply, $version] = $this->supplyAndVersion(5, 3);

        $operation = app(KitchenInventoryService::class)->recordSale('sale-shared-supply', [
            ['supply_id' => $supply->id, 'recipe_version_id' => $version->id, 'sold_quantity' => 1],
            ['supply_id' => $supply->id, 'recipe_version_id' => $version->id, 'sold_quantity' => 1],
        ]);

        $this->assertSame('0.0000', $supply->fresh()->stock_current);
        $this->assertSame(2, $operation->consumptions->count());
        $this->assertSame('5.0000', $operation->movements->first()->quantity);
        $this->assertSame('1.0000', $operation->movements->first()->shortage_quantity);
    }

    public function test_zero_covered_sale_keeps_theoretical_consumption_without_physical_movement(): void
    {
        [$supply, $version] = $this->supplyAndVersion(0, 5);

        $operation = app(KitchenInventoryService::class)->recordSale('sale-zero-cover', [[
            'supply_id' => $supply->id,
            'recipe_version_id' => $version->id,
            'sold_quantity' => 1,
        ]]);

        $this->assertSame('0.0000', $supply->fresh()->stock_current);
        $this->assertCount(0, $operation->movements);
        $this->assertDatabaseCount('bape_kitchen_inventory_movements', 0);

        $consumption = $operation->consumptions->first();
        $this->assertSame('5.0000', $consumption->consumed_quantity);
        $this->assertSame('0.0000', $consumption->covered_quantity);
        $this->assertSame('5.0000', $consumption->shortage_quantity);
    }

    public function test_return_cannot_exceed_physically_covered_quantity_and_is_idempotent(): void
    {
        [$supply, $version] = $this->supplyAndVersion(10, 4);
        $service = app(KitchenInventoryService::class);
        $sale = $service->recordSale('sale-return', [[
            'supply_id' => $supply->id,
            'recipe_version_id' => $version->id,
            'sold_quantity' => 1,
        ]]);
        $consumption = $sale->consumptions->first();

        $first = $service->recordReturn('return-1', [['consumption_id' => $consumption->id, 'quantity' => 2]]);
        $second = $service->recordReturn('return-1', [['consumption_id' => $consumption->id, 'quantity' => 2]]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('2.0000', $consumption->fresh()->returned_quantity);
        $this->assertSame(1, KitchenInventoryMovement::where('operation_id', $first->id)->count());

        try {
            $service->recordReturn('return-2', [['consumption_id' => $consumption->id, 'quantity' => 3]]);
            $this->fail('Se esperaba ValidationException por devolución excesiva.');
        } catch (ValidationException) {
            $this->assertSame('2.0000', $consumption->fresh()->returned_quantity);
            $this->assertSame('8.0000', $supply->fresh()->stock_current);
        }
    }

    public function test_return_merges_repeated_consumption_lines_within_one_request(): void
    {
        [$supply, $version] = $this->supplyAndVersion(10, 4);
        $service = app(KitchenInventoryService::class);
        $sale = $service->recordSale('sale-return-merge', [[
            'supply_id' => $supply->id,
            'recipe_version_id' => $version->id,
            'sold_quantity' => 1,
        ]]);
        $consumption = $sale->consumptions->first();

        $operation = $service->recordReturn('return-merge', [
            ['consumption_id' => $consumption->id, 'quantity' => 1.5],
            ['consumption_id' => $consumption->id, 'quantity' => 2.5],
        ]);

        $this->assertSame(1, $operation->movements->count());
        $this->assertSame('4.0000', $operation->movements->first()->quantity);
        $this->assertSame('4.0000', $consumption->fresh()->returned_quantity);
        $this->assertSame('10.0000', $supply->fresh()->stock_current);
    }

    public function test_return_requires_a_verifiable_original_sale_operation(): void
    {
        [$supply, $version] = $this->supplyAndVersion(10);
        $fake = KitchenInventoryOperation::create([
            'event_key' => 'not-a-sale',
            'operation_type' => 'entry',
            'request_hash' => str_repeat('a', 64),
            'status' => 'confirmed',
        ]);
        $consumption = KitchenOrderConsumption::create([
            'operation_id' => $fake->id,
            'recipe_version_id' => $version->id,
            'kitchen_supply_id' => $supply->id,
            'sold_quantity' => 1,
            'recipe_quantity' => 2,
            'theoretical_quantity' => 2,
            'consumed_quantity' => 2,
            'covered_quantity' => 2,
            'shortage_quantity' => 0,
            'returned_quantity' => 0,
            'idempotency_key' => 'fabricated-consumption',
        ]);

        try {
            app(KitchenInventoryService::class)->recordReturn('return-invalid', [['consumption_id' => $consumption->id, 'quantity' => 1]]);
            $this->fail('Se esperaba ValidationException por referencia no verificable a una venta.');
        } catch (ValidationException) {
            $this->assertSame('10.0000', $supply->fresh()->stock_current);
            $this->assertSame('0.0000', $consumption->fresh()->returned_quantity);
            $this->assertDatabaseCount('bape_kitchen_inventory_movements', 0);
        }
    }

    public function test_return_and_cancellation_keep_distinct_types_for_the_same_consumption(): void
    {
        [$supply, $version] = $this->supplyAndVersion(10, 4);
        $service = app(KitchenInventoryService::class);
        $sale = $service->recordSale('sale-reversal-types', [[
            'supply_id' => $supply->id,
            'recipe_version_id' => $version->id,
            'sold_quantity' => 1,
        ]]);
        $consumption = $sale->consumptions->first();

        $return = $service->recordReturn('reversal-return', [['consumption_id' => $consumption->id, 'quantity' => 1]], 'return');
        $cancellation = $service->recordReturn('reversal-cancellation', [['consumption_id' => $consumption->id, 'quantity' => 1]], 'cancellation');

        $this->assertSame('return', $return->operation_type);
        $this->assertSame('cancellation', $cancellation->operation_type);
        $this->assertSame('return', $return->movements->first()->type);
        $this->assertSame('cancellation', $cancellation->movements->first()->type);
        $this->assertSame($consumption->id, (int) $return->movements->first()->source_id);
        $this->assertSame($consumption->id, (int) $cancellation->movements->first()->source_id);
        $this->assertSame('2.0000', $consumption->fresh()->returned_quantity);
        $this->assertSame('8.0000', $supply->fresh()->stock_current);
    }

    public function test_repeated_return_key_with_different_payload_is_rejected(): void
    {
        [$supply, $version] = $this->supplyAndVersion(10, 4);
        $service = app(KitchenInventoryService::class);
        $sale = $service->recordSale('sale-return-conflict', [[
            'supply_id' => $supply->id,
            'recipe_version_id' => $version->id,
            'sold_quantity' => 1,
        ]]);
        $consumption = $sale->consumptions->first();

        $service->recordReturn('return-conflict', [['consumption_id' => $consumption->id, 'quantity' => 1]]);
        $this->expectException(KitchenIdempotencyConflict::class);
        $service->recordReturn('return-conflict', [['consumption_id' => $consumption->id, 'quantity' => 2]]);
    }

    public function test_regularization_reduces_administrative_pending_balance_without_changing_stock(): void
    {
        [$supply, $version] = $this->supplyAndVersion(0, 5);
        $service = app(KitchenInventoryService::class);
        $sale = $service->recordSale('sale-shortage', [[
            'supply_id' => $supply->id,
            'recipe_version_id' => $version->id,
            'sold_quantity' => 1,
        ]]);
        $consumption = $sale->consumptions->first();
        $before = $supply->fresh()->stock_current;

        $service->regularizeShortage('regularization-1', [['consumption_id' => $consumption->id, 'quantity' => 2]]);

        $this->assertSame($before, $supply->fresh()->stock_current);
        $this->assertSame(2.0, (float) KitchenShortageRegularization::sum('quantity'));
        $this->assertSame(1, KitchenOrderConsumption::whereKey($consumption->id)->count());
        $this->assertDatabaseCount('bape_kitchen_inventory_movements', 0);
    }

    public function test_regularization_rejects_duplicate_lines_within_one_request(): void
    {
        [$supply, $version] = $this->supplyAndVersion(0, 5);
        $service = app(KitchenInventoryService::class);
        $sale = $service->recordSale('sale-shortage-dup', [[
            'supply_id' => $supply->id,
            'recipe_version_id' => $version->id,
            'sold_quantity' => 1,
        ]]);
        $consumption = $sale->consumptions->first();

        try {
            $service->regularizeShortage('regularization-dup', [
                ['consumption_id' => $consumption->id, 'quantity' => 2],
                ['consumption_id' => $consumption->id, 'quantity' => 3],
            ]);
            $this->fail('Se esperaba ValidationException por líneas duplicadas en la regularización.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('bape_kitchen_shortage_regularizations', 0);
        }
    }

    public function test_adjust_stock_treats_equivalent_numeric_formats_as_the_same_request(): void
    {
        [$supply] = $this->supplyAndVersion(10);
        $service = app(KitchenInventoryService::class);

        $first = $service->adjustStock('adjust-1', 'entry', $supply->id, 5);
        $second = $service->adjustStock('adjust-1', 'entry', $supply->id, 5.0);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('15.0000', $supply->fresh()->stock_current);
        $this->assertSame(1, KitchenInventoryMovement::where('operation_id', $first->id)->count());
    }

    public function test_kitchen_inventory_records_cannot_be_deleted(): void
    {
        [$supply, $version] = $this->supplyAndVersion(3, 5);
        $service = app(KitchenInventoryService::class);
        $sale = $service->recordSale('sale-delete-guard', [[
            'supply_id' => $supply->id,
            'recipe_version_id' => $version->id,
            'sold_quantity' => 1,
        ]]);
        $consumption = $sale->consumptions->first();
        $movement = $sale->movements->first();
        $service->regularizeShortage('regularization-delete-guard', [['consumption_id' => $consumption->id, 'quantity' => 1]]);
        $regularization = KitchenShortageRegularization::query()->firstOrFail();

        foreach ([$consumption, $movement, $sale, $regularization] as $record) {
            try {
                $record->delete();
                $this->fail('Se esperaba excepción al eliminar un registro protegido.');
            } catch (RuntimeException) {
            }
        }

        $this->assertDatabaseHas('bape_kitchen_order_consumptions', ['id' => $consumption->id]);
        $this->assertDatabaseHas('bape_kitchen_inventory_movements', ['id' => $movement->id]);
        $this->assertDatabaseHas('bape_kitchen_inventory_operations', ['id' => $sale->id]);
        $this->assertDatabaseHas('bape_kitchen_shortage_regularizations', ['id' => $regularization->id]);
    }

    public function test_schema_enforces_non_negative_stock_on_supported_engines(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Las restricciones CHECK se aplican en MySQL/MariaDB; SQLite no admite ALTER TABLE ADD CONSTRAINT.');
        }

        [$supply] = $this->supplyAndVersion(10);

        try {
            DB::table('bape_kitchen_supplies')->where('id', $supply->id)->update(['stock_current' => -1]);
            $this->fail('Se esperaba violación de la restricción CHECK de stock no negativo.');
        } catch (QueryException) {
            $this->assertSame('10.0000', $supply->fresh()->stock_current);
        }
    }

    public function test_adjust_stock_persists_optional_note_on_movement(): void
    {
        $category = KitchenCategory::create(['name' => 'Ajustes']);
        $supply = KitchenSupply::create(['category_id' => $category->id, 'name' => 'Sal', 'unit' => 'kg', 'stock_current' => 4]);

        $operation = app(KitchenInventoryService::class)->adjustStock('adjust-note-1', 'entry', $supply->id, 6, null, 'Entrada con motivo de auditoría');

        $this->assertSame('10.0000', $supply->fresh()->stock_current);
        $this->assertSame('Entrada con motivo de auditoría', $operation->movements->first()->note);

        $withoutNote = app(KitchenInventoryService::class)->adjustStock('adjust-note-2', 'waste', $supply->id, 1);
        $this->assertNull($withoutNote->movements->first()->note);
    }

    private function supplyAndVersion(float $stock, float $recipeQuantity = 2): array
    {
        $category = KitchenCategory::create(['name' => 'Pruebas']);
        $supply = KitchenSupply::create(['category_id' => $category->id, 'name' => 'Harina', 'unit' => 'kg', 'stock_current' => $stock]);
        $product = Product::create(['category_id' => Category::create(['name' => 'Platos'])->id, 'name' => 'Plato de prueba', 'price' => 10, 'stock' => null, 'is_active' => true]);
        $recipe = KitchenRecipe::create(['product_id' => $product->id, 'name' => 'Receta prueba']);
        $version = KitchenRecipeVersion::create(['recipe_id' => $recipe->id, 'version' => 1, 'status' => 'published']);
        KitchenRecipeItem::create(['recipe_version_id' => $version->id, 'kitchen_supply_id' => $supply->id, 'quantity' => $recipeQuantity]);

        return [$supply, $version];
    }
}
