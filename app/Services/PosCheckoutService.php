<?php

namespace App\Services;

use App\Models\DocumentSeries;
use App\Models\InventoryLog;
use App\Models\Order;
use App\Models\Product;
use App\Services\Sunat\NubeFactService;
use App\Services\Sunat\SunatConfig;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosCheckoutService
{
    public function __construct(protected \App\Services\SalesService $salesService) {}

    public function completeCheckout(Order $order, array $payload): Order
    {
        if (!Auth::user()?->activeCashRegister) {
            throw ValidationException::withMessages([
                'general' => ['Debes abrir tu turno de caja antes de cobrar una venta.'],
            ]);
        }

        if ($order->status !== 'pending') {
            throw ValidationException::withMessages([
                'general' => ['Orden cerrada.'],
            ]);
        }

        $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
        $order->load('details.product.ingredients');

        $method = $payload['payment_method'];
        $received = $payload['received'];
        $change = $payload['change'];
        $clientId = $payload['client_id'] ?? null;
        $clientName = $payload['client_name'] ?? 'Público';
        $documentType = $payload['document_type'];
        $clientDocument = $payload['client_document'] ?? null;
        $nubefactConfigured = (new SunatConfig())->isNubefactConfigured();

        return DB::transaction(function () use ($order, $method, $received, $change, $clientId, $clientName, $documentType, $clientDocument, $nubefactConfigured) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $order->load('details.product.ingredients');

            $config = new SunatConfig();
            $igvFactor = $config->igvFactor();
            $isElectronic = in_array($documentType, ['Boleta', 'Factura'], true);

            $totalGravada = 0;
            $igv = 0;
            if ($isElectronic) {
                $totalGravada = round((float) $order->total / (1 + $igvFactor), 2);
                $igv = round((float) $order->total - $totalGravada, 2);
            }

            $serie = null;
            $correlativo = null;
            if ($isElectronic) {
                $tipo = $documentType === 'Factura' ? 'factura' : 'boleta';
                $areaId = $order->table()->value('area_id');
                $next = DocumentSeries::next($tipo, $areaId);
                $serie = $next['serie'];
                $correlativo = $next['correlativo'];
            }

            $order->update([
                'status' => 'completed',
                'issued_at' => now(),
                'payment_method' => $method,
                'received_amount' => $received,
                'change_amount' => $change,
                'document_type' => $documentType,
                'client_id' => $clientId,
                'client_name' => $clientName,
                'client_document' => $clientDocument,
                'cash_register_id' => Auth::user()->activeCashRegister->id ?? null,
                'serie' => $serie,
                'correlativo' => $correlativo,
                'subtotal' => $totalGravada,
                'igv' => $igv,
                'total_gravada' => $totalGravada,
                'sunat_status' => $isElectronic ? ($nubefactConfigured ? 'PENDING' : 'NO_ENVIADO') : 'NA',
            ]);

            $this->salesService->validateOrderForStock($order);
            $this->salesService->applyStockReductionForOrder($order, 'Venta POS');

            return $order->fresh();
        });
    }

    public function sendElectronicInvoiceIfNeeded(Order $order): void
    {
        if (!$order->isElectronic()) {
            return;
        }

        if (!(new SunatConfig())->isNubefactConfigured()) {
            return;
        }

        (new NubeFactService())->sendInvoice($order->fresh('details.product'));
    }
}
