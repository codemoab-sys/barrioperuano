<?php

namespace App\Services\Sunat;

use App\Models\DebitNote;

/**
 * Convierte una DebitNote en array JSON compatible con la API de NubeFact.
 * tipo_de_comprobante = 4 (Nota de Débito)
 */
class NubeFactDebitNoteBuilder
{
    public function __construct(private SunatConfig $config)
    {
    }

    public function build(DebitNote $dn): array
    {
        $order = $dn->order()->with('details.product', 'client')->first();
        if (!$order) {
            throw new \RuntimeException('La nota de débito no tiene una orden asociada.');
        }

        $isFactura = $order->document_type === 'Factura';
        $igvFactor = $this->config->igvFactor();
        $denom     = 1 + $igvFactor;

        // ═══ ITEMS ══════════════════════════════════════════════════
        $items        = [];
        $totalGravada = 0.0;
        $totalIgv     = 0.0;

        if ($dn->amount !== null) {
            $valorVenta = round((float) $dn->amount / $denom, 2);
            $subtotalLinea = round((float) $dn->amount, 2);
            $igvLinea = round($subtotalLinea - $valorVenta, 2);
            $totalGravada = $valorVenta;
            $totalIgv = $igvLinea;
            $items[] = [
                'unidad_de_medida' => 'NIU',
                'codigo' => 'AJUSTE',
                'descripcion' => $dn->reason_description,
                'cantidad' => 1,
                'valor_unitario' => $valorVenta,
                'precio_unitario' => round((float) $dn->amount, 2),
                'descuento' => '',
                'subtotal' => $valorVenta,
                'tipo_de_igv' => 1,
                'igv' => $igvLinea,
                'total' => $subtotalLinea,
                'anticipo_regularizacion' => false,
                'anticipo_documento_serie' => '',
                'anticipo_documento_numero' => '',
            ];
        }

        foreach ($dn->amount === null ? $order->details : [] as $line) {
            $product         = $line->product;
            $precioVentaUnit = (float) $line->price;
            $valorUnit       = round($precioVentaUnit / $denom, 6);
            $cantidad        = (float) $line->quantity;
            $valorVenta      = round($valorUnit * $cantidad, 2);
            $igvLinea        = round($valorVenta * $igvFactor, 2);
            $subtotalLinea   = round($valorVenta + $igvLinea, 2);

            $totalGravada += $valorVenta;
            $totalIgv     += $igvLinea;

            $items[] = [
                'unidad_de_medida' => 'NIU',
                'codigo'           => (string) ($product->id ?? '001'),
                'descripcion'      => $product->name ?? 'Producto',
                'cantidad'         => $cantidad,
                'valor_unitario'   => $valorUnit,
                'precio_unitario'  => round($precioVentaUnit, 2),
                'descuento'        => '',
                'subtotal'         => $valorVenta,
                'tipo_de_igv'      => 1,
                'igv'              => $igvLinea,
                'total'            => $subtotalLinea,
                'anticipo_regularizacion' => false,
                'anticipo_documento_serie' => '',
                'anticipo_documento_numero' => '',
            ];
        }

        $totalGravada = round($totalGravada, 2);
        $totalIgv     = round($totalIgv, 2);
        $totalVenta   = round($totalGravada + $totalIgv, 2);

        // ═══ DOCUMENTO DEL CLIENTE ════════════════════════════════
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
            throw new \RuntimeException('La nota de débito de factura requiere RUC, razón social y dirección del cliente.');
        }
        if ($clienteDireccion === '') {
            $clienteDireccion = $this->config->direccion();
        }
        $clienteEmail = $client?->email ?? '';

        // ═══ JSON CABECERA ════════════════════════════════════════
        return [
            'operacion'                  => 'generar_comprobante',
            'tipo_de_comprobante'        => 4,  // 4 = Nota de Débito
            'serie'                      => $dn->serie,
            'numero'                     => (int) $dn->correlativo,
            'codigo_unico'               => '08-' . $dn->serie . '-' . $dn->correlativo,
            'sunat_transaction'          => 1,
            'cliente_tipo_de_documento'  => $clienteTipoDoc,
            'cliente_numero_de_documento' => $clienteNumDoc,
            'cliente_denominacion'       => $clienteNombre,
            'cliente_direccion'          => $clienteDireccion,
            'cliente_email'              => $clienteEmail,
            'cliente_email_1'            => '',
            'cliente_email_2'            => '',
            'fecha_de_emision'           => now()->format('d-m-Y'),
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
            'observaciones'              => $dn->reason_description ?? '',
            'documento_que_se_modifica_tipo'   => $isFactura ? 1 : 2,
            'documento_que_se_modifica_serie'  => $order->serie,
            'documento_que_se_modifica_numero' => (int) $order->correlativo,
            'tipo_de_nota_de_credito'   => '',
            'tipo_de_nota_de_debito'    => (int) $dn->reason_code,
            'enviar_automaticamente_a_la_sunat' => true,
            'enviar_automaticamente_al_cliente' => false,
            'condiciones_de_pago'        => 'CONTADO',
            'medio_de_pago'              => match ($order->payment_method) {
                'cash' => 'EFECTIVO',
                'card' => 'TARJETA',
                'transfer' => 'TRANSFERENCIA',
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
    }
}
