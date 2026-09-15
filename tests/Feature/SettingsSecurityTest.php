<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsSecurityTest extends TestCase
{
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
