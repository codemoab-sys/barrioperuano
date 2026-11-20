<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ForcedPasswordChangeTest extends TestCase
{
    public function test_login_requires_password_change_before_accessing_the_app(): void
    {
        $user = User::factory()->create([
            'email' => 'change-password@example.com',
            'password_change_required' => true,
        ]);

        $this->post(route('login.perform'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('password.force-change'));

        $this->assertAuthenticatedAs($user);

        $this->get(route('pos.index'))
            ->assertRedirect(route('password.force-change'));

        $this->get(route('password.force-change'))
            ->assertOk()
            ->assertSee('Repite la contraseña');
    }

    public function test_user_can_set_and_confirm_a_password_of_at_least_six_characters(): void
    {
        $user = User::factory()->create([
            'password_change_required' => true,
        ]);

        $this->actingAs($user)
            ->post(route('password.force-change.update'), [
                'password' => 'nueva123',
                'password_confirmation' => 'nueva123',
            ])
            ->assertRedirect(route('pos.index'))
            ->assertSessionHas('success', 'Tu contraseña fue actualizada correctamente.');

        $user->refresh();
        $this->assertFalse($user->password_change_required);
        $this->assertTrue(Hash::check('nueva123', $user->password));
    }

    public function test_password_change_requires_minimum_length_and_confirmation(): void
    {
        $user = User::factory()->create([
            'password_change_required' => true,
        ]);

        $this->actingAs($user)
            ->from(route('password.force-change'))
            ->post(route('password.force-change.update'), [
                'password' => 'abc12',
                'password_confirmation' => 'different',
            ])
            ->assertRedirect(route('password.force-change'))
            ->assertSessionHasErrors('password');

        $this->assertTrue($user->fresh()->password_change_required);
    }
}
