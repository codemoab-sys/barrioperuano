<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Table;
use App\Models\User;
use Tests\TestCase;

class UserAreaAccessTest extends TestCase
{
    public function test_admin_can_assign_specific_areas_to_a_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $firstArea = Area::create(['name' => 'Salón']);
        $secondArea = Area::create(['name' => 'Terraza']);

        $response = $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Mozo de salón',
            'email' => 'mozo@example.com',
            'password' => 'password',
            'role' => 'waiter',
            'area_ids' => [$firstArea->id],
        ]);

        $response->assertRedirect()
            ->assertSessionHas('success', 'Usuario registrado correctamente.');

        $user = User::where('email', 'mozo@example.com')->firstOrFail();
        $this->assertFalse($user->all_areas);
        $this->assertTrue($user->canAccessArea($firstArea->id));
        $this->assertFalse($user->canAccessArea($secondArea->id));
    }

    public function test_admin_can_update_user_to_all_areas(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'waiter', 'all_areas' => false]);
        $area = Area::create(['name' => 'Salón']);
        $user->areas()->sync([$area->id]);

        $response = $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'cashier',
            'all_areas' => '1',
        ]);

        $response->assertRedirect()
            ->assertSessionHas('success', 'Datos actualizados.');
        $user->refresh();
        $this->assertTrue($user->hasAllAreasAccess());
        $this->assertCount(0, $user->areas);
    }

    public function test_admin_can_be_restricted_to_assigned_areas(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'all_areas' => false]);
        $area = Area::create(['name' => 'Terraza']);

        $this->assertFalse($admin->canAccessArea($area->id));
        $admin->areas()->sync([$area->id]);
        $this->assertTrue($admin->canAccessArea($area->id));
    }

    public function test_user_cannot_open_a_table_outside_assigned_areas(): void
    {
        $user = User::factory()->create([
            'role' => 'waiter',
            'all_areas' => false,
        ]);
        $assignedArea = Area::create(['name' => 'Salón']);
        $restrictedArea = Area::create(['name' => 'Terraza']);
        $user->areas()->sync([$assignedArea->id]);
        $table = Table::create([
            'area_id' => $restrictedArea->id,
            'name' => 'Mesa restringida',
            'status' => 'available',
        ]);

        $this->actingAs($user)->get(route('pos.order', $table))
            ->assertForbidden();
    }
}