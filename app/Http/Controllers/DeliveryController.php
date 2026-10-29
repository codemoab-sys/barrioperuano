<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Client;
use App\Models\Delivery;
use App\Models\DeliveryDriver;
use App\Models\DocumentSeries;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Services\DeliveryDirectService;
use App\Services\DeliveryService;
use App\Services\StockValidationService;
use App\Services\WingSauceMenu;
use App\Services\Sunat\SunatConfig;
use App\Services\Sunat\SunatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DeliveryController extends Controller
{
    public function __construct(
        protected DeliveryService $deliveryService,
        protected StockValidationService $stockValidationService,
        protected DeliveryDirectService $deliveryDirectService
    ) {}

    /* ══════════════════════════════════════════════
       INDEX — Panel Kanban
    ══════════════════════════════════════════════ */
    public function index()
    {
        $deliveries = Delivery::with(['order.details.product', 'driver', 'client'])
            ->today()
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('status');

        $drivers  = DeliveryDriver::where('is_active', true)->orderBy('name')->get();
        $currency = Setting::where('key', 'currency_symbol')->value('value') ?? 'S/';
        $statuses = Delivery::$statusLabels;

        // Contadores para las columnas del kanban
        $counts = [
            'pending'   => $deliveries->get('pending', collect())->count(),
            'preparing' => $deliveries->get('preparing', collect())->count(),
            'on_way'    => $deliveries->get('on_way', collect())->count(),
            'delivered' => $deliveries->get('delivered', collect())->count(),
        ];

        return view('delivery.index', compact('deliveries', 'drivers', 'currency', 'statuses', 'counts'));
    }

    /* ══════════════════════════════════════════════
       DIRECTO — Módulo en construcción (vacío)
    ══════════════════════════════════════════════ */
    public function directo()
    {
        $categories = Category::with(['products' => function ($q) {
            $q->where('is_active', true)->where('is_saleable', true);
        }])->where('is_active', true)->get();

        $currency = Setting::where('key', 'currency_symbol')->value('value') ?? 'S/';

        $clientsData = Client::select('id', 'name', 'document_number')->orderBy('name')->get()
            ->map(fn ($client) => [
                'id' => $client->id,
                'name' => $client->name,
                'document' => (string) ($client->document_number ?? ''),
            ])->values();

        $availableSeries = collect(['boleta', 'factura'])->mapWithKeys(function (string $documentType) {
            $serie = DocumentSeries::where('document_type', $documentType)
                ->whereNull('area_id')
                ->where('is_active', true)
                ->value('serie');

            return [$documentType => $serie];
        })->all();

        $igvFactor = (new SunatConfig())->igvFactor();
        $varios = $this->deliveryDirectService->variosClient();
        $variosData = $varios ? [
            'id' => $varios->id,
            'name' => $varios->name,
            'document' => (string) ($varios->document_number ?? ''),
        ] : null;

        return view('delivery.directo', compact('categories', 'currency', 'clientsData', 'availableSeries', 'igvFactor', 'variosData'));
    }

    /* ══════════════════════════════════════════════
       CREATE — Formulario nuevo pedido
    ══════════════════════════════════════════════ */
    public function create()
    {
        $categories = Category::with(['products' => function ($q) {
            $q->where('is_active', true)->where('is_saleable', true);
        }])->where('is_active', true)->get();

        $clients  = Client::orderBy('name')->get();
        $drivers  = DeliveryDriver::where('is_active', true)->orderBy('name')->get();
        $currency = Setting::where('key', 'currency_symbol')->value('value') ?? 'S/';

        return view('delivery.create', compact('categories', 'clients', 'drivers', 'currency'));
    }

    /* ══════════════════════════════════════════════
       STORE — Crear delivery + orden
    ══════════════════════════════════════════════ */
    public function store(Request $request)
    {
        $this->deliveryService->ensureCashRegisterIsOpen();

        $request->validate([
            'client_name'    => 'required|string|max:255',
            'client_phone'   => 'required|string|max:30',
            'address'        => 'required|string',
            'payment_method' => 'required|in:transfer,yape,plin',
            'delivery_fee'   => 'nullable|numeric|min:0|max:999999.99',
            'products'       => 'required|array|min:1',
            'products.*.id'  => 'required|exists:bape_products,id',
            'products.*.qty' => 'required|integer|min:1',
        ]);

        $products = $this->validateSauceChoices($request->input('products'));

        $this->stockValidationService->validateItems($products);

        DB::transaction(function () use ($request, $products) {
            $this->deliveryService->createPendingDelivery($products, [
                'client_id'      => $request->client_id ?: null,
                'client_name'    => $request->client_name,
                'client_phone'   => $request->client_phone,
                'address'        => $request->address,
                'reference'      => $request->reference,
                'driver_id'      => $request->driver_id ?: null,
                'payment_method' => $request->payment_method,
                'delivery_fee'   => (float) ($request->delivery_fee ?? 0),
                'notes'          => $request->notes,
                'scheduled_at'   => $request->scheduled_at ?: null,
            ]);
        });

        return redirect()->route('delivery.index')->with('success', '¡Pedido de delivery creado correctamente!');
    }

    /* ══════════════════════════════════════════════
       STORE DIRECTO — Pedido de delivery cobrado al instante
    ══════════════════════════════════════════════ */
    public function storeDirecto(Request $request)
    {
        $this->deliveryService->ensureCashRegisterIsOpen();

        $validated = $request->validate([
            'client_phone'    => ['required', 'string', 'max:30'],
            'client_name'     => ['nullable', 'string', 'max:255'],
            'address'         => ['nullable', 'string'],
            'delivery_fee'    => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'products'        => ['required', 'array', 'min:1'],
            'products.*.id'   => ['required', 'exists:bape_products,id'],
            'products.*.qty'  => ['required', 'integer', 'min:1'],
            'payment_mode'    => ['required', 'in:now,later'],
            'document_type'   => ['required_if:payment_mode,now', 'nullable', 'in:Ticket,Boleta,Factura'],
            'payment_method'  => ['required', 'in:cash,card,transfer,yape,plin'],
            'received_amount' => ['nullable', 'numeric', 'min:0'],
            'client_id'       => ['nullable', 'integer', 'exists:bape_clients,id'],
        ], [
            'client_phone.required'    => 'Ingresa el celular del cliente.',
            'products.required'        => 'Agrega al menos un producto al pedido.',
            'products.min'             => 'Agrega al menos un producto al pedido.',
            'document_type.required_if' => 'Selecciona el tipo de documento.',
        ]);

        $products = $this->validateSauceChoices($request->input('products'));

        $this->stockValidationService->validateItems($products);

        $clientName = trim((string) ($validated['client_name'] ?? '')) ?: 'Cliente - Varios';

        $deliveryData = [
            'client_id'      => $validated['client_id'] ?? null,
            'client_name'    => $clientName,
            'client_phone'   => $validated['client_phone'],
            'address'        => (string) ($validated['address'] ?? ''),
            'reference'      => null,
            'driver_id'      => null,
            'delivery_fee'   => (float) ($validated['delivery_fee'] ?? 0),
            'notes'          => null,
            'scheduled_at'   => null,
            'payment_method' => $validated['payment_method'],
        ];

        // ── Cobrar después: el pedido queda pendiente de cobro ──
        if ($validated['payment_mode'] === 'later') {
            $delivery = $this->deliveryDirectService->createPending($products, $deliveryData);
            $message = '¡Pedido de delivery directo creado! Queda pendiente de cobro.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success'       => true,
                    'message'       => $message,
                    'order_id'      => $delivery->order_id,
                    'delivery_id'   => $delivery->id,
                    'pending'       => true,
                    'pre_order_url' => route('delivery.pre-order', $delivery),
                ]);
            }

            return redirect()->route('delivery.index')->with('success', $message);
        }

        // ── Cobrar ahora ──
        [$delivery, $order] = $this->deliveryDirectService->createAndCharge($products, $deliveryData, [
            'document_type'   => $validated['document_type'],
            'payment_method'  => $validated['payment_method'],
            'received_amount' => $validated['received_amount'] ?? null,
            'client_id'       => $validated['client_id'] ?? null,
        ]);

        $message = '¡Pedido de delivery directo creado y cobrado correctamente!';

        if ($request->expectsJson()) {
            return response()->json([
                'success'       => true,
                'message'       => $message,
                'order_id'      => $order->id,
                'delivery_id'   => $delivery->id,
                'pre_order_url' => route('delivery.pre-order', $delivery),
            ]);
        }

        return redirect()->route('delivery.index')->with('success', $message);
    }

    /**
     * Valida (y normaliza) los sabores de los combos de alitas de cada producto.
     */
    private function validateSauceChoices(array $products): array
    {
        foreach ($products as $index => &$item) {
            $product = Product::with('category')->findOrFail($item['id']);
            $flavorLimit = WingSauceMenu::flavorLimit($product);
            if ($flavorLimit === null) {
                $item['sauce_choices'] = [];
                continue;
            }

            $validator = Validator::make(
                ['sauce_choices' => $item['sauce_choices'] ?? null],
                [
                    'sauce_choices' => ['required', 'array', 'min:1', 'max:' . $flavorLimit],
                    'sauce_choices.*' => ['required', 'string', 'distinct', Rule::in(WingSauceMenu::sauces())],
                ],
                [
                    'sauce_choices.required' => 'Selecciona los sabores del combo de alitas.',
                    'sauce_choices.min' => 'Selecciona al menos un sabor.',
                    'sauce_choices.max' => 'Este combo permite como máximo :max sabores.',
                    'sauce_choices.*.distinct' => 'No repitas el mismo sabor.',
                    'sauce_choices.*.in' => 'Uno de los sabores seleccionados no es válido.',
                ]
            );

            if ($validator->fails()) {
                throw ValidationException::withMessages([
                    "products.{$index}.sauce_choices" => $validator->errors()->first(),
                ]);
            }

            $item['sauce_choices'] = array_values($item['sauce_choices']);
        }
        unset($item);

        return $products;
    }

    /* ══════════════════════════════════════════════
       SHOW — Detalle del pedido
    ══════════════════════════════════════════════ */
    public function show(Delivery $delivery)
    {
        $delivery->load(['order.details.product', 'driver', 'client', 'user']);
        $drivers  = DeliveryDriver::where('is_active', true)->orderBy('name')->get();
        $currency = Setting::where('key', 'currency_symbol')->value('value') ?? 'S/';
        $statuses = Delivery::$statusLabels;

        return view('delivery.show', compact('delivery', 'drivers', 'currency', 'statuses'));
    }

    /* ══════════════════════════════════════════════
       PRE-ORDER — Pre-orden imprimible para cocina
    ══════════════════════════════════════════════ */
    public function preOrder(Delivery $delivery)
    {
        $delivery->load(['order.details.product', 'driver']);
        $settings = Setting::pluck('value', 'key')->toArray();

        return view('delivery.pre_order', compact('delivery', 'settings'));
    }

    /* ══════════════════════════════════════════════
       UPDATE STATUS — AJAX
    ══════════════════════════════════════════════ */
    public function updateStatus(Request $request, Delivery $delivery)
    {
        $status = $request->validate([
            'status' => 'required|in:pending,preparing,on_way,delivered,cancelled',
        ])['status'];

        if ($status === 'delivered') {
            $order = $delivery->order;

            // Solo se marca como entregado si el pedido ya está cobrado: en el flujo
            // normal el cobro ocurre en el checkout, y en "delivery directo" el pedido
            // nace pagado (queda pendiente solo la entrega).
            if (!$order || $order->status !== 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Un delivery solo se marca como entregado al completar el cobro.',
                ], 422);
            }
        }

        if ($status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Usa la acción de cancelar para liberar correctamente el pedido y su inventario.',
            ], 422);
        }

        if (in_array($delivery->status, ['delivered', 'cancelled'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Este delivery ya está cerrado y no puede cambiar de estado.',
            ], 422);
        }

        $delivery->update([
            'status'       => $status,
            'delivered_at' => $delivery->delivered_at,
        ]);

        return response()->json([
            'success' => true,
            'label'   => Delivery::$statusLabels[$status],
            'color'   => Delivery::$statusColors[$status],
        ]);
    }

    /* ══════════════════════════════════════════════
       ASSIGN DRIVER — AJAX
    ══════════════════════════════════════════════ */
    public function assignDriver(Request $request, Delivery $delivery)
    {
        $request->validate(['driver_id' => 'nullable|exists:delivery_drivers,id']);
        $delivery->update(['driver_id' => $request->driver_id ?: null]);

        $driver = $delivery->driver;
        return response()->json([
            'success'     => true,
            'driver_name' => $driver ? $driver->name : 'Sin asignar',
        ]);
    }

    /* ══════════════════════════════════════════════
       CHECKOUT — Cobrar y cerrar
    ══════════════════════════════════════════════ */
    public function checkout(Request $request, Delivery $delivery)
    {
        $this->deliveryService->ensureCashRegisterIsOpen();

        if ($delivery->status === 'delivered' || $delivery->status === 'cancelled') {
            return redirect()->route('delivery.show', $delivery)->with('error', 'Este pedido ya fue cerrado.');
        }

        $validated = $request->validate([
            'received_amount' => ['required', 'numeric', 'min:0'],
        ]);
        $totalToCollect = $delivery->total_with_fee;
        $receivedAmount = (float) $validated['received_amount'];
        if ($receivedAmount < $totalToCollect) {
            return back()->with('error', 'El monto recibido es insuficiente.');
        }

        $documentType = $request->input('document_type', 'Ticket');
        if (!in_array($documentType, ['Ticket', 'Boleta', 'Factura'], true)) {
            return back()->with('error', 'Tipo de comprobante inválido.');
        }

        $seriesAreaId = null;
        if (in_array($documentType, ['Boleta', 'Factura'], true)) {
            $seriesKey = $documentType === 'Factura' ? 'factura' : 'boleta';
            $series = DocumentSeries::where('document_type', $seriesKey)
                ->where('is_active', true)
                ->orderByRaw('CASE WHEN area_id IS NULL THEN 0 ELSE 1 END')
                ->orderBy('area_id')
                ->orderBy('id')
                ->first();

            if (!$series) {
                return back()->with(
                    'error',
                    "No hay una serie activa configurada para {$documentType}. Configúrala en Configuración antes de cobrar."
                );
            }

            // Delivery no pertenece a una zona de mesas: usa la primera serie activa.
            $seriesAreaId = $series->area_id;
        }

        $client = $delivery->client;
        if ($documentType === 'Factura') {
            $clientDocument = preg_replace('/\D/', '', (string) ($client?->document_number ?? ''));
            if (strlen($clientDocument) !== 11 || !$client?->name) {
                return back()->with('error', 'Para emitir Factura el cliente debe tener RUC y razón social válidos.');
            }
        }

        $nubefactConfigured = (new SunatConfig())->isNubefactConfigured();

        $closed = DB::transaction(function () use ($delivery, $documentType, $client, $receivedAmount, $totalToCollect, $nubefactConfigured, $seriesAreaId) {
            // Orden de bloqueo común Order → Delivery (idéntico al de cancel). Revalida los
            // estados bajo candado: un checkout nunca puede resucitar un delivery cancelado
            // ni completar dos veces la misma orden. Sin re-check, una cancelación que ya
            // restauró inventario comercial y de cocina sería sobrescrita por este flujo.
            $order = Order::whereKey($delivery->order_id)->lockForUpdate()->firstOrFail();
            $delivery = Delivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail();

            if ($order->status !== 'pending' || in_array($delivery->status, ['delivered', 'cancelled'], true)) {
                return false;
            }

            $isElectronic = in_array($documentType, ['Boleta', 'Factura'], true);
            $total = round((float) $order->total + (float) $delivery->delivery_fee, 2);
            $igvFactor = (new SunatConfig())->igvFactor();
            $totalGravada = $isElectronic ? round($total / (1 + $igvFactor), 2) : 0;
            $igv = $isElectronic ? round($total - $totalGravada, 2) : 0;
            $series = $isElectronic
                ? DocumentSeries::next($documentType === 'Factura' ? 'factura' : 'boleta', $seriesAreaId)
                : null;

            // Marcar orden como completada
            $order->update([
                'status'          => 'completed',
                'issued_at'       => now(),
                'issued_by_user_id'=> Auth::id(),
                'total'           => $total,
                'payment_method'  => $delivery->payment_method,
                'received_amount' => $receivedAmount,
                'change_amount'   => round($receivedAmount - $totalToCollect, 2),
                'document_type'   => $documentType,
                'client_id'       => $client?->id,
                'client_name'     => $client?->name ?: $delivery->client_name,
                'client_document' => $client?->document_number,
                'cash_register_id'=> $this->deliveryService->getCashRegisterId(),
                'serie'           => $series['serie'] ?? null,
                'correlativo'     => $series['correlativo'] ?? null,
                'subtotal'        => $totalGravada,
                'igv'              => $igv,
                'total_gravada'    => $totalGravada,
                'sunat_status'    => $isElectronic ? ($nubefactConfigured ? 'PENDING' : 'NO_ENVIADO') : 'NA',
            ]);

            // Marcar delivery como entregado
            $delivery->update([
                'status'       => 'delivered',
                'delivered_at' => now(),
            ]);

            return true;
        });

        if (!$closed) {
            return redirect()->route('delivery.show', $delivery)->with('error', 'Este pedido ya fue cerrado.');
        }

        if ($documentType !== 'Ticket' && $nubefactConfigured) {
            $order = $delivery->order->fresh('details.product');
            try {
                (new SunatService())->sendInvoice($order);
            } catch (\Throwable $e) {
                Log::error('Error al enviar delivery a NubeFact', [
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

        return redirect()->route('delivery.index')->with('success', 'Pedido entregado y cobrado correctamente.');
    }

    /* ══════════════════════════════════════════════
       CANCEL — Cancelar
    ══════════════════════════════════════════════ */
    public function cancel(Delivery $delivery)
    {
        if (in_array($delivery->status, ['delivered', 'cancelled'])) {
            return redirect()->back()->with('error', 'No se puede cancelar este pedido.');
        }

        $order = $delivery->order()->with('details.product.ingredients')->firstOrFail();

        $cancelled = DB::transaction(function () use ($delivery, $order) {
            // Orden de bloqueo consistente Order → Delivery (mismo orden relativo que checkout,
            // cuyo primer candado sobre estas entidades es el update de la orden antes que el
            // del delivery). Revalida los estados bajo candado: una cancelación nunca debe
            // restaurar inventario comercial ni de cocina de un pedido ya entregado/completado.
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $lockedDelivery = Delivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail();

            if (
                in_array($lockedDelivery->status, ['delivered', 'cancelled'], true)
                || $lockedOrder->status !== 'pending'
            ) {
                return false;
            }

            $this->deliveryService->restoreInventoryForOrder($lockedOrder);
            $this->deliveryService->returnKitchenForOrderIfConsumed($lockedOrder);
            $lockedDelivery->update(['status' => 'cancelled']);
            $lockedOrder->update(['status' => 'cancelled']);

            return true;
        });

        if (!$cancelled) {
            return redirect()->back()->with('error', 'No se puede cancelar este pedido.');
        }

        return redirect()->route('delivery.index')->with('success', 'Pedido cancelado.');
    }

    /* ══════════════════════════════════════════════
       DRIVERS — Gestión rápida de repartidores
    ══════════════════════════════════════════════ */
    public function driversIndex()
    {
        $drivers = DeliveryDriver::withCount('deliveries')->orderBy('name')->get();
        return view('delivery.drivers', compact('drivers'));
    }

    public function driversStore(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255', 'phone' => 'nullable|string|max:30']);
        DeliveryDriver::create($request->only('name', 'phone') + ['is_active' => true]);
        return redirect()->back()->with('success', 'Repartidor agregado.');
    }

    public function driversUpdate(Request $request, DeliveryDriver $driver)
    {
        $request->validate(['name' => 'required|string|max:255', 'phone' => 'nullable|string|max:30']);
        $driver->update($request->only('name', 'phone', 'is_active'));
        return redirect()->back()->with('success', 'Repartidor actualizado.');
    }

    public function driversDestroy(DeliveryDriver $driver)
    {
        $driver->delete();
        return redirect()->back()->with('success', 'Repartidor eliminado.');
    }
}
