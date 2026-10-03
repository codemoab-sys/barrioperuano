<?php

namespace App\Services\Sunat;

use App\Models\Order;

/**
 * Convierte una Order en array JSON compatible con la API de NubeFact.
 *
 * Estructura JSON NubeFact:
 * https://www.nubefact.com/manual/ (Sección "ESTRUCTURA PARA GENERAR FACTURAS, BOLETAS Y NOTAS")
 */
class NubeFactInvoiceBuilder
{
    public function __construct(private SunatConfig $config)
    {
    }

    /**
     * Convierte una Order en array listo para enviar a NubeFact.
     */
    public function build(Order $order): array
    {
        $order->loadMissing('client', 'delivery');

        $isFactura = $order->document_type === 'Factura';
        $igvRate   = $this->config->igvRate();
        if (!in_array($igvRate, [18.0, 10.5, 10.0, 4.0], true)) {
            throw new \RuntimeException('El porcentaje de IGV configurado debe ser 18, 10.5, 10 o 4.');
        }

        $igvFactor = $this->config->igvFactor();
        $denom     = 1 + $igvFactor;             // 1.18

        // ═══ ITEMS ═══════════════════════════════════════════════════
        $items          = [];
        $grossSubtotal  = 0.0;
        $totalGravada   = 0.0;
        $totalIgv       = 0.0;
        $totalExonerada = 0.0;
        $totalInafecta  = 0.0;

        foreach ($order->details as $line) {
            $grossSubtotal += (float) $line->price * (float) $line->quantity;
        }

        $discountGross = min(max((float) ($order->discount ?? 0), 0), $grossSubtotal);
        $discountNet = round($discountGross / $denom, 2);
        $remainingDiscountNet = $discountNet;
        $lastLineIndex = $order->details->count() - 1;

        foreach ($order->details as $lineIndex => $line) {
            $product = $line->product;

            $precioVentaUnit = (float) $line->price;
            $valorUnit       = round($precioVentaUnit / $denom, 6);
            $cantidad        = (float) $line->quantity;
            $lineGross       = $precioVentaUnit * $cantidad;
            $lineDiscount    = $lineIndex === $lastLineIndex
                ? $remainingDiscountNet
                : round($discountNet * ($lineGross / max($grossSubtotal, 1)), 2);
            $lineDiscount    = min($lineDiscount, $remainingDiscountNet);
            $remainingDiscountNet = round($remainingDiscountNet - $lineDiscount, 2);
            $valorVenta      = round(($valorUnit * $cantidad) - $lineDiscount, 2);
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
                'descuento'        => $lineDiscount,
                'subtotal'         => $valorVenta,
                'tipo_de_igv'      => 1,          // 1 = Gravado - Operación Onerosa
                'igv'              => $igvLinea,
                'total'            => $subtotalLinea,
                'anticipo_regularizacion' => false,
                'anticipo_documento_serie' => '',
                'anticipo_documento_numero' => '',
            ];
        }

        $deliveryFee = (float) ($order->delivery?->delivery_fee ?? 0);
        if ($deliveryFee > 0) {
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
        $totalGravada = round($totalGravada, 2);
        $totalIgv     = round($totalIgv, 2);
        $totalVenta   = round($totalGravada + $totalIgv, 2);

        // ═══ TIPO DE MONEDA ═════════════════════════════════════════
        $monedaCodigo = 1; // 1 = Soles

        // ═══ DOCUMENTO DEL CLIENTE ══════════════════════════════════
        $client = $order->client;

        if ($isFactura) {
            $clienteTipoDoc = 6;  // RUC
            $clienteNumDoc  = $order->client_document ?: ($client?->document_number ?? '');
            $clienteNombre  = $order->client_name ?: ($client?->name ?? '');
        } else {
            // Boleta
            $doc  = trim((string) ($order->client_document ?: $client?->document_number));
            $tipo = (strlen($doc) === 8) ? 1 : '-'; // 1 = DNI, - = Varios
            $clienteTipoDoc = $tipo;
            $clienteNumDoc  = $doc ?: '-';
            $clienteNombre  = $order->client_name ?: ($client?->name ?? 'CLIENTE VARIOS');
        }

        $clienteDireccion = trim((string) ($client?->address ?? ''));

        if ($isFactura && (!preg_match('/^\d{11}$/', $clienteNumDoc) || $clienteNombre === '' || $clienteDireccion === '')) {
            throw new \RuntimeException('La factura requiere RUC, razón social y dirección del cliente.');
        }

        if ($clienteDireccion === '') {
            $clienteDireccion = $this->config->direccion();
        }
        $clienteEmail = $client?->email ?? '';

        // ═══ FECHA ══════════════════════════════════════════════════
        $fechaEmision = ($order->issued_at ?? now())->format('d-m-Y');

        // ═══ JSON CABECERA ══════════════════════════════════════════
        $json = [
            'operacion'                  => 'generar_comprobante',
            'tipo_de_comprobante'        => $isFactura ? 1 : 2, // 1=Factura, 2=Boleta
            'serie'                      => $order->serie,
            'numero'                     => (int) $order->correlativo,
            'codigo_unico'               => ($isFactura ? '01' : '03') . '-' . $order->serie . '-' . $order->correlativo,
            'sunat_transaction'          => 1,  // 1 = Venta interna
            'cliente_tipo_de_documento'  => $clienteTipoDoc,
            'cliente_numero_de_documento' => $clienteNumDoc,
            'cliente_denominacion'       => $clienteNombre,
            'cliente_direccion'          => $clienteDireccion,
            'cliente_email'              => $clienteEmail,
            'cliente_email_1'            => '',
            'cliente_email_2'            => '',
            'fecha_de_emision'           => $fechaEmision,
            'fecha_de_vencimiento'       => '',
            'moneda'                     => $monedaCodigo,
            'tipo_de_cambio'             => '',
            'porcentaje_de_igv'          => $igvRate,
            'descuento_global'           => '',
            'total_descuento'            => '',
            'total_anticipo'             => '',
            'total_gravada'              => $totalGravada,
            'total_inafecta'             => $totalInafecta,
            'total_exonerada'            => $totalExonerada,
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
            'observaciones'              => '',
            'documento_que_se_modifica_tipo'    => '',
            'documento_que_se_modifica_serie'   => '',
            'documento_que_se_modifica_numero'  => '',
            'tipo_de_nota_de_credito'   => '',
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
