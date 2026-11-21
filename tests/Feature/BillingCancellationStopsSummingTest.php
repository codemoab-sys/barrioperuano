<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Order;
use App\Models\Table;
use App\Models\User;
use App\Services\Sunat\NubeFactService;
use Tests\TestCase;

class BillingCancellationStopsSummingTest extends TestCase
{
    private User $cashier;

    private Table $table;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->cashier = User::factory()->create(['role' => 'cashier']);

        $area = Area::create(['name' => 'Salón']);
        $this->table = Table::create(['area_id' => $area->id, 'name' => 'Mesa 1']);
    }

    private function acceptedBoleta(array $attributes = []): Order
    {
        return Order::create(array_merge([
            'table_id' => $this->table->id,
            'user_id' => $this->cashier->id,
            'status' => 'completed',
            'total' => 50,
            'payment_method' => 'cash',
            'document_type' => 'Boleta',
            'client_name' => 'Cliente Varios',
            'sunat_status' => 'ACCEPTED',
            'serie' => 'B001',
            'correlativo' => 1,
            'issued_at' => now(),
        ], $attributes));
    }

    public function test_accepted_sunnat_annulment_marks_order_cancelled(): void
    {
        $order = $this->acceptedBoleta();

        $this->app->instance(NubeFactService::class, new class
        {
            public function voidDocument(string $tipoComprobante, string $serie, int $numero, string $motivo): array
            {
                return [
                    'aceptada_por_sunat' => true,
                    'sunat_ticket_numero' => 'TICKET-1',
                    'sunat_description' => 'Anulación aceptada',
                ];
            }

            public function queryCancellation(string $tipoComprobante, string $serie, int $numero): array
            {
                return ['aceptada_por_sunat' => true];
            }
        });

        $response = $this->actingAs($this->cashier)->post(route('billing.cancel', $order), [
            'motivo' => 'ERROR DE SISTEMA',
        ]);

        $response->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('VOIDED', $order->sunat_status);
        $this->assertSame('ACCEPTED', $order->anulacion_status);
    }

    public function test_voided_boleta_does_not_sum_in_sales_history_totals(): void
    {
        $this->acceptedBoleta();
        $this->acceptedBoleta([
            'status' => 'cancelled',
            'sunat_status' => 'VOIDED',
            'anulacion_status' => 'ACCEPTED',
            'total' => 999,
            'serie' => 'B001',
            'correlativo' => 2,
        ]);

        $response = $this->actingAs($this->cashier)->get(route('sales.index'));

        $response->assertOk();
        $response->assertViewHas('totalSales', fn ($total) => (float) $total === 50.0);
        $response->assertViewHas('totalCash', fn ($total) => (float) $total === 50.0);
    }
}
