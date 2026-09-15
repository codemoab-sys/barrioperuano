<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Setting;
use App\Services\Sunat\SunatService;
use App\Services\Sunat\SunatConfig;

/**
 * Genera la representación gráfica imprimible del comprobante.
 *
 * - Facturas: redirige al PDF de NubeFact (enlace_del_pdf).
 * - Boletas: genera ticket 80mm personalizado SIN QR (NubeFact maneja el QR).
 */
class BillingPdfController extends Controller
{
    /**
     * Para Facturas y Boletas redirige al PDF de NubeFact.
     * Para Nota Venta (ticket) muestra el diseño personalizado.
     */
    public function show(Order $order)
    {
        abort_unless(in_array($order->document_type, ['Boleta', 'Factura']), 404);

        if (empty($order->pdf_path) && $order->serie && $order->correlativo) {
            $this->refreshDocumentLinks($order);
        }

        // Factura o Boleta → redirigir al PDF de NubeFact
        if (!empty($order->pdf_path)) {
            return redirect($order->pdf_path);
        }

        return redirect()->route('billing.show', $order)
            ->with('error', 'NubeFact todavía no ha devuelto la URL oficial del PDF. Consulta el estado e inténtalo nuevamente.');
    }

    private function refreshDocumentLinks(Order $order): void
    {
        try {
            $response = (new SunatService())->queryDocument(
                $order->document_type === 'Factura' ? '1' : '2',
                $order->serie,
                (int) $order->correlativo
            );

            if (!empty($response['enlace_del_pdf'])) {
                $order->pdf_path = $response['enlace_del_pdf'];
            } elseif (!empty($response['enlace'])) {
                $order->pdf_path = rtrim($response['enlace'], '/') . '.pdf';
            }
            if (!empty($response['enlace_del_xml'])) {
                $order->xml_path = $response['enlace_del_xml'];
            } elseif (!empty($response['enlace'])) {
                $order->xml_path = rtrim($response['enlace'], '/') . '.xml';
            }
            if (!empty($response['enlace_del_cdr'])) {
                $order->cdr_path = $response['enlace_del_cdr'];
            } elseif (!empty($response['enlace'])) {
                $order->cdr_path = rtrim($response['enlace'], '/') . '.cdr';
            }

            $order->sunat_code = $response['sunat_responsecode'] ?? $order->sunat_code;
            $order->sunat_description = $response['sunat_description'] ?? $order->sunat_description;
            $order->hash = $response['codigo_hash'] ?? $order->hash;
            if (($response['aceptada_por_sunat'] ?? null) === true) {
                $order->sunat_status = 'ACCEPTED';
            }
            $order->save();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
    * URL oficial del PDF electrónico de NubeFact.
     */
    public function ticket(Order $order)
    {
        abort_unless(in_array($order->document_type, ['Boleta', 'Factura']), 404);

        if (empty($order->pdf_path) && $order->serie && $order->correlativo) {
            $this->refreshDocumentLinks($order);
        }

        if (!empty($order->pdf_path)) {
            return redirect($order->pdf_path);
        }

        return redirect()->route('billing.show', $order)
            ->with('error', 'NubeFact todavía no ha devuelto la URL oficial del PDF. Consulta el estado e inténtalo nuevamente.');
    }

    private function companyData(): array
    {
        $config = new SunatConfig();
        return [
            'ruc'              => $config->ruc(),
            'razon_social'     => $config->razonSocial(),
            'nombre_comercial' => $config->nombreComercial(),
            'direccion'        => $config->direccion(),
            'ticket_footer'    => Setting::where('key', 'ticket_footer')->value('value')
                ?: '¡Gracias por su preferencia!',
        ];
    }
}
