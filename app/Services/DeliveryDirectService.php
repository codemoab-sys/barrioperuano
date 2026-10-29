<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Delivery;
use App\Models\DocumentSeries;
use App\Models\Order;
use App\Services\Sunat\SunatConfig;
use App\Services\Sunat\SunatService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Flujo "Delivery directo": pedidos que entran por teléfono y se cobran en el
 * momento (creación + cobro en una sola transacción). Reutiliza el servicio de
 * delivery para la creación del pedido y la lógica de cobro del POS para los
 * totales, el surcharge de tarjeta y el comprobante electrónico.
 */
class DeliveryDirectService
{
    /**
     * Documento del cliente "varios" que se asigna automáticamente a las notas de venta.
     */
    public const VARIOS_DOCUMENT = '999999999';

    public function __construct(
        protected DeliveryService $deliveryService,
        protected StockValidationService $stockValidationService,
        protected PosCheckoutService $posCheckoutService
    ) {}

    public function variosClient(): ?Client
    {
        return Client::where('document_number', self::VARIOS_DOCUMENT)->first();
    }

    /**
     * Crea el pedido de delivery directo SIN cobrarlo: queda pendiente de cobro y
     * se liquida luego desde el detalle del delivery (mismo camino que el flujo
     * normal). El total de la orden es el subtotal; el costo de delivery se suma
     * en el checkout, igual que en el delivery normal, para no duplicarlo.
     */
    public function createPending(array $products, array $data): Delivery
    {
        return DB::transaction(function () use ($products, $data) {
            return $this->deliveryService->createPendingDelivery($products, $data);
        });
    }

    /**
     * Crea el pedido de delivery directo y lo cobra. Todo ocurre en una única
     * transacción: si el cobro falla (sin serie activa, monto insuficiente,
     * factura sin RUC) no queda pedido ni descuento de stock en la base.
     *
     * @return array{0: Delivery, 1: Order}
     */
    public function createAndCharge(array $products, array $data, array $payment): array
    {
        $documentType = $payment['document_type'];
        $method = $payment['payment_method'];

        $client = $this->resolveClient($documentType, $payment, $data);
        $series = $this->resolveSeries($documentType);

        $config = new SunatConfig();
        $igvFactor = $config->igvFactor();
        $isElectronic = in_array($documentType, ['Boleta', 'Factura'], true);
        $nubefactConfigured = $config->isNubefactConfigured();

        [$delivery, $order] = DB::transaction(function () use (
            $products,
            $data,
            $payment,
            $method,
            $documentType,
            $client,
            $series,
            $igvFactor,
            $isElectronic,
            $nubefactConfigured
        ) {
            $delivery = $this->deliveryService->createPendingDelivery($products, array_merge($data, [
                'client_id'    => $documentType === 'Ticket' ? null : ($client?->id ?? null),
                'payment_method' => $method,
            ]));

            $order = $delivery->order;
            $baseTotal = round((float) $order->total + (float) $delivery->delivery_fee, 2);
            $cardSurcharge = $method === 'card'
                ? $this->posCheckoutService->cardSurcharge($baseTotal)
                : 0.0;
            $finalTotal = round($baseTotal + $cardSurcharge, 2);

            $received = $method === 'cash'
                ? (float) ($payment['received_amount'] ?? 0)
                : $finalTotal;

            if ($method === 'cash' && $received < $finalTotal) {
                throw ValidationException::withMessages([
                    'general' => ['El monto recibido es insuficiente.'],
                ]);
            }

            $totalGravada = $isElectronic ? round($baseTotal / (1 + $igvFactor), 2) : 0;
            $igv = $isElectronic ? round($baseTotal - $totalGravada, 2) : 0;

            $order->update([
                'status'            => 'completed',
                'issued_at'         => now(),
                'issued_by_user_id' => Auth::id(),
                'total'             => $finalTotal,
                'payment_method'    => $method,
                'received_amount'   => $received,
                'change_amount'     => round($received - $finalTotal, 2),
                'document_type'     => $documentType,
                'client_id'         => $client?->id,
                'client_name'       => $client?->name ?: ($data['client_name'] ?? 'Público'),
                'client_document'   => $client?->document_number,
                'cash_register_id'  => $this->deliveryService->getCashRegisterId(),
                'serie'             => $series['serie'] ?? null,
                'correlativo'       => $series['correlativo'] ?? null,
                'subtotal'          => $totalGravada,
                'igv'               => $igv,
                'total_gravada'     => $totalGravada,
                'sunat_status'      => $isElectronic
                    ? ($nubefactConfigured ? 'PENDING' : 'NO_ENVIADO')
                    : 'NA',
            ]);

            // El delivery queda "pending": el pedido ya está cobrado, pero la
            // entrega sigue su curso en el kanban (preparando → en camino → entregado).
            return [$delivery->fresh('order'), $order->fresh()];
        });

        if ($documentType !== 'Ticket' && $nubefactConfigured) {
            try {
                (new SunatService())->sendInvoice($order->fresh('details.product'));
            } catch (\Throwable $e) {
                Log::error('Error al enviar delivery directo a NubeFact', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
                $order->update([
                    'sunat_status' => 'ERROR',
                    'sunat_code' => '500',
                    'sunat_description' => $e->getMessage(),
                ]);
            }
        }

        return [$delivery, $order];
    }

    /**
     * Nota de venta → cliente "varios" (999999999). Boleta/Factura → cliente
     * elegido por el usuario.
     */
    private function resolveClient(string $documentType, array $payment, array $data): ?Client
    {
        if ($documentType === 'Ticket') {
            $varios = $this->variosClient();
            if ($varios) {
                return $varios;
            }

            return null;
        }

        $client = isset($payment['client_id']) && $payment['client_id']
            ? Client::find($payment['client_id'])
            : null;

        if ($documentType === 'Factura') {
            $document = preg_replace('/\D/', '', (string) ($client?->document_number ?? ''));
            if (strlen($document) !== 11) {
                throw ValidationException::withMessages([
                    'general' => ['Para emitir Factura el cliente debe tener RUC de 11 dígitos.'],
                ]);
            }
            if (empty(trim((string) ($client?->name ?? '')))) {
                throw ValidationException::withMessages([
                    'general' => ['Para emitir Factura debe indicar la razón social del cliente.'],
                ]);
            }
            if (trim((string) ($client?->address ?? '')) === '' && trim((string) ($data['address'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    'general' => ['Para emitir Factura debe indicar la dirección del cliente.'],
                ]);
            }
        }

        return $client;
    }

    /**
     * Boleta/Factura requieren una serie activa; la nota de venta no usa serie.
     */
    private function resolveSeries(string $documentType): ?array
    {
        if (!in_array($documentType, ['Boleta', 'Factura'], true)) {
            return null;
        }

        $seriesKey = $documentType === 'Factura' ? 'factura' : 'boleta';
        $series = DocumentSeries::where('document_type', $seriesKey)
            ->whereNull('area_id')
            ->where('is_active', true)
            ->first();

        if (!$series) {
            throw ValidationException::withMessages([
                'general' => ["No hay una serie activa configurada para {$documentType}. Configúrala en Configuración antes de cobrar."],
            ]);
        }

        // El delivery no pertenece a una zona de mesas: usa la serie global activa.
        return DocumentSeries::next($seriesKey);
    }
}
