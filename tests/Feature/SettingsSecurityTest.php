<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Area;
use App\Models\Order;
use App\Models\Table;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsSecurityTest extends TestCase
{
    public function test_settings_show_combined_monthly_electronic_document_quota_and_overage_estimate(): void
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
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee($monthLabel . ': 251/250 boletas y facturas.')
            ->assertSee('Excedente: 1 documento(s)')
            ->assertSee('adicional estimado: S/ 0.50')
            ->assertSee('este sistema no realiza el cobro automáticamente');
    }

    public function test_production_mode_requires_all_series_before_switching(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Setting::updateOrCreate(['key' => 'sunat_environment'], ['value' => 'beta']);

        Setting::updateOrCreate(['key' => 'company_name'], ['value' => 'Restaurante antes']);

        $response = $this->actingAs($admin)
            ->from(route('settings.index'))
            ->post(route('settings.update'), [
                'company_name' => 'Nuevo restaurante',
                'sunat_environment' => 'produccion',
                'serie_factura' => '',
                'serie_boleta' => '',
                'serie_nc_factura' => '',
                'serie_nc_boleta' => '',
            ]);

        $response->assertRedirect(route('settings.index'));
        $response->assertSessionHasErrors(['sunat_environment']);
        $this->assertDatabaseHas('bape_settings', ['key' => 'company_name', 'value' => 'Restaurante antes']);
    }

    public function test_logo_upload_rejects_invalid_file_type(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Storage::fake('public');

        $file = UploadedFile::fake()->create('documento.txt', 10, 'text/plain');

        $response = $this->actingAs($admin)
            ->from(route('settings.index'))
            ->post(route('settings.update'), [
                'company_name' => 'Restaurante validado',
                'company_logo' => $file,
            ]);

        $response->assertRedirect(route('settings.index'));
        $response->assertSessionHasErrors(['company_logo']);
    }
}
