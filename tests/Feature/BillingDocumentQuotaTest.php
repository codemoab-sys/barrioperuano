<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Order;
use App\Models\Table;
use App\Models\User;
use Tests\TestCase;

class BillingDocumentQuotaTest extends TestCase
{
    public function test_billing_module_displays_monthly_boleta_and_factura_quota_and_overage_estimate(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $area = Area::create(['name' => 'Salón']);
        $table = Table::create(['area_id' => $area->id, 'name' => 'Mesa 1']);
        $issuedAt = now()->startOfMonth()->addDays(2);
        $monthLabel = ucfirst(now()->locale('es')->translatedFormat('F Y'));
        $documents = [];

        for ($index = 0; $index < 251; $index++) {
            $documents[] = [
                'table_id' => $table->id,
                'user_id' => $admin->id,
                'status' => $index === 250 ? 'cancelled' : 'completed',
                'document_type' => $index % 2 === 0 ? 'Boleta' : 'Factura',
                'total' => 10,
                'sunat_status' => $index === 249 ? 'REJECTED' : 'ACCEPTED',
                'issued_at' => $issuedAt,
                'created_at' => $issuedAt,
                'updated_at' => $issuedAt,
            ];
        }

        Order::insert($documents);
        Order::create([
            'table_id' => $table->id,
            'user_id' => $admin->id,
            'status' => 'completed',
            'document_type' => 'Ticket',
            'total' => 10,
            'created_at' => $issuedAt,
        ]);
        Order::create([
            'table_id' => $table->id,
            'user_id' => $admin->id,
            'status' => 'completed',
            'document_type' => 'Factura',
            'total' => 10,
            'issued_at' => now()->startOfMonth()->subSecond(),
        ]);

        $this->actingAs($admin)
            ->get(route('billing.index'))
            ->assertOk()
            ->assertSee($monthLabel . ': 251/250 boletas y facturas.')
            ->assertSee('Excedente: 1 documento(s)')
            ->assertSee('adicional estimado: S/ 0.50');
    }
}
