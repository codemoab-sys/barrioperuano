<?php

namespace App\Http\Controllers;

use App\Models\DebitNote;
use App\Models\DocumentSeries;
use App\Models\Order;
use App\Services\Sunat\SunatService;
use App\Services\Sunat\SunatConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Gestión de Notas de Débito Electrónicas (tipo 08 SUNAT).
 *
 * Catálogo 10 SUNAT:
 *   1 Intereses por mora
 *   2 Aumento de valor
 *   3 Penalidades
 *   4 Ajustes afectos al IVAP
 *   5 Ajustes de operaciones de exportación
 */
class DebitNoteController extends Controller
{
    public const REASON_CODES = [
        '1' => 'Intereses por mora',
        '2' => 'Aumento de valor',
        '3' => 'Penalidades',
        '4' => 'Ajustes afectos al IVAP',
        '5' => 'Ajustes de operaciones de exportación',
    ];

    public function index(Request $request)
    {
        $query = DebitNote::with('order', 'user')->orderByDesc('created_at');

        if ($s = $request->input('status')) {
            $query->where('sunat_status', $s);
        }
        if ($q = $request->input('q')) {
            $query->where(function ($qq) use ($q) {
                $qq->where('serie', 'like', "%{$q}%")
                   ->orWhere('correlativo', 'like', "%{$q}%");
            });
        }

        $notes = $query->paginate(25)->withQueryString();
        return view('debit_notes.index', compact('notes'));
    }

    public function create(Order $order)
    {
        abort_unless(in_array($order->document_type, ['Boleta', 'Factura']), 404);

        if ($order->sunat_status !== 'ACCEPTED') {
            return redirect()->route('billing.show', $order)
                ->with('error', 'Solo se puede emitir nota de débito sobre comprobantes ACEPTADOS por SUNAT.');
        }

        $order->load('details.product');
        return view('debit_notes.create', [
            'order'   => $order,
            'reasons' => self::REASON_CODES,
        ]);
    }

    public function store(Request $request, Order $order)
    {
        abort_unless(in_array($order->document_type, ['Boleta', 'Factura']), 404);

        if ($order->sunat_status !== 'ACCEPTED') {
            return redirect()->route('billing.show', $order)
                ->with('error', 'Solo se puede emitir nota de débito sobre comprobantes ACEPTADOS por SUNAT.');
        }

        $request->validate([
            'reason_code'        => 'required|string|in:1,2,3,4,5',
            'reason_description' => 'required|string|max:255',
            'amount'             => 'required|numeric|min:0.01',
        ]);

        $isFactura = $order->document_type === 'Factura';
        $seriesKey = $isFactura ? 'nota_debito_factura' : 'nota_debito_boleta';
        $total = (float) $request->input('amount');
        $subtotal = round($total / (1 + (new SunatConfig())->igvFactor()), 2);
        $igv = round($total - $subtotal, 2);

        $dn = DB::transaction(function () use ($request, $order, $seriesKey, $subtotal, $igv, $total) {
            $next = DocumentSeries::next($seriesKey);

            return DebitNote::create([
                'order_id'           => $order->id,
                'serie'              => $next['serie'],
                'correlativo'        => $next['correlativo'],
                'document_type'      => 'nota_debito',
                'reason_code'        => $request->input('reason_code'),
                'reason_description' => $request->input('reason_description'),
                'amount'             => $request->input('amount'),
                'subtotal'           => $subtotal,
                'igv'                => $igv,
                'total'              => $total,
                'sunat_status'       => 'PENDING',
                'user_id'            => Auth::id(),
            ]);
        });

        try {
            (new SunatService())->sendDebitNote($dn);
            $dn->refresh();
        } catch (\Throwable $e) {
            Log::error('DebitNote sendDebitNote', ['dn_id' => $dn->id, 'msg' => $e->getMessage()]);
        }

        $msg = "Nota de Débito {$dn->full_number}: {$dn->sunat_status}";
        return redirect()->route('debit_notes.show', $dn)
            ->with($dn->sunat_status === 'ACCEPTED' ? 'success' : 'error', $msg);
    }

    public function show(DebitNote $debitNote)
    {
        $debitNote->load('order.details.product');
        return view('debit_notes.show', ['dn' => $debitNote]);
    }

    public function retry(DebitNote $debitNote)
    {
        try {
            (new SunatService())->sendDebitNote($debitNote);
            $debitNote->refresh();
        } catch (\Throwable $e) {
            return back()->with('error', 'Excepción: ' . $e->getMessage());
        }

        $msg = "Nota de Débito {$debitNote->full_number}: {$debitNote->sunat_status}";
        return back()->with($debitNote->sunat_status === 'ACCEPTED' ? 'success' : 'error', $msg);
    }

    public function downloadXml(DebitNote $debitNote)
    {
        return $this->stream($debitNote->xml_path, 'application/xml');
    }

    public function downloadPdf(DebitNote $debitNote)
    {
        return $this->stream($debitNote->pdf_path, 'application/pdf');
    }

    public function downloadCdr(DebitNote $debitNote)
    {
        return $this->stream($debitNote->cdr_path, 'application/zip');
    }

    private function stream(?string $relPath, string $mime)
    {
        if (!$relPath) {
            abort(404, 'Archivo no encontrado.');
        }

        if (filter_var($relPath, FILTER_VALIDATE_URL)) {
            $host = parse_url($relPath, PHP_URL_HOST);
            $configuredHost = parse_url((new SunatConfig())->nubefactRuta(), PHP_URL_HOST);
            $allowed = is_string($host) && (
                $host === $configuredHost || str_ends_with($host, '.nubefact.com')
            );
            abort_unless($allowed, 404, 'Archivo no encontrado.');
            $response = Http::timeout(30)->get($relPath);
            abort_unless($response->successful(), 404, 'Archivo no encontrado.');
            return response()->streamDownload(fn () => print($response->body()), basename(parse_url($relPath, PHP_URL_PATH) ?: 'comprobante'), ['Content-Type' => $mime]);
        }

        if (!Storage::disk('local')->exists($relPath)) abort(404, 'Archivo no encontrado.');
        return response()->stream(function () use ($relPath) {
            echo Storage::disk('local')->get($relPath);
        }, 200, [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'attachment; filename="' . basename($relPath) . '"',
        ]);
    }
}
