<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebugRoutesSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_debug_routes_are_removed_and_return_not_found(): void
    {
        $this->get('/debug/ultima-factura-json')->assertNotFound();
        $this->get('/debug/todas-facturas-json')->assertNotFound();

        $cashier = User::factory()->create(['role' => 'cashier']);
        $this->actingAs($cashier)
            ->get('/debug/todas-facturas-json')
            ->assertNotFound();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)
            ->post('/debug/sync-ultima-factura-status')
            ->assertNotFound();
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
