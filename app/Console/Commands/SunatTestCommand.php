<?php

namespace App\Console\Commands;

use App\Models\DocumentSeries;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\User;
use App\Models\Table;
use App\Models\Client;
use App\Services\Sunat\SunatConfig;
use App\Services\Sunat\SunatService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Emite un comprobante de prueba contra SUNAT BETA y reporta el resultado.
 *
 * Uso:
 *   php artisan sunat:test                 # Boleta con productos del catálogo
 *   php artisan sunat:test --factura       # Factura (RUC de prueba 20000000001)
 *   php artisan sunat:test --order=15      # Reenvía una orden existente (no la duplica)
 *   php artisan sunat:test --no-create     # No crea, solo muestra config y aborta
 */
class SunatTestCommand extends Command
{
    protected $signature = 'sunat:test
                            {--factura : Emitir Factura en lugar de Boleta}
                            {--order= : ID de una orden existente a reenviar}
                            {--no-create : Solo mostrar la configuración SUNAT y abortar}';

    protected $description = 'Emite un comprobante de prueba a NubeFact';

    public function handle(): int
    {
        $this->info('═══════════════════════════════════════════════════════');
        $this->info('  PRUEBA DE FACTURACIÓN ELECTRÓNICA - SUNAT');
        $this->info('═══════════════════════════════════════════════════════');

        // 1. Mostrar configuración actual
        $config = new SunatConfig();
        $this->table(
            ['Parámetro', 'Valor'],
            [
                ['Ambiente',       $config->isProduction() ? 'PRODUCCIÓN' : 'BETA'],
                ['RUC',            $config->ruc()],
                ['Razón social',   $config->razonSocial()],
                ['NubeFact RUTA',  $config->nubefactRuta() ?: '(NO CONFIGURADO)'],
                ['NubeFact TOKEN', $config->nubefactToken() ? '••••••••' : '(NO CONFIGURADO)'],
                ['IGV',            $config->igvRate() . '%'],
            ]
        );

        if (!$config->isNubefactConfigured()) {
            $this->error('NubeFact no está configurado. Ingrese RUTA y TOKEN en Configuración.');
            return self::FAILURE;
        }

        if ($this->option('no-create')) {
            $this->warn('--no-create activo: abortando antes de emitir.');
            return self::SUCCESS;
        }

        if ($config->isProduction()) {
            if (!$this->confirm('⚠️  Estás en PRODUCCIÓN. ¿Continuar?', false)) {
                return self::FAILURE;
            }
        }

        // 2. Resolver la orden de prueba
        try {
            $order = $this->option('order')
                ? $this->reuseOrder((int) $this->option('order'))
                : $this->createTestOrder($this->option('factura'));
        } catch (\Throwable $e) {
            $this->error('No se pudo preparar la orden: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Orden #{$order->id} — {$order->document_type} {$order->full_number}");
        $this->line("  Cliente:   {$order->client_name} ({$order->client_document})");
        $this->line("  Total:     S/ " . number_format($order->total, 2));
        $this->line("  IGV:       S/ " . number_format($order->igv, 2));
        $this->newLine();

        // 3. Enviar a NubeFact
        $this->info('→ Enviando JSON a NubeFact...');
        $service = new SunatService($config);

        try {
            $service->sendInvoice($order->fresh('details.product'));
            $order->refresh();
        } catch (\Throwable $e) {
            $this->error('Excepción durante el envío: ' . $e->getMessage());
            $this->line($e->getTraceAsString());
            return self::FAILURE;
        }

        // 4. Reportar resultado
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════');
        $this->info('  RESULTADO');
        $this->info('═══════════════════════════════════════════════════════');

        $color = match ($order->sunat_status) {
            'ACCEPTED' => 'info',
            'OBSERVED' => 'comment',
            default    => 'error',
        };

        $this->{$color}("  Estado:       {$order->sunat_status}");
        $this->line("  Código:       " . ($order->sunat_code ?? '-'));
        $this->line("  Descripción:  " . ($order->sunat_description ?? '-'));
        $this->line("  Hash:         " . ($order->hash ?? '-'));
        $this->line("  XML:          " . ($order->xml_path ?? '-'));
        $this->line("  CDR:          " . ($order->cdr_path ?? '-'));

        // 5. Verificar archivos físicos
        $this->newLine();
        if ($order->xml_path && Storage::disk('local')->exists($order->xml_path)) {
            $size = Storage::disk('local')->size($order->xml_path);
            $this->info("  ✓ XML firmado guardado ({$size} bytes)");
        }
        if ($order->cdr_path && Storage::disk('local')->exists($order->cdr_path)) {
            $size = Storage::disk('local')->size($order->cdr_path);
            $this->info("  ✓ CDR de SUNAT guardado ({$size} bytes)");
        }

        $this->newLine();

        return $order->sunat_status === 'ACCEPTED' ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Crea una orden ficticia (con 2 productos del catálogo) para la prueba.
     */
    private function createTestOrder(bool $isFactura): Order
    {
        $products = Product::where('is_active', true)->take(2)->get();
        if ($products->isEmpty()) {
            throw new \RuntimeException('No hay productos activos en la BD. Ejecuta los seeders.');
        }

        $user  = User::first() ?? throw new \RuntimeException('No hay usuarios en la BD.');
        $table = Table::first() ?? throw new \RuntimeException('No hay mesas en la BD.');

        $documentType = $isFactura ? 'Factura' : 'Boleta';
        $seriesKey    = $isFactura ? 'factura' : 'boleta';
        $testClient = $isFactura
            ? Client::firstOrCreate(
                ['document_number' => '20123456789'],
                [
                    'name' => 'EMPRESA PRUEBA SAC',
                    'document_type' => 'RUC',
                    'address' => 'AV. PRUEBA 123 LIMA',
                ]
            )
            : null;

        return DB::transaction(function () use ($products, $user, $table, $documentType, $seriesKey, $testClient) {
            // Reserva atómica de serie/correlativo
            $next = DocumentSeries::next($seriesKey);

            // Calcular totales
            $total = 0;
            foreach ($products as $p) {
                $total += (float) $p->price * 1;
            }
            $total = round($total, 2);
            $config = new SunatConfig();
            $gravada = round($total / (1 + $config->igvFactor()), 2);
            $igv     = round($total - $gravada, 2);

            $order = Order::create([
                'table_id'         => $table->id,
                'user_id'          => $user->id,
                'issued_by_user_id'=> $user->id,
                'client_id'        => $testClient?->id,
                'status'           => 'completed',
                'issued_at'        => now(),
                'total'            => $total,
                'document_type'    => $documentType,
                'client_name'      => $documentType === 'Factura' ? 'EMPRESA PRUEBA SAC' : 'Cliente Prueba',
                'client_document'  => $documentType === 'Factura' ? '20123456789' : '12345678',
                'serie'            => $next['serie'],
                'correlativo'      => $next['correlativo'],
                'subtotal'         => $gravada,
                'igv'              => $igv,
                'total_gravada'    => $gravada,
                'sunat_status'     => 'PENDING',
                'payment_method'   => 'cash',
                'received_amount'  => $total,
                'change_amount'    => 0,
            ]);

            foreach ($products as $p) {
                OrderDetail::create([
                    'order_id'   => $order->id,
                    'product_id' => $p->id,
                    'quantity'   => 1,
                    'price'      => $p->price,
                    'status'     => 'served',
                ]);
            }

            return $order->load('details.product');
        });
    }

    /**
     * Reusa una orden existente (debe tener serie/correlativo ya asignados).
     */
    private function reuseOrder(int $id): Order
    {
        $order = Order::with('details.product')->find($id);
        if (!$order) {
            throw new \RuntimeException("La orden #{$id} no existe.");
        }
        if (!$order->isElectronic()) {
            throw new \RuntimeException("La orden #{$id} no es Boleta ni Factura.");
        }
        if (!$order->serie || !$order->correlativo) {
            throw new \RuntimeException("La orden #{$id} no tiene serie/correlativo asignados.");
        }
        return $order;
    }
}
