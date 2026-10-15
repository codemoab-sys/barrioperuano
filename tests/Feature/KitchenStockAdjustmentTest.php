<?php

namespace Tests\Feature;

use App\Exceptions\KitchenIdempotencyConflict;
use App\Models\Kitchen\KitchenCategory;
use App\Models\Kitchen\KitchenInventoryMovement;
use App\Models\Kitchen\KitchenInventoryOperation;
use App\Models\Kitchen\KitchenSupply;
use App\Models\User;
use App\Services\KitchenInventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class KitchenStockAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private KitchenSupply $supply;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $category = KitchenCategory::create(['name' => 'Almacén']);
        $this->supply = KitchenSupply::create(['category_id' => $category->id, 'name' => 'Harina', 'unit' => 'kg', 'stock_current' => 0]);
    }

    public function test_entry_increases_stock_and_records_note_and_user_in_kardex(): void
    {
        $this->actingAs($this->admin)
            ->post(route('kitchen_inventory.supplies.adjust', $this->supply), [
                'form_uuid' => (string) Str::uuid(),
                'type' => 'entry',
                'quantity' => 10,
                'note' => 'Stock inicial registrado en almacén',
            ])
            ->assertRedirect(route('kitchen_inventory.supplies.index'));

        $this->assertSame('10.0000', $this->supply->fresh()->stock_current);

        $movement = KitchenInventoryMovement::query()->sole();
        $this->assertSame('entry', $movement->type);
        $this->assertSame('Stock inicial registrado en almacén', $movement->note);
        $this->assertSame($this->admin->id, $movement->user_id);
        $this->assertSame('ui-adjust', explode(':', $movement->operation->event_key)[0]);
    }

    public function test_adjustment_out_and_waste_decrease_stock(): void
    {
        $this->supply->update(['stock_current' => 8]);

        $this->actingAs($this->admin)
            ->post(route('kitchen_inventory.supplies.adjust', $this->supply), [
                'form_uuid' => (string) Str::uuid(),
                'type' => 'adjustment_out',
                'quantity' => 3,
                'note' => 'Corrección por conteo físico',
            ]);

        $this->actingAs($this->admin)
            ->post(route('kitchen_inventory.supplies.adjust', $this->supply), [
                'form_uuid' => (string) Str::uuid(),
                'type' => 'waste',
                'quantity' => 1.5,
                'note' => 'Merma por vencimiento de lote',
            ]);

        $this->assertSame('3.5000', $this->supply->fresh()->stock_current);
        $this->assertSame(['adjustment_out', 'waste'], KitchenInventoryMovement::query()->orderBy('id')->pluck('type')->all());
    }

    public function test_adjustment_out_cannot_drop_stock_below_zero(): void
    {
        $this->supply->update(['stock_current' => 2]);

        $this->actingAs($this->admin)
            ->from(route('kitchen_inventory.supplies.adjust', $this->supply))
            ->post(route('kitchen_inventory.supplies.adjust', $this->supply), [
                'form_uuid' => (string) Str::uuid(),
                'type' => 'adjustment_out',
                'quantity' => 5,
                'note' => 'Intento de salida mayor al stock',
            ])
            ->assertSessionHasErrors('quantity');

        $this->assertSame('2.0000', $this->supply->fresh()->stock_current);
        $this->assertDatabaseCount('bape_kitchen_inventory_movements', 0);
    }

    public function test_invalid_type_missing_note_or_zero_quantity_are_rejected(): void
    {
        $base = ['form_uuid' => (string) Str::uuid(), 'quantity' => 1, 'note' => 'Motivo válido para prueba'];

        $this->actingAs($this->admin)
            ->post(route('kitchen_inventory.supplies.adjust', $this->supply), array_merge($base, ['type' => 'sale']))
            ->assertSessionHasErrors('type');

        $this->actingAs($this->admin)
            ->post(route('kitchen_inventory.supplies.adjust', $this->supply), array_merge($base, ['type' => 'entry', 'note' => 'abc']))
            ->assertSessionHasErrors('note');

        $this->actingAs($this->admin)
            ->post(route('kitchen_inventory.supplies.adjust', $this->supply), array_merge($base, ['type' => 'entry', 'quantity' => 0]))
            ->assertSessionHasErrors('quantity');

        $this->assertDatabaseCount('bape_kitchen_inventory_movements', 0);
    }

    public function test_identical_resubmission_is_idempotent(): void
    {
        $uuid = (string) Str::uuid();
        $payload = ['form_uuid' => $uuid, 'type' => 'entry', 'quantity' => 5, 'note' => 'Doble envío del mismo formulario'];

        $this->actingAs($this->admin)->post(route('kitchen_inventory.supplies.adjust', $this->supply), $payload);
        $this->actingAs($this->admin)->post(route('kitchen_inventory.supplies.adjust', $this->supply), $payload);

        $this->assertSame('5.0000', $this->supply->fresh()->stock_current);
        $this->assertSame(1, KitchenInventoryOperation::query()->count());
        $this->assertSame(1, KitchenInventoryMovement::query()->count());
    }

    public function test_resubmission_with_edited_data_is_rejected_without_stock_change(): void
    {
        $uuid = (string) Str::uuid();
        $this->actingAs($this->admin)->post(route('kitchen_inventory.supplies.adjust', $this->supply), [
            'form_uuid' => $uuid,
            'type' => 'entry',
            'quantity' => 5,
            'note' => 'Primera versión del formulario',
        ]);

        $this->actingAs($this->admin)
            ->post(route('kitchen_inventory.supplies.adjust', $this->supply), [
                'form_uuid' => $uuid,
                'type' => 'entry',
                'quantity' => 7,
                'note' => 'Segunda versión del formulario',
            ])
            ->assertSessionHasErrors('form_uuid');

        $this->assertSame('5.0000', $this->supply->fresh()->stock_current);
        $this->assertSame(1, KitchenInventoryOperation::query()->count());
    }

    public function test_note_is_included_in_service_payload_for_conflict_detection(): void
    {
        $service = app(KitchenInventoryService::class);
        $service->adjustStock('svc-note-1', 'entry', $this->supply->id, 2, $this->admin->id, 'Nota inicial del servicio');

        try {
            $service->adjustStock('svc-note-1', 'entry', $this->supply->id, 2, $this->admin->id, 'Nota diferente');
            $this->fail('Se esperaba conflicto por misma clave con motivo distinto.');
        } catch (KitchenIdempotencyConflict) {
        }

        $this->assertSame('2.0000', $this->supply->fresh()->stock_current);
    }

    public function test_adjust_form_is_reachable_via_get_for_admin(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('kitchen_inventory.supplies.adjust.form', $this->supply));

        $response->assertOk()
            ->assertSee('Ajustar Stock')
            ->assertSee(route('kitchen_inventory.supplies.adjust', $this->supply), false);

        $this->assertSame(0, KitchenInventoryOperation::query()->count());
    }
}
