<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Client;
use App\Models\Delivery;
use App\Models\DeliveryDriver;
use App\Models\DocumentSeries;
use App\Models\InventoryLog;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Setting;
use App\Services\DeliveryService;
use App\Services\SalesService;
use App\Services\StockValidationService;
use App\Services\Sunat\SunatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DeliveryController extends Controller
{
    public function __construct(
        protected DeliveryService $deliveryService,
        protected StockValidationService $stockValidationService,
        protected SalesService $salesService
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
            'payment_method' => 'required|in:cash,card,transfer',
            'products'       => 'required|array|min:1',
            'products.*.id'  => 'required|exists:bape_products,id',
            'products.*.qty' => 'required|integer|min:1',
        ]);

        $this->stockValidationService->validateItems($request->products);

        DB::transaction(function () use ($request) {
            // 1. Crear la Orden (sin mesa — delivery)
            $order = Order::create([
                'table_id'       => null,
                'user_id'        => Auth::id(),
                'client_id'      => $request->client_id ?: null,
                'client_name'    => $request->client_name,
                'status'         => 'pending',
                'total'          => 0,
                'cash_register_id' => $this->deliveryService->getCashRegisterId(),
            ]);

            // 2. Agregar productos a la orden
            $subtotal = 0;
            foreach ($request->products as $item) {
                $product = Product::findOrFail($item['id']);
                $line    = $product->price * $item['qty'];
                $subtotal += $line;

                OrderDetail::create([
                    'order_id'   => $order->id,
                    'product_id' => $product->id,
                    'quantity'   => $item['qty'],
                    'price'      => $product->price,
                    'status'     => 'pending',
                    'note'       => $item['note'] ?? null,
                ]);
            }

            $deliveryFee = (float) ($request->delivery_fee ?? 0);
            $order->update(['total' => $subtotal]);

            // 3. Crear el Delivery
            Delivery::create([
                'order_id'        => $order->id,
                'client_id'       => $request->client_id ?: null,
                'client_name'     => $request->client_name,
                'client_phone'    => $request->client_phone,
                'address'         => $request->address,
                'reference'       => $request->reference,
                'driver_id'       => $request->driver_id ?: null,
                'user_id'         => Auth::id(),
                'cash_register_id'=> $this->deliveryService->getCashRegisterId(),
                'status'          => 'pending',
                'payment_method'  => $request->payment_method,
                'delivery_fee'    => $deliveryFee,
                'notes'           => $request->notes,
                'scheduled_at'    => $request->scheduled_at ?: null,
            ]);
        });

        return redirect()->route('delivery.index')->with('success', '¡Pedido de delivery creado correctamente!');
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
       UPDATE STATUS — AJAX
    ══════════════════════════════════════════════ */
    public function updateStatus(Request $request, Delivery $delivery)
    {
        $status = $request->validate([
            'status' => 'required|in:pending,preparing,on_way,delivered,cancelled',
        ])['status'];

        if ($status === 'delivered') {
            return response()->json([
                'success' => false,
                'message' => 'Un delivery solo se marca como entregado al completar el cobro.',
            ], 422);
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

        $documentType = $request->input('document_type', 'Ticket');
        if (!in_array($documentType, ['Ticket', 'Boleta', 'Factura'], true)) {
            return back()->with('error', 'Tipo de comprobante inválido.');
        }

        $client = $delivery->client;
        if ($documentType === 'Factura') {
            $clientDocument = preg_replace('/\D/', '', (string) ($client?->document_number ?? ''));
            if (strlen($clientDocument) !== 11 || !$client?->name) {
                return back()->with('error', 'Para emitir Factura el cliente debe tener RUC y razón social válidos.');
            }
        }

        DB::transaction(function () use ($delivery, $request, $documentType, $client) {
            $order = $delivery->order;

            $isElectronic = in_array($documentType, ['Boleta', 'Factura'], true);
            $series = $isElectronic
                ? DocumentSeries::next($documentType === 'Factura' ? 'factura' : 'boleta')
                : null;

            // Marcar orden como completada
            $order->update([
                'status'          => 'completed',
                'payment_method'  => $delivery->payment_method,
                'received_amount' => $request->received_amount ?? $delivery->total_with_fee,
                'change_amount'   => max(0, ($request->received_amount ?? 0) - $delivery->total_with_fee),
                'document_type'   => $documentType,
                'client_id'       => $client?->id,
                'client_name'     => $client?->name ?: $delivery->client_name,
                'client_document' => $client?->document_number,
                'cash_register_id'=> $this->deliveryService->getCashRegisterId(),
                'serie'           => $series['serie'] ?? null,
                'correlativo'     => $series['correlativo'] ?? null,
                'sunat_status'    => $isElectronic ? 'PENDING' : 'NA',
            ]);

            $this->salesService->applyStockReductionForOrder($order, 'Delivery');

            // Marcar delivery como entregado
            $delivery->update([
                'status'       => 'delivered',
                'delivered_at' => now(),
            ]);
        });

        if ($documentType !== 'Ticket') {
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

        $order = $delivery->order()->with('details.product.ingredients')->first();

        DB::transaction(function () use ($delivery, $order) {
            $this->deliveryService->restoreInventoryForOrder($order);
            $delivery->update(['status' => 'cancelled']);
            $order->update(['status' => 'cancelled']);
        });

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
