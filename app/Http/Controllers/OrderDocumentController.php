<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\DocumentSeries;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Services\Sunat\NubeFactService;
use App\Services\Sunat\SunatConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class OrderDocumentController extends Controller
{
    public function store(Request $request, Order $order, NubeFactService $nubefact)
    {
        if (!Schema::hasTable('bape_order_documents')) {
            return back()->with('error', 'Falta crear la tabla bape_order_documents. No se emitió ningún comprobante.');
        }

        $validated = $request->validate([
            'document_type' => ['required', 'in:Boleta,Factura'],
            'client_id' => ['nullable', 'integer', 'exists:bape_clients,id'],
        ]);

        if (!(new SunatConfig())->isNubefactConfigured()) {
            return back()->with('error', 'Configure RUTA, TOKEN y los datos del emisor en Facturación Electrónica antes de emitir.');
        }

        $client = !empty($validated['client_id'])
            ? Client::findOrFail($validated['client_id'])
            : null;
        $documentType = $validated['document_type'];
        $clientDocument = preg_replace('/\D/', '', (string) $client?->document_number);

        if ($documentType === 'Factura') {
            if (strlen($clientDocument) !== 11) {
                throw ValidationException::withMessages([
                    'client_id' => ['Para emitir Factura seleccione un cliente con RUC de 11 dígitos.'],
                ]);
            }
            if (trim((string) $client?->name) === '') {
                throw ValidationException::withMessages([
                    'client_id' => ['Para emitir Factura el cliente debe tener razón social.'],
                ]);
            }
            if (trim((string) $client?->address) === '') {
                throw ValidationException::withMessages([
                    'client_id' => ['Para emitir Factura el cliente debe tener dirección registrada.'],
                ]);
            }
        } elseif ($clientDocument !== '' && !in_array(strlen($clientDocument), [8, 11], true)) {
            throw ValidationException::withMessages([
                'client_id' => ['El documento del cliente para boleta debe tener 8 dígitos (DNI) u 11 dígitos (RUC), o seleccione Cliente Varios.'],
            ]);
        }

        try {
            $document = DB::transaction(function () use ($order, $documentType, $client, $clientDocument) {
                $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

                if ($lockedOrder->status !== 'completed' || $lockedOrder->document_type !== 'Ticket') {
                    throw new RuntimeException('Solo se puede emitir un comprobante desde una nota de venta completada.');
                }
                if ($lockedOrder->billingDocument()->exists()) {
                    throw new RuntimeException('Esta nota de venta ya tiene un comprobante vinculado.');
                }

                $areaId = $lockedOrder->table()->value('area_id');
                $seriesKey = $documentType === 'Factura' ? 'factura' : 'boleta';
                $series = DocumentSeries::next($seriesKey, $areaId);

                return OrderDocument::create([
                    'order_id' => $lockedOrder->id,
                    'client_id' => $client?->id,
                    'document_type' => $documentType,
                    'client_name' => $client?->name ?? 'CLIENTE VARIOS',
                    'client_document' => $clientDocument !== '' ? $clientDocument : null,
                    'client_address' => $client?->address,
                    'client_email' => $client?->email,
                    'serie' => $series['serie'],
                    'correlativo' => $series['correlativo'],
                    'issued_at' => now(),
                    'total' => $lockedOrder->total,
                    'sunat_status' => 'PENDING',
                ]);
            });
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        try {
            $nubefact->sendOrderDocument($document, $order->fresh(['details.product.category', 'delivery']));
            $document->refresh();
        } catch (Throwable $exception) {
            Log::error('Emisión de comprobante desde nota de venta falló', [
                'order_id' => $order->id,
                'order_document_id' => $document->id,
                'message' => $exception->getMessage(),
            ]);
            $document->sunat_status = 'ERROR';
            $document->sunat_code = '500';
            $document->sunat_description = $exception->getMessage();
            $document->save();
        }

        return back()->with(
            $document->sunat_status === 'ACCEPTED' ? 'success' : 'error',
            "{$document->document_type} {$document->full_number}: {$document->sunat_status}"
                . ($document->sunat_description ? ' - ' . $document->sunat_description : '')
        );
    }

    public function retry(Order $order, NubeFactService $nubefact)
    {
        abort_unless($order->status === 'completed' && $order->document_type === 'Ticket', 404);
        abort_unless(Schema::hasTable('bape_order_documents'), 404);

        $result = DB::transaction(function () use ($order) {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $document = OrderDocument::query()
                ->where('order_id', $lockedOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($document->sunat_status, ['PENDING', 'ACCEPTED'], true)
                || $document->sunat_code === '23'
                || str_contains((string) $document->sunat_description, 'Este documento ya existe')) {
                return ['level' => 'info', 'message' => 'El comprobante ya fue enviado o está pendiente. No se volverá a enviar para evitar duplicados.'];
            }

            if ($document->sunat_status !== 'ERROR') {
                return ['level' => 'error', 'message' => 'Solo se pueden reenviar comprobantes con error técnico. Los rechazados requieren revisar sus datos antes de cualquier reemisión.'];
            }

            $document->sunat_status = 'PENDING';
            $document->sent_at = now();
            $document->save();

            return ['document' => $document];
        });

        if (isset($result['message'])) {
            return back()->with($result['level'], $result['message']);
        }

        $document = $result['document'];

        try {
            $nubefact->sendOrderDocument($document, $order->fresh(['details.product.category', 'delivery']));
            $document->refresh();
        } catch (Throwable $exception) {
            Log::error('Reintento de comprobante desde nota de venta falló', [
                'order_id' => $order->id,
                'order_document_id' => $document->id,
                'message' => $exception->getMessage(),
            ]);
            $document->sunat_status = 'ERROR';
            $document->sunat_code = '500';
            $document->sunat_description = $exception->getMessage();
            $document->save();
        }

        return back()->with(
            $document->sunat_status === 'ACCEPTED' ? 'success' : 'error',
            "{$document->document_type} {$document->full_number}: {$document->sunat_status}"
                . ($document->sunat_description ? ' - ' . $document->sunat_description : '')
        );
    }

    public function sync(Order $order, NubeFactService $nubefact)
    {
        abort_unless($order->status === 'completed' && $order->document_type === 'Ticket', 404);
        abort_unless(Schema::hasTable('bape_order_documents'), 404);

        $document = $order->billingDocument;
        abort_unless($document && $document->serie && $document->correlativo, 404);

        if ($document->sunat_status !== 'PENDING') {
            return back()->with('info', 'Solo se consultan comprobantes pendientes.');
        }

        try {
            $typeCode = $document->document_type === 'Factura' ? '1' : '2';
            $response = $nubefact->queryDocument(
                $typeCode,
                $document->serie,
                (int) $document->correlativo
            );
            $nubefact->syncOrderDocumentStatusFromResponse($document, $response);
            $document->refresh();
        } catch (Throwable $exception) {
            Log::error('Consulta de comprobante de nota de venta falló', [
                'order_id' => $order->id,
                'order_document_id' => $document->id,
                'message' => $exception->getMessage(),
            ]);

            return back()->with('error', 'No se pudo consultar el comprobante en NubeFact. Revisa el detalle del error.');
        }

        return back()->with(
            $document->sunat_status === 'ACCEPTED' ? 'success' : 'info',
            "Estado actualizado: {$document->sunat_status}. " . ($document->sunat_description ?? '')
        );
    }
}
