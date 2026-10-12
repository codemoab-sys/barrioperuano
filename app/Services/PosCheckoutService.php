<?php

namespace App\Services;

use App\Models\DocumentSeries;
use App\Models\CashRegister;
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

    public function cardSurcharge(float $total): float
    {
        $igvFactor = (new SunatConfig())->igvFactor();
        $totalGravada = round($total / (1 + $igvFactor), 2);

        return round($totalGravada * 0.05, 2);
    }

    public function totalForPayment(float $total, string $paymentMethod): float
    {
        return round($total + ($paymentMethod === 'card' ? $this->cardSurcharge($total) : 0), 2);
    }

    public function completeCheckout(Order $order, array $payload): Order
    {
        $cashRegister = CashRegister::current();
        if (!$cashRegister) {
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

        return DB::transaction(function () use ($order, $method, $received, $change, $clientId, $clientName, $documentType, $clientDocument, $nubefactConfigured, $cashRegister) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $order->load('details.product.ingredients');

            $config = new SunatConfig();
            $igvFactor = $config->igvFactor();
            $isElectronic = in_array($documentType, ['Boleta', 'Factura'], true);
            $baseTotal = round((float) $order->total, 2);
            $cardSurcharge = $method === 'card'
                ? round(round($baseTotal / (1 + $igvFactor), 2) * 0.05, 2)
                : 0;
            $finalTotal = round($baseTotal + $cardSurcharge, 2);

            $totalGravada = 0;
            $igv = 0;
            if ($isElectronic) {
                $totalGravada = round($baseTotal / (1 + $igvFactor), 2);
                $igv = round($baseTotal - $totalGravada, 2);
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
                'issued_by_user_id' => Auth::id(),
                'payment_method' => $method,
                'total' => $finalTotal,
                'received_amount' => $received,
                'change_amount' => $change,
                'document_type' => $documentType,
                'client_id' => $clientId,
                'client_name' => $clientName,
                'client_document' => $clientDocument,
                'cash_register_id' => $cashRegister->id,
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
