<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebugRoutesSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_debug_routes_require_an_admin_and_mutations_use_post(): void
    {
        $this->get(route('debug.last.invoice.json'))
            ->assertRedirect(route('login'));

        $cashier = User::factory()->create(['role' => 'cashier']);
        $this->actingAs($cashier)
            ->get(route('debug.all.invoice.json'))
            ->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)
            ->get(route('debug.sync.last.invoice.status'))
            ->assertMethodNotAllowed();

        $this->actingAs($admin)
            ->get(route('debug.manual.send.ultima.factura'))
            ->assertMethodNotAllowed();
    }

    public function test_cashier_roles_cannot_access_or_see_the_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'cashier']);

        foreach (['cashier', 'cashier_waiter'] as $role) {
            $user->role = $role;

            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertForbidden();

            $this->get(route('clients.index'))
                ->assertOk()
                ->assertDontSee(route('dashboard'))
                ->assertSee(route('pos.index'));

            $this->get(route('cash_registers.create'))
                ->assertOk()
                ->assertDontSee(route('dashboard'));
        }
    }
}
