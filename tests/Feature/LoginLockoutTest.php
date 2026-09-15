<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class LoginLockoutTest extends TestCase
{
    public function test_expired_lockout_is_cleared_and_user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'locked@example.com',
            'role' => 'cashier',
            'locked_at' => now()->subMinutes(16),
            'failed_login_attempts' => 5,
        ]);

        $response = $this->post(route('login.perform'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('pos.index'));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('bape_users', [
            'id' => $user->id,
            'failed_login_attempts' => 0,
            'locked_at' => null,
        ]);
    }

    public function test_active_lockout_still_blocks_login(): void
    {
        $user = User::factory()->create([
            'email' => 'active-lock@example.com',
            'locked_at' => now()->subMinutes(5),
            'failed_login_attempts' => 5,
        ]);

        $response = $this->from(route('login'))
            ->post(route('login.perform'), [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}