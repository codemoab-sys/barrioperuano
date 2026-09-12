<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Setting;
use App\Mail\InvoiceMail;
use App\Services\Sunat\SunatService;
use App\Services\Sunat\NubeFactService;
use App\Services\Sunat\SunatConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;

/**
 * Módulo de Comprobantes Electrónicos (SUNAT - Perú).
 *
 * Listado, detalle, reintento de envío, descarga de XML/CDR/PDF
 * tanto para boletas/facturas como para notas de crédito.
 */
class BillingController extends Controller
{
    /**
     * Listado con filtros (estado, tipo, fecha, búsqueda libre).
     */
    public function index(Request $request)
    {
        $from = $request->input('from') ?: Carbon::now()->startOfMonth()->format('Y-m-d');
        $to = $request->input('to') ?: Carbon::now()->endOfMonth()->format('Y-m-d');

        $query = Order::with('user')
            ->whereIn('document_type', ['Boleta', 'Factura'])
            ->whereNotNull('serie')
            ->whereNotNull('correlativo')
            ->orderByRaw('COALESCE(issued_at, created_at) DESC');

        if ($s = $request->input('status')) {
            $query->where('sunat_status', $s);
        }
        if ($t = $request->input('type')) {
            $query->where('document_type', $t);
        }
        $query->whereDate(DB::raw('COALESCE(issued_at, created_at)'), '>=', $from)
            ->whereDate(DB::raw('COALESCE(issued_at, created_at)'), '<=', $to);
        if ($q = $request->input('q')) {
            $query->where(function ($qq) use ($q) {
                $qq->where('serie', 'like', "%{$q}%")
                   ->orWhere('correlativo', 'like', "%{$q}%")
                   ->orWhere('client_name', 'like', "%{$q}%")
                   ->orWhere('client_document', 'like', "%{$q}%");
            });
        }

        $orders = $query->paginate(25)->withQueryString();

        // Totales rápidos por estado (sin filtros)
        $stats = Order::whereIn('document_type', ['Boleta', 'Factura'])
            ->selectRaw('sunat_status, COUNT(*) as total')
            ->groupBy('sunat_status')
            ->pluck('total', 'sunat_status')
            ->toArray();

        return view('billing.index', compact('orders', 'stats', 'from', 'to'));
    }

    /**
     * Detalle de un comprobante.
     */
    public function show(Order $order)
    {
        abort_unless(in_array($order->document_type, ['Boleta', 'Factura']), 404);
        $order->load('details.product', 'creditNotes');
        $igvRate = (new SunatConfig())->igvRate();
        return view('billing.show', compact('order', 'igvRate'));
    }

    /**
     * Reenvía un comprobante a SUNAT.
     */
    public function retry(Order $order)
    {
        abort_unless(in_array($order->document_type, ['Boleta', 'Factura']), 404);

        if (!$order->serie || !$order->correlativo) {
            return back()->with('error', 'La orden no tiene serie/correlativo asignados.');
        }

        if ($order->sunat_code === '23' || str_contains((string) $order->sunat_description, 'Este documento ya existe')) {
            return back()->with('info', 'El documento ya fue registrado por el proveedor electrónico. Usa "Consultar estado"; no se volverá a enviar.');
        }
        if ($order->sunat_status === 'PENDING' && $order->sent_at) {
            return back()->with('info', 'El comprobante está pendiente. Usa "Consultar estado"; no se volverá a enviar.');
        }

        try {
            (new SunatService())->sendInvoice($order->fresh('details.product'));
            $order->refresh();
        } catch (\Throwable $e) {
            Log::error('Reintento SUNAT falló', ['order_id' => $order->id, 'msg' => $e->getMessage()]);
            return back()->with('error', 'No se pudo reenviar el comprobante. Revisa el detalle del error.');
        }

        $msg = "Comprobante {$order->full_number}: {$order->sunat_status}";
        if ($order->sunat_description) {
            $msg .= " - {$order->sunat_description}";
        }

        return back()->with($order->sunat_status === 'ACCEPTED' ? 'success' : 'error', $msg);
    }

