<?php

namespace App\Http\Controllers;

use App\Models\DocumentSeries;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::pluck('value', 'key')->toArray();

        $colorDefaults = [
            'color_primary'        => '#ff8c00',
            'color_primary_hover'  => '#e07b00',
            'color_primary_soft'   => '#fff4e6',
            'color_sidebar_bg'     => '#2d1b5e',
            'color_sidebar_active' => '#ff8c00',
            'brand_text_color'     => '#ffffff',
        ];

        foreach ($colorDefaults as $key => $value) {
            if (!isset($settings[$key])) {
                $settings[$key] = $value;
            }
        }

        return view('settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:30'],
            'company_address' => ['nullable', 'string', 'max:255'],
            'welcome_message' => ['nullable', 'string', 'max:100'],
            'currency_symbol' => ['nullable', 'in:S/,$,€'],
            'ticket_footer' => ['nullable', 'string', 'max:1000'],
            'color_primary' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_primary_hover' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_primary_soft' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_sidebar_bg' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_sidebar_active' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'brand_text_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sunat_ruc' => ['nullable', 'digits:11'],
            'sunat_razon_social' => ['nullable', 'string', 'max:255'],
            'sunat_nombre_comercial' => ['nullable', 'string', 'max:255'],
            'sunat_direccion_fiscal' => ['nullable', 'string', 'max:255'],
            'sunat_ubigeo' => ['nullable', 'digits:6'],
            'sunat_departamento' => ['nullable', 'string', 'max:100'],
            'sunat_provincia' => ['nullable', 'string', 'max:100'],
            'sunat_distrito' => ['nullable', 'string', 'max:100'],
            'sunat_urbanizacion' => ['nullable', 'string', 'max:100'],
            'sunat_environment' => ['nullable', 'in:beta,produccion'],
            'sunat_igv_rate' => ['nullable', 'numeric', 'in:18,10.5,10,4'],
            'nubefact_ruta' => ['nullable', 'url', 'max:500'],
            'nubefact_token' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'company_logo' => ['nullable', 'image', 'max:2048'],
            'serie_factura' => ['nullable', 'string', 'size:4', 'regex:/^F[A-Z0-9]{3}$/i'],
            'serie_boleta' => ['nullable', 'string', 'size:4', 'regex:/^B[A-Z0-9]{3}$/i'],
            'serie_nc_factura' => ['nullable', 'string', 'size:4', 'regex:/^F[A-Z0-9]{3}$/i'],
            'serie_nc_boleta' => ['nullable', 'string', 'size:4', 'regex:/^B[A-Z0-9]{3}$/i'],
        ]);

        $currentEnvironment = Setting::where('key', 'sunat_environment')->value('value') ?? 'beta';
        $requestedEnvironment = $request->input('sunat_environment');
        if ($currentEnvironment === 'produccion' && $requestedEnvironment !== null && $requestedEnvironment !== 'produccion') {
            throw ValidationException::withMessages([
                'sunat_environment' => 'El ambiente PRODUCCIÓN no puede cambiarse nuevamente a BETA desde este módulo.',
            ]);
        }

        $requestedSeries = [
            'factura' => strtoupper(trim((string) $request->input('serie_factura', ''))),
            'boleta' => strtoupper(trim((string) $request->input('serie_boleta', ''))),
            'nota_credito_factura' => strtoupper(trim((string) $request->input('serie_nc_factura', ''))),
            'nota_credito_boleta' => strtoupper(trim((string) $request->input('serie_nc_boleta', ''))),
        ];

        if ($currentEnvironment === 'produccion') {
            foreach ($requestedSeries as $documentType => $serie) {
                if ($serie === '') continue;
                $current = DocumentSeries::where('document_type', $documentType)->first();
                if ($current && strtoupper($current->serie) !== $serie && $current->last_number > 0) {
                    throw ValidationException::withMessages([
                        'sunat_environment' => 'No puedes cambiar la serie productiva de ' . $documentType . ' porque ya tiene comprobantes emitidos.',
                    ]);
                }
            }
        }

        if ($currentEnvironment !== 'produccion' && $requestedEnvironment === 'produccion') {
            foreach ($requestedSeries as $documentType => $serie) {
                if ($serie === '') {
                    throw ValidationException::withMessages([
                        'sunat_environment' => 'Para activar PRODUCCIÓN debes completar las cuatro series de comprobantes.',
                    ]);
                }
                $currentSeries = DocumentSeries::where('document_type', $documentType)->value('serie');
                if ($currentSeries && strtoupper($currentSeries) === $serie) {
                    throw ValidationException::withMessages([
                        'sunat_environment' => 'Para pasar a PRODUCCIÓN debes registrar una serie nueva para ' . $documentType . '. El correlativo productivo iniciará en 1.',
                    ]);
                }
            }
        }

        $allowedSettings = [
            'company_name', 'company_phone', 'company_address', 'welcome_message',
            'currency_symbol', 'ticket_footer', 'color_primary', 'color_primary_hover',
            'color_primary_soft', 'color_sidebar_bg', 'color_sidebar_active', 'brand_text_color',
            'sunat_ruc', 'sunat_razon_social', 'sunat_nombre_comercial',
            'sunat_direccion_fiscal', 'sunat_ubigeo', 'sunat_departamento',
            'sunat_provincia', 'sunat_distrito', 'sunat_urbanizacion',
            'sunat_environment', 'sunat_igv_rate', 'nubefact_ruta',
        ];
        $data = collect($validated)->only($allowedSettings)->all();

        foreach ($data as $key => $value) {
            if ($value === null) continue;
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        if ($request->filled('nubefact_token')) {
            Setting::updateOrCreate(
                ['key' => 'nubefact_token'],
                ['value' => 'enc:' . Crypt::encryptString(trim($request->input('nubefact_token')))]
            );
        }

        if ($request->filled('smtp_password')) {
            Setting::updateOrCreate(
                ['key' => 'smtp_password'],
                ['value' => 'enc:' . Crypt::encryptString(trim($request->input('smtp_password')))]
            );
        }

        if ($request->hasFile('company_logo')) {
            $request->validate(['company_logo' => 'image|max:2048']);
            $oldLogo = Setting::where('key', 'company_logo')->value('value');
            if ($oldLogo) {
                $this->deleteLogoFiles($oldLogo);
            }
            $path = $request->file('company_logo')->store('settings', 'public');
            $this->mirrorLogoToPublic($path);
            Setting::updateOrCreate(['key' => 'company_logo'], ['value' => $path]);
        }

        $series = [
            'factura' => $request->input('serie_factura'),
            'boleta' => $request->input('serie_boleta'),
            'nota_credito_factura' => $request->input('serie_nc_factura'),
            'nota_credito_boleta' => $request->input('serie_nc_boleta'),
        ];
        foreach ($series as $documentType => $serie) {
            if ($serie !== null && trim($serie) !== '') {
                $this->saveSeries($documentType, $serie, 1);
            }
        }

        return redirect()->back()->with('success', 'Configuración actualizada correctamente.');
    }

    /**
     * Guarda serie y correlativo.
     * Si el usuario pide inicio en 5, last_number = 4, así el próximo será 5.
     */
    private function saveSeries(string $documentType, string $serie, int $startNumber): void
    {
        $code = match ($documentType) {
            'factura' => '01',
            'boleta' => '03',
            default => '07',
        };

        $serie = strtoupper(trim($serie));
        $current = DocumentSeries::where('document_type', $documentType)->first();
        $lastNumber = $current && $current->serie === $serie
            ? $current->last_number
            : max(0, $startNumber - 1);

        DocumentSeries::updateOrCreate(
            ['document_type' => $documentType],
            [
                'document_code' => $code,
                'serie' => $serie,
                'last_number' => $lastNumber,
                'is_active' => true,
            ]
        );
    }

    private function deleteLogoFiles(?string $path): void
    {
        if (!$path) return;
        $files = [
            storage_path('app/public/' . $path),
            public_path('storage/' . $path),
        ];
        foreach ($files as $file) {
            if (is_file($file)) @unlink($file);
        }
    }

    private function mirrorLogoToPublic(string $path): void
    {
        $source = storage_path('app/public/' . $path);
        $target = public_path('storage/' . $path);
        if (!is_file($source)) return;
        $dir = dirname($target);
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        if (!file_exists($target)) @copy($source, $target);
    }
}
