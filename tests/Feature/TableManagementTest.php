<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Table;
use App\Models\User;
use Tests\TestCase;

class TableManagementTest extends TestCase
{
    public function test_admin_can_update_area_name(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $area = Area::create(['name' => 'Salón antiguo']);

        $response = $this->actingAs($user)->put(route('tables.updateArea', $area), [
            'name' => 'Salón principal',
        ]);

        $response->assertRedirect()
            ->assertSessionHas('success', 'Zona actualizada.');
        $this->assertDatabaseHas('bape_areas', [
            'id' => $area->id,
            'name' => 'Salón principal',
        ]);
    }

    public function test_admin_can_update_table_name(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $area = Area::create(['name' => 'Salón']);
        $table = Table::create([
            'area_id' => $area->id,
            'name' => 'Mesa antigua',
            'status' => 'available',
        ]);

        $response = $this->actingAs($user)->put(route('tables.updateTable', $table), [
            'name' => 'Mesa VIP',
        ]);

        $response->assertRedirect()
            ->assertSessionHas('success', 'Mesa actualizada.');
        $this->assertDatabaseHas('bape_tables', [
            'id' => $table->id,
            'name' => 'Mesa VIP',
        ]);
    }

    public function test_admin_can_delete_an_empty_area(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $area = Area::create(['name' => 'Zona vacía']);

        $response = $this->actingAs($user)->delete(route('tables.destroyArea', $area));

        $response->assertRedirect()
            ->assertSessionHas('success', 'Zona eliminada.');
        $this->assertDatabaseMissing('bape_areas', ['id' => $area->id]);
    }

    public function test_admin_cannot_delete_an_area_with_tables(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $area = Area::create(['name' => 'Zona con mesa']);
        Table::create([
            'area_id' => $area->id,
            'name' => 'Mesa 1',
            'status' => 'available',
        ]);

        $response = $this->actingAs($user)->delete(route('tables.destroyArea', $area));

        $response->assertRedirect()
            ->assertSessionHas('error', 'No puedes eliminar una zona que tiene mesas.');
        $this->assertDatabaseHas('bape_areas', ['id' => $area->id]);
    }
}