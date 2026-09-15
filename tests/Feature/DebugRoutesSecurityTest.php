<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class DebugRoutesSecurityTest extends TestCase
{
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
}
