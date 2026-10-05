<?php

namespace App\Services\Sunat;

use App\Models\CreditNote;

/**
 * Convierte una CreditNote en array JSON compatible con la API de NubeFact.
 *
 * Estructura JSON NubeFact para Notas de Crédito:
 * https://www.nubefact.com/manual/ (Sección "ESTRUCTURA PARA GENERAR FACTURAS, BOLETAS Y NOTAS")
 */
class NubeFactCreditNoteBuilder
{
    public function __construct(private SunatConfig $config)
    {
    }

    /**
     * Convierte una CreditNote en array listo para enviar a NubeFact.
     */
    public function build(CreditNote $cn): array
    {
        $order = $cn->order()->with('details.product', 'client', 'delivery')->first();
        if (!$order) {
            throw new \RuntimeException('La nota de crédito no tiene una orden asociada.');
        }

        $isFactura = $order->document_type === 'Factura';
        $igvFactor = $this->config->igvFactor();
        $denom     = 1 + $igvFactor;

        // Motivos 03 y 13: solo corrección de texto, montos en 0
        $zeroAmounts = in_array($cn->reason_code, ['03', '13']);

        // ═══ ITEMS ══════════════════════════════════════════════════
        $items        = [];
        $totalGravada = 0.0;
        $totalIgv     = 0.0;

        $details = $order->details;
        if ($cn->reason_code === '07' && is_array($cn->items)) {
            $details = $details->filter(fn ($line) => array_key_exists((string) $line->id, $cn->items));
        }

        foreach ($details as $line) {
            $product         = $line->product;
            $precioVentaUnit = (float) $line->price;
            $valorUnit       = round($precioVentaUnit / $denom, 6);
            $cantidad        = $cn->reason_code === '07'
                ? (float) $cn->items[(string) $line->id]
                : (float) $line->quantity;
            $valorVenta      = round($valorUnit * $cantidad, 2);
            $subtotalLinea   = round($precioVentaUnit * $cantidad, 2);
            $igvLinea        = round($subtotalLinea - $valorVenta, 2);
            $isService       = mb_strtolower(trim((string) $product->category?->name)) === 'servicio';

            if (!$zeroAmounts) {
                $totalGravada += $valorVenta;
                $totalIgv     += $igvLinea;
            }

            $item = [
                'unidad_de_medida' => $isService ? 'ZZ' : 'NIU',
                'descripcion'      => $isService ? 'Servicio Delivery' : ($product->name ?? 'Producto'),
                'cantidad'         => $cantidad,
                'valor_unitario'   => $zeroAmounts ? 0 : $valorUnit,
                'precio_unitario'  => $zeroAmounts ? 0 : round($precioVentaUnit, 2),
                'descuento'        => '',
                'subtotal'         => $zeroAmounts ? 0 : $valorVenta,
                'tipo_de_igv'      => 1,
                'igv'              => $zeroAmounts ? 0 : $igvLinea,
                'total'            => $zeroAmounts ? 0 : $subtotalLinea,
                'anticipo_regularizacion' => false,
                'anticipo_documento_serie' => '',
                'anticipo_documento_numero' => '',
            ];
            if (!$isService) {
                $item['codigo'] = (string) ($product->id ?? '001');
            }
            $items[] = $item;
        }

        $deliveryFee = (float) ($order->delivery?->delivery_fee ?? 0);
        if (!$zeroAmounts && $cn->reason_code !== '07' && $deliveryFee > 0) {
            $deliveryValue = round($deliveryFee / $denom, 2);
            $deliveryIgv = round($deliveryFee - $deliveryValue, 2);
            $totalGravada += $deliveryValue;
            $totalIgv += $deliveryIgv;
            $items[] = [
                'unidad_de_medida' => 'ZZ',
                'codigo' => 'DELIVERY',
                'descripcion' => 'Servicio de delivery',
                'cantidad' => 1,
                'valor_unitario' => $deliveryValue,
                'precio_unitario' => round($deliveryFee, 2),
                'descuento' => '',
                'subtotal' => $deliveryValue,
                'tipo_de_igv' => 1,
                'igv' => $deliveryIgv,
                'total' => round($deliveryValue + $deliveryIgv, 2),
                'anticipo_regularizacion' => false,
                'anticipo_documento_serie' => '',
                'anticipo_documento_numero' => '',
            ];
        }

        // ═══ TOTALES ════════════════════════════════════════════════
        $totalGravada = $zeroAmounts ? 0 : round($totalGravada, 2);
        $totalIgv     = $zeroAmounts ? 0 : round($totalIgv, 2);
        $totalVenta   = $zeroAmounts ? 0 : round($totalGravada + $totalIgv, 2);

        // ═══ DOCUMENTO DEL CLIENTE ══════════════════════════════════
        $client = $order->client;

        if ($isFactura) {
            $clienteTipoDoc = 6;
            $clienteNumDoc  = $order->client_document ?: ($client?->document_number ?? '');
            $clienteNombre  = $order->client_name ?: ($client?->name ?? '');
        } else {
            $doc  = trim((string) ($order->client_document ?: $client?->document_number));
            $tipo = (strlen($doc) === 8) ? 1 : '-';
            $clienteTipoDoc = $tipo;
            $clienteNumDoc  = $doc ?: '-';
            $clienteNombre  = $order->client_name ?: ($client?->name ?? 'CLIENTE VARIOS');
        }

        $clienteDireccion = trim((string) ($client?->address ?? ''));
        if ($isFactura && (!preg_match('/^\d{11}$/', $clienteNumDoc) || $clienteNombre === '' || $clienteDireccion === '')) {
            throw new \RuntimeException('La nota de crédito de factura requiere RUC, razón social y dirección del cliente.');
        }
        if ($clienteDireccion === '') {
            $clienteDireccion = $this->config->direccion();
        }
        $clienteEmail = $client?->email ?? '';

        // ═══ FECHA ══════════════════════════════════════════════════
        $fechaEmision = now()->format('d-m-Y');

        // ═══ JSON CABECERA ══════════════════════════════════════════
        $json = [
            'operacion'                  => 'generar_comprobante',
            'tipo_de_comprobante'        => 3,  // 3 = Nota de Crédito
            'serie'                      => $cn->serie,
            'numero'                     => (int) $cn->correlativo,
            'codigo_unico'               => '07-' . $cn->serie . '-' . $cn->correlativo,
            'sunat_transaction'          => 1,
            'cliente_tipo_de_documento'  => $clienteTipoDoc,
            'cliente_numero_de_documento' => $clienteNumDoc,
            'cliente_denominacion'       => $clienteNombre,
            'cliente_direccion'          => $clienteDireccion,
            'cliente_email'              => $clienteEmail,
            'cliente_email_1'            => '',
            'cliente_email_2'            => '',
            'fecha_de_emision'           => $fechaEmision,
            'fecha_de_vencimiento'       => '',
            'moneda'                     => 1,
            'tipo_de_cambio'             => '',
            'porcentaje_de_igv'          => $this->config->igvRate(),
            'descuento_global'           => '',
            'total_descuento'            => '',
            'total_anticipo'             => '',
            'total_gravada'              => $totalGravada,
            'total_inafecta'             => 0,
            'total_exonerada'            => 0,
            'total_igv'                  => $totalIgv,
            'total_gratuita'             => '',
            'total_otros_cargos'         => '',
            'total'                      => $totalVenta,
            'percepcion_tipo'            => '',
            'percepcion_base_imponible'  => '',
            'total_percepcion'           => '',
            'total_incluido_percepcion'  => '',
            'retencion_tipo'             => '',
            'retencion_base_imponible'   => '',
            'total_retencion'            => '',
            'total_impuestos_bolsas'     => '',
            'detraccion'                 => false,
            'observaciones'              => $cn->reason_description ?? '',
            'documento_que_se_modifica_tipo'   => $isFactura ? 1 : 2, // 1=Factura, 2=Boleta
            'documento_que_se_modifica_serie'  => $order->serie,
            'documento_que_se_modifica_numero' => (int) $order->correlativo,
            'tipo_de_nota_de_credito'   => (int) $cn->reason_code, // Catálogo 09 SUNAT
            'tipo_de_nota_de_debito'    => '',
            'enviar_automaticamente_a_la_sunat' => true,
            'enviar_automaticamente_al_cliente' => false,
            'condiciones_de_pago'        => 'CONTADO',
            'medio_de_pago'              => match ($order->payment_method) {
                'cash' => 'EFECTIVO',
                'card' => 'TARJETA',
                'transfer' => 'TRANSFERENCIA',
                'yape' => 'YAPE',
                'plin' => 'PLIN',
                default => '',
            },
            'cancelado'                  => true,
            'placa_vehiculo'             => '',
            'orden_compra_servicio'      => '',
            'formato_de_pdf'             => '',
            'generado_por_contingencia'  => '',
            'bienes_region_selva'        => '',
            'servicios_region_selva'     => '',
            'items'                      => $items,
            'guias'                      => [],
            'venta_al_credito'           => [],
        ];

        return $json;
    }
}
