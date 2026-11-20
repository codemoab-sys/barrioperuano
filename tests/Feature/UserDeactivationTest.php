<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class UserDeactivationTest extends TestCase
{
    public function test_admin_can_deactivate_and_reactivate_a_user_without_deleting_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'waiter']);

        $this->actingAs($admin)
            ->patch(route('users.toggleStatus', $user))
            ->assertRedirect()
            ->assertSessionHas('success', 'Usuario desactivado correctamente.');

        $this->assertDatabaseHas('bape_users', [
            'id' => $user->id,
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->patch(route('users.toggleStatus', $user))
            ->assertRedirect()
            ->assertSessionHas('success', 'Usuario activado correctamente.');

        $this->assertDatabaseHas('bape_users', [
            'id' => $user->id,
            'is_active' => true,
        ]);
    }

    public function test_deactivated_user_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive@example.com',
            'is_active' => false,
        ]);

        $this->from(route('login'))
            ->post(route('login.perform'), [
                'email' => $user->email,
                'password' => 'password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_deactivated_user_session_is_logged_out_on_next_request(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->actingAs($user)
            ->get(route('pos.index'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_cannot_deactivate_their_own_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->patch(route('users.toggleStatus', $admin))
            ->assertRedirect()
            ->assertSessionHas('error', 'No puedes desactivar tu propia cuenta mientras estás conectado.');

        $this->assertTrue($admin->fresh()->is_active);
    }
}