    public function syncStatus(Request $request, Order $order)
    {
        abort_unless(in_array($order->document_type, ['Boleta', 'Factura']), 404);

        try {
            $service = new NubeFactService();
            $response = $service->queryDocument(
                $order->document_type === 'Factura' ? '1' : '2',
                $order->serie,
                (int) $order->correlativo
            );
            $order = $service->syncOrderStatusFromResponse($order, $response);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'status' => $order->sunat_status,
                    'response' => $response,
                ]);
            }

            return back()->with(
                $order->sunat_status === 'ACCEPTED' ? 'success' : 'info',
                "Estado actualizado: {$order->sunat_status}. " . ($order->sunat_description ?? '')
            );
        } catch (\Throwable $e) {
            Log::error('Consulta de comprobante NubeFact falló', ['order_id' => $order->id, 'msg' => $e->getMessage()]);
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo consultar el comprobante. Revisa el detalle del error.',
                ], 422);
            }
            return back()->with('error', 'No se pudo consultar el comprobante. Revisa el detalle del error.');
        }
    }

    /**
     * Solicita a NubeFact la anulación de una factura o boleta.
     */
    public function cancel(Request $request, Order $order)
    {
        abort_unless(in_array($order->document_type, ['Boleta', 'Factura']), 404);

        $request->validate(['motivo' => 'required|string|max:100']);

        if ($order->sunat_status !== 'ACCEPTED') {
            return back()->with('error', 'Solo se puede anular un comprobante aceptado por SUNAT.');
        }
        if ($order->anulacion_status === 'ACCEPTED') {
            return back()->with('error', 'Este comprobante ya figura como anulado.');
        }

        try {
            $response = (new NubeFactService())->voidDocument(
                $order->document_type === 'Factura' ? '1' : '2',
                $order->serie,
                (int) $order->correlativo,
                $request->input('motivo')
            );

            $this->storeCancellationResponse($order, $response);

            return back()->with(
                ($order->anulacion_status === 'ACCEPTED') ? 'success' : 'error',
                $order->anulacion_status === 'ACCEPTED'
                    ? 'Anulación aceptada por SUNAT.'
                    : 'Anulación enviada. Consulta el estado con el botón de actualizar.'
            );
        } catch (\Throwable $e) {
            Log::error('Anulación NubeFact falló', ['order_id' => $order->id, 'msg' => $e->getMessage()]);
            return back()->with('error', 'No se pudo enviar la anulación. Revisa el detalle del error.');
        }
    }

    /**
     * Consulta en NubeFact una anulación enviada previamente.
     */
    public function queryCancellation(Order $order)
    {
        abort_unless(in_array($order->document_type, ['Boleta', 'Factura']), 404);

        if (!$order->anulacion_ticket && $order->anulacion_status !== 'PENDING') {
            return back()->with('error', 'Este comprobante no tiene una anulación pendiente.');
        }

        try {
            $response = (new NubeFactService())->queryCancellation(
                $order->document_type === 'Factura' ? '1' : '2',
                $order->serie,
                (int) $order->correlativo
            );
            $this->storeCancellationResponse($order, $response);

            return back()->with(
                $order->anulacion_status === 'ACCEPTED' ? 'success' : 'error',
                $order->anulacion_status === 'ACCEPTED'
                    ? 'Anulación aceptada por SUNAT.'
                    : 'La anulación aún no ha sido aceptada por SUNAT.'
            );
        } catch (\Throwable $e) {
            Log::error('Consulta de anulación NubeFact falló', ['order_id' => $order->id, 'msg' => $e->getMessage()]);
            return back()->with('error', 'No se pudo consultar la anulación. Revisa el detalle del error.');
        }
    }

    private function storeCancellationResponse(Order $order, array $response): void
    {
        $accepted = ($response['aceptada_por_sunat'] ?? null) === true;
        $hasError = isset($response['errors']);
        $order->anulacion_status = $accepted ? 'ACCEPTED' : ($hasError ? 'ERROR' : 'PENDING');
        $order->anulacion_ticket = $response['sunat_ticket_numero'] ?? $order->anulacion_ticket;
        $order->anulacion_description = $response['sunat_description'] ?? $response['sunat_note'] ?? $response['errors'] ?? null;
        $order->anulacion_pdf_path = $response['enlace_del_pdf'] ?? $order->anulacion_pdf_path;
        $order->anulacion_xml_path = $response['enlace_del_xml'] ?? $order->anulacion_xml_path;
        $order->anulacion_cdr_path = $response['enlace_del_cdr'] ?? $order->anulacion_cdr_path;
        $order->anulacion_sent_at = $order->anulacion_sent_at ?? now();

        if ($accepted) {
            $order->sunat_status = 'VOIDED';
        }

        $order->save();
    }

    /**
     * Descarga el XML firmado.
     */
    public function downloadXml(Order $order)
    {
        if (!$order->xml_path) {
            $this->refreshDocumentLinks($order);
        }

        return $this->streamSunatFile($order->xml_path, 'application/xml');
    }

    /**
     * Descarga el CDR (zip).
     */
    public function downloadCdr(Order $order)
    {
        if (!$order->cdr_path) {
            $this->refreshDocumentLinks($order);
        }

        return $this->streamSunatFile($order->cdr_path, 'application/zip');
    }

    public function downloadCancellationPdf(Order $order)
    {
        abort_unless(in_array($order->document_type, ['Boleta', 'Factura']), 404);
        return $this->streamSunatFile($order->anulacion_pdf_path, 'application/pdf');
    }

    public function downloadCancellationXml(Order $order)
    {
        abort_unless(in_array($order->document_type, ['Boleta', 'Factura']), 404);
        return $this->streamSunatFile($order->anulacion_xml_path, 'application/xml');
    }

    public function downloadCancellationCdr(Order $order)
    {
        abort_unless(in_array($order->document_type, ['Boleta', 'Factura']), 404);
        return $this->streamSunatFile($order->anulacion_cdr_path, 'application/zip');
    }

    /**
     * Consulta NubeFact solo cuando falta el enlace solicitado.
     */
    private function refreshDocumentLinks(Order $order): void
    {
        abort_unless(in_array($order->document_type, ['Boleta', 'Factura']), 404);

        try {
            $response = (new \App\Services\Sunat\SunatService())->queryDocument(
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

            if (array_key_exists('aceptada_por_sunat', $response)) {
                $order->sunat_status = $response['aceptada_por_sunat'] === true
                    ? 'ACCEPTED'
                    : $order->sunat_status;
            }
            $order->sunat_code = $response['sunat_responsecode'] ?? $order->sunat_code;
            $order->sunat_description = $response['sunat_description'] ?? $order->sunat_description;
            $order->hash = $response['codigo_hash'] ?? $order->hash;
            $order->save();
        } catch (\Throwable $e) {
            Log::warning('No se pudieron consultar los enlaces del comprobante', [
                'order_id' => $order->id,
                'msg' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Streaming seguro de archivos dentro de storage/app/sunat/.
     */
    private function streamSunatFile(?string $relPath, string $mime): Response
    {
        if (!$relPath) {
            abort(404, 'Archivo no encontrado.');
        }

        if (filter_var($relPath, FILTER_VALIDATE_URL)) {
            $urlHost = parse_url($relPath, PHP_URL_HOST);
            $rutaHost = parse_url((new \App\Services\Sunat\SunatConfig())->nubefactRuta(), PHP_URL_HOST);
            $allowedHost = is_string($urlHost)
                && ($urlHost === $rutaHost || str_ends_with($urlHost, '.nubefact.com'));

            abort_unless($allowedHost, 404, 'Archivo no encontrado.');

            $remote = Http::timeout(30)->get($relPath);
            abort_unless($remote->successful(), 404, 'Archivo no encontrado.');

            return response()->streamDownload(
                fn () => print($remote->body()),
                basename(parse_url($relPath, PHP_URL_PATH) ?: 'comprobante'),
                ['Content-Type' => $mime]
            );
        }

        if (!Storage::disk('local')->exists($relPath)) {
            abort(404, 'Archivo no encontrado.');
        }

        return response()->stream(function () use ($relPath) {
            echo Storage::disk('local')->get($relPath);
        }, 200, [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'attachment; filename="' . basename($relPath) . '"',
        ]);
    }

    /**
     * Envía el comprobante por correo electrónico.
     */
    public function sendEmail(Request $request, Order $order)
    {
        abort_unless(in_array($order->document_type, ['Boleta', 'Factura']), 404);

        $request->validate([
            'email' => 'required|email|max:250',
            'message' => 'nullable|string|max:500',
        ]);

        if (empty($order->pdf_path)) {
            return back()->with('error', 'No hay PDF disponible. El comprobante debe ser aceptado por SUNAT primero.');
        }

        $passwordRaw = Setting::where('key', 'smtp_password')->value('value');
        if (empty($passwordRaw)) {
            return back()->with('error', 'No hay contraseña SMTP configurada. Ve a Configuración → SUNAT.');
        }
        $password = str_starts_with($passwordRaw, 'enc:')
            ? Crypt::decryptString(substr($passwordRaw, 4))
            : $passwordRaw;

        config([
            'mail.mailers.smtp.password' => $password,
        ]);

        try {
            Mail::to($request->input('email'))->send(
                new InvoiceMail($order, $request->input('message', ''))
            );

            return back()->with('success', "Comprobante enviado a {$request->input('email')}.");
        } catch (\Throwable $e) {
            Log::error('Error enviando comprobante por email', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', 'Error al enviar: ' . $e->getMessage());
        }
    }
}
