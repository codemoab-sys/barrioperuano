<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            // Datos básicos
            'company_name' => 'Mi Restaurante VIP',
            'company_address' => 'Av. Gastronómica 123, Lima',
            'company_phone' => '(01) 555-9999',
            'ticket_footer' => '¡Gracias por su preferencia! Vuelva pronto.',
            'welcome_message' => 'Bienvenido de vuelta',
            'currency_symbol' => 'S/',

            // ── SUNAT / NubeFact ──────────────────────────────────────
            'sunat_ruc' => '20000000001',
            'sunat_razon_social' => 'EMPRESA DEMO SAC',
            'sunat_nombre_comercial' => 'DEMO',
            'sunat_direccion_fiscal' => 'AV. PRINCIPAL 123',
            'sunat_ubigeo' => '150101',          // Lima/Lima/Lima
            'sunat_departamento' => 'LIMA',
            'sunat_provincia' => 'LIMA',
            'sunat_distrito' => 'LIMA',
            'sunat_urbanizacion' => '-',
            'sunat_codigo_pais' => 'PE',
            'sunat_igv_rate' => '10.5',          // %
            'sunat_environment' => 'beta',       // beta | produccion

            // ── NubeFact (credenciales) ───────────────────────────────
            'nubefact_ruta' => '',               // RUTA de la API NubeFact
            'nubefact_token' => '',              // TOKEN de autenticación

            // ── SMTP (envío de comprobantes) ─────────────────────────
            'smtp_password' => '',               // Contraseña correo barrioperuano@moabcode.com
        ];

        foreach ($data as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
