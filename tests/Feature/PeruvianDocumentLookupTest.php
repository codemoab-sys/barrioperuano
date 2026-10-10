<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PeruvianDocumentLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_document_search_uses_the_shared_api_service(): void
    {
        config([
            'services.apis_net_pe.url' => 'https://api.apis.net.pe/v1',
            'services.apis_net_pe.token' => 'test-token',
        ]);
        Http::fake([
            'https://api.apis.net.pe/v1/dni*' => Http::response([
                'nombre' => 'Cliente de prueba',
                'direccion' => 'Av. Principal 123',
                'provincia' => 'Lima',
                'departamento' => 'Lima',
            ], 200),
        ]);

        $response = $this->actingAs(User::factory()->create(['role' => 'waiter']))
            ->getJson(route('clients.searchDocument', ['document' => '12345678']));

        $response->assertOk()->assertJson([
            'success' => true,
            'document_type' => 'DNI',
            'name' => 'Cliente de prueba',
            'address' => 'Av. Principal 123, Lima, Lima',
        ]);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-token'));
    }

    public function test_invalid_document_does_not_call_external_api(): void
    {
        Http::fake();

        $response = $this->actingAs(User::factory()->create(['role' => 'waiter']))
            ->getJson(route('clients.searchDocument', ['document' => '123']));

        $response->assertStatus(422);
        Http::assertNothingSent();
    }
}