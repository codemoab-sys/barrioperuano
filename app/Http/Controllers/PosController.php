<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Table;
use App\Models\Category;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\InventoryLog;
use App\Models\Client;
use App\Models\Setting;
use App\Models\DocumentSeries;
use App\Models\CashRegister;
use App\Services\PosCheckoutService;
use App\Services\StockValidationService;
use App\Services\WingSauceMenu;
use App\Services\Sunat\SunatConfig;
use App\Services\Sunat\NubeFactService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class PosController extends Controller
{
    public function __construct(
        protected PosCheckoutService $posCheckoutService,
        protected StockValidationService $stockValidationService
    ) {}

    public function index()
    {
        $areas = Auth::user()->accessibleAreas()->with(['tables' => function($q) {
            $q->with(['orders' => function($q) {
                $q->where('status', 'pending');
            }, 'reservations' => function($q) {
                $q->where('status', 'confirmed')
                  ->whereDate('reservation_time', Carbon::today())
                  ->where('reservation_time', '>=', Carbon::now()->subHours(2))
                  ->orderBy('reservation_time', 'asc');
            }]);
        }])->where('is_active', true)->get();

        $currency = Setting::where('key', 'currency_symbol')->value('value') ?? 'S/';

        // Últimas 5 ventas para el panel de "Últimas Ventas"
        $lastSales = Order::where('status', 'completed')
            ->with('client')
            ->orderByRaw('COALESCE(issued_at, created_at) DESC')
            ->take(5)
            ->get();

        return view('pos.index', compact('areas', 'currency', 'lastSales'));
    }

    public function order(Table $table)
    {
        $this->authorizeTableAccess($table);

        // Stock reservado en órdenes pendientes de MESAS (no delivery)
        $reservedMap = DB::table('bape_order_details')
            ->join('bape_orders', 'bape_orders.id', '=', 'bape_order_details.order_id')
            ->where('bape_orders.status', 'pending')
            ->whereNotNull('bape_orders.table_id')
            ->select('bape_order_details.product_id', DB::raw('SUM(bape_order_details.quantity) as reserved'))
            ->groupBy('bape_order_details.product_id')
            ->pluck('reserved', 'product_id');

        // Filtro: Solo productos activos y vendibles con stock base
        $categories = Category::with(['products' => function($q) {
            $q->where('is_active', true)
              ->where('is_saleable', true)
              ->where(fn($q2) => $q2->where('stock', '>', 0)->orWhereNull('stock'));
        }])->where('is_active', true)->get();

        // Ocultar productos sin stock disponible (descontando reservas)
        $categories->each(function($category) use ($reservedMap) {
            $category->products = $category->products->filter(function($product) use ($reservedMap) {
                if (is_null($product->stock)) return true;
                $reserved = $reservedMap->get($product->id, 0);
                return ($product->stock - $reserved) > 0;
            });
        });

        $order = Order::where('table_id', $table->id)->where('status', 'pending')->with('details.product.category')->first();
        $occupiedTableIds = Order::where('status', 'pending')->pluck('table_id');
        $freeTables = Table::whereNotIn('id', $occupiedTableIds)
            ->where('id', '!=', $table->id)
            ->whereHas('area', fn ($query) => $query->whereIn('id', Auth::user()->accessibleAreas()->select('id')))
            ->with('area')
            ->get();
        $clients = Client::select('id', 'name', 'document_number')->orderBy('name')->get();
        $currency = Setting::where('key', 'currency_symbol')->value('value') ?? 'S/';
        $clientsData = $clients->map(fn ($c) => [
            'id'       => $c->id,
            'name'     => $c->name,
            'document' => (string) ($c->document_number ?? ''),
        ])->values();
        $availableSeries = $this->availableSeriesForArea($table->area_id);

        $lastSales = Order::where('status', 'completed')
            ->with('client')
            ->orderByRaw('COALESCE(issued_at, created_at) DESC')
            ->take(5)
            ->get();

        return view('pos.order', compact('table', 'categories', 'order', 'freeTables', 'clients', 'currency', 'lastSales', 'clientsData', 'reservedMap', 'availableSeries'));
    }

    // --- AGREGAR POR CLIC (Normal) ---
    public function addToOrder(Request $request, Table $table)
    {
        if (!CashRegister::current()) {
            return response()->json(['error' => 'Debes abrir tu turno de caja antes de agregar productos.'], 422);
        }

        $this->authorizeTableOrder($table);
        $product = Product::findOrFail($request->product_id);
        $sauceChoices = $this->validatedSauceChoices($request, $product);
        if ($sauceChoices instanceof \Illuminate\Http\JsonResponse) {
            return $sauceChoices;
        }

        $error = $this->checkStockAvailability($product, $table);
        if ($error) {
            return response()->json(['error' => $error], 422);
        }

        $this->addItemToTable($table, $product, $sauceChoices);
        return $this->getCartHtml($table);
    }

    // --- AGREGAR POR CÓDIGO DE BARRAS / BÚSQUEDA (código, nombre o precio) ---
    public function addByBarcode(Request $request, Table $table)
    {
        if (!CashRegister::current()) {
            return response()->json(['error' => 'Debes abrir tu turno de caja antes de agregar productos.'], 422);
        }

        $this->authorizeTableOrder($table);
        $request->validate(['barcode' => 'required']);

        $query = trim($request->input('barcode'));

        // 1) Coincidencia exacta por código de barras
        $product = Product::where('barcode', $query)
                          ->where('is_active', true)
                          ->where('is_saleable', true)
                          ->where(fn($q) => $q->where('stock', '>', 0)->orWhereNull('stock'))
                          ->first();

        if ($product) {
            return $this->addProductFromBarcode($table, $product, $request);
        }

        // 2) Búsqueda por nombre (parcial)
        $matches = Product::whereRaw('name COLLATE utf8mb4_general_ci LIKE ?', ['%' . $query . '%'])
                          ->where('is_active', true)
                          ->where('is_saleable', true)
                          ->where(fn($q) => $q->where('stock', '>', 0)->orWhereNull('stock'))
                          ->limit(10)
                          ->get();

        if ($matches->count() === 0) {
            return response()->json(['error' => 'Producto no encontrado'], 404);
        }

        if ($matches->count() === 1) {
            return $this->addProductFromBarcode($table, $matches->first(), $request);
        }

        return response()->json([
            'matches' => $matches->map(fn ($p) => [
                'id'      => $p->id,
                'name'    => $p->name,
                'price'   => $p->price,
                'barcode' => $p->barcode,
            ]),
        ]);
    }

    private function addProductFromBarcode(Table $table, Product $product, Request $request)
    {
        $sauceChoices = $this->validatedSauceChoices($request, $product);
        if ($sauceChoices instanceof \Illuminate\Http\JsonResponse) {
            return $sauceChoices;
        }

        $error = $this->checkStockAvailability($product, $table);
        if ($error) {
            return response()->json(['error' => $error], 422);
        }

        $this->addItemToTable($table, $product, $sauceChoices);
        return $this->getCartHtml($table);
    }

    private function validatedSauceChoices(Request $request, Product $product): array|\Illuminate\Http\JsonResponse
    {
        $limit = WingSauceMenu::flavorLimit($product);
        if ($limit === null) {
            return [];
        }

        if (!$request->exists('sauce_choices')) {
            return response()->json([
                'requires_sauces' => true,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'max_sauces' => $limit,
            ]);
        }

        $choices = $request->input('sauce_choices');
        $validator = Validator::make(
            ['sauce_choices' => $choices],
            [
                'sauce_choices' => ['required', 'array', 'min:1', 'max:' . $limit],
                'sauce_choices.*' => [
                    'required',
                    'string',
                    'distinct',
                    Rule::in(WingSauceMenu::sauces()),
                ],
            ],
            [
                'sauce_choices.min' => 'Selecciona al menos un sabor.',
                'sauce_choices.max' => 'Este combo permite como máximo :max sabores.',
                'sauce_choices.*.distinct' => 'No repitas el mismo sabor.',
                'sauce_choices.*.in' => 'Uno de los sabores seleccionados no es válido.',
            ]
        );

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        return array_values($choices);
    }

    /**
     * Verifica si hay stock disponible para un producto.
     * Descuenta stock reservado en órdenes pendientes de TODAS las mesas.
     * Retorna string con mensaje de error o null si está OK.
     */
    private function checkStockAvailability(Product $product, Table $table): ?string
    {
        $product->loadMissing('ingredients');

        if (!is_null($product->stock)) {
            if ($product->stock <= 0) {
                return 'Sin stock disponible';
            }

            // Stock reservado en órdenes pendientes de MESAS (no delivery)
            $reserved = DB::table('bape_order_details')
                ->join('bape_orders', 'bape_orders.id', '=', 'bape_order_details.order_id')
                ->where('bape_orders.status', 'pending')
                ->whereNotNull('bape_orders.table_id')
                ->where('bape_order_details.product_id', $product->id)
                ->sum('bape_order_details.quantity');

            $available = $product->stock - $reserved;

            if ($available <= 0) {
                return 'Sin stock disponible. Todas las unidades están en otras mesas.';
            }

            // Verificar si la mesa actual ya tiene el producto y no puede sumar más
            $order = Order::where('table_id', $table->id)->where('status', 'pending')->first();
            if ($order) {
                $detail = $order->details()->where('product_id', $product->id)->first();
                if ($detail && $detail->quantity >= $available) {
                    return 'Stock insuficiente. Disponible: ' . $available;
                }
            }
        }

        // Si el producto tiene receta, validar que también haya stock disponible de cada insumo.
        foreach ($product->ingredients as $ingredient) {
            if (is_null($ingredient->stock)) {
                continue;
            }

            $requestedQty = 1;
            $order = Order::where('table_id', $table->id)->where('status', 'pending')->first();
            if ($order) {
                $detail = $order->details()->where('product_id', $product->id)->first();
                if ($detail) {
                    $requestedQty = $detail->quantity + 1;
                }
            }

            $requiredQty = (float) $ingredient->pivot->quantity * $requestedQty;

            $reservedIngredient = DB::table('bape_order_details')
                ->join('bape_orders', 'bape_orders.id', '=', 'bape_order_details.order_id')
                ->join('bape_product_ingredients', 'bape_product_ingredients.product_id', '=', 'bape_order_details.product_id')
                ->where('bape_orders.status', 'pending')
                ->where('bape_product_ingredients.ingredient_id', $ingredient->id)
                ->selectRaw('SUM(bape_order_details.quantity * bape_product_ingredients.quantity) as reserved')
                ->value('reserved');

            $availableIngredient = $ingredient->stock - ((float) ($reservedIngredient ?? 0));

            if ($availableIngredient < $requiredQty) {
                return 'Stock insuficiente para el ingrediente: ' . $ingredient->name;
            }
        }

        return null;
    }

    /**
     * Agrega producto al carrito dentro de una transacción.
     */
    private function addItemToTable(Table $table, Product $product, array $sauceChoices = [])
    {
        DB::transaction(function() use ($table, $product, $sauceChoices) {
            $order = Order::firstOrCreate(
                ['table_id' => $table->id, 'status' => 'pending'],
                ['user_id' => Auth::id() ?? 1, 'total' => 0]
            );

            $detail = $order->details()
                ->where('product_id', $product->id)
                ->get()
                ->first(fn ($item) => ($item->sauce_choices ?? []) === $sauceChoices);

            if ($detail) {
                $detail->increment('quantity');
            } else {
                $order->details()->create([
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'price' => $product->price,
                    'status' => 'pending',
                    'sauce_choices' => $sauceChoices ?: null,
                ]);
            }
            $this->recalculateTotal($order);
        });
    }

    // --- ACTUALIZAR CANTIDAD ---
    public function updateQuantity(Request $request, OrderDetail $detail)
    {
        $this->authorizePendingDetail($detail);
        $request->validate(['quantity' => ['required', 'integer', 'min:0']]);
        $newQty = $request->quantity;
        $order = $detail->order;

        if ($newQty < 1) {
            $detail->delete();
            $this->cleanupEmptyOrder($order);
        } else {
            $product = $detail->product;
            if (!is_null($product->stock)) {
                $reserved = DB::table('bape_order_details')
                    ->join('bape_orders', 'bape_orders.id', '=', 'bape_order_details.order_id')
                    ->where('bape_orders.status', 'pending')
                    ->whereNotNull('bape_orders.table_id')
                    ->where('bape_order_details.product_id', $product->id)
                    ->sum('bape_order_details.quantity');

                $available = $product->stock - $reserved + $detail->quantity;
                if ($newQty > $available) {
                    return response()->json(['error' => 'Stock insuficiente. Disponible: ' . $available], 422);
                }
            }
            $detail->update(['quantity' => $newQty]);
        }

        $this->recalculateTotal($order);
        return $this->getCartHtml($order->table()->firstOrFail());
    }

    public function updatePrice(Request $request, OrderDetail $detail)
    {
        $this->authorizePendingDetail($detail);
        $request->validate([
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        $detail->load('product.category');
        if (mb_strtolower(trim((string) $detail->product->category?->name)) !== 'servicio') {
            return response()->json([
                'error' => 'Solo se puede editar el precio de productos de la categoría Servicio.',
            ], 422);
        }

        $detail->update(['price' => round((float) $request->input('price'), 2)]);
        $this->recalculateTotal($detail->order);

        return $this->getCartHtml($detail->order->table()->firstOrFail());
    }

    // --- ACTUALIZAR NOTA ---
    public function updateNote(Request $request, OrderDetail $detail)
    {
        $this->authorizePendingDetail($detail);
        $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        $detail->update(['note' => $request->note]);
        return $this->getCartHtml($detail->order->table()->firstOrFail());
    }

    // --- ELIMINAR ITEM ---
    public function removeItem(OrderDetail $detail)
    {
        $this->authorizePendingDetail($detail);
        $order = $detail->order;
        $detail->delete();
        $this->cleanupEmptyOrder($order);
        $this->recalculateTotal($order);
        return $this->getCartHtml($order->table()->firstOrFail());
    }

    /**
     * Elimina la orden y libera la mesa si no quedan items.
     * Reactiva productos que tengan stock disponible.
     */
    private function cleanupEmptyOrder(Order $order): void
    {
        if ($order->details()->count() === 0) {
            $tableId = $order->table_id;
            $order->delete();

            // Reactivar productos que ahora tienen stock
            Product::where('is_saleable', false)
                ->where('stock', '>', 0)
                ->update(['is_saleable' => true]);
        }
    }

    // --- APLICAR DESCUENTO (Corregido para devolver HTML) ---
    public function applyDiscount(Request $request, Order $order)
    {
        $this->authorizePendingOrder($order);
        $request->validate([
            'discount' => ['required', 'numeric', 'min:0'],
        ]);
        $subtotal = $order->details()->get()->sum(fn ($detail) => $detail->price * $detail->quantity);
        if ((float) $request->discount > $subtotal) {
            return back()->with('error', 'El descuento no puede superar el subtotal.');
        }
        $order->discount = $request->input('discount', 0);
        $order->save();
        $this->recalculateTotal($order);
        return $this->getCartHtml($order->table()->firstOrFail());
    }

    public function moveTable(Request $request, Order $order) {
        $this->authorizePendingOrder($order);
        $request->validate(['target_table_id' => 'required|exists:bape_tables,id']);
        if (Order::where('table_id', $request->target_table_id)->where('status', 'pending')->exists()) return redirect()->back()->with('error', 'Ocupada.');
        $order->table_id = $request->target_table_id; $order->save();
        return redirect()->route('pos.order', $request->target_table_id);
    }

    public function getSplitContent(Order $order) { $this->authorizePendingOrder($order); return view('pos.partials.split_content', compact('order')); }
    public function checkoutModal(Order $order)
    {
        $this->authorizePendingOrder($order);
        $clients = Client::select('id', 'name', 'document_number')->orderBy('name')->get();
        $clientsData = $clients->map(fn ($client) => [
            'id' => $client->id,
            'name' => $client->name,
            'document' => (string) ($client->document_number ?? ''),
        ])->values();
        $availableSeries = $this->availableSeriesForArea($order->table()->value('area_id'));

        return view('pos.partials.checkout_modal', compact('order', 'clientsData', 'availableSeries'));
    }

    private function availableSeriesForArea(?int $areaId): array
    {
        return collect(['boleta', 'factura'])->mapWithKeys(function (string $documentType) use ($areaId) {
            $serie = DocumentSeries::where('document_type', $documentType)
                ->where('is_active', true)
                ->where(function ($query) use ($areaId) {
                    $query->where('area_id', $areaId)->orWhereNull('area_id');
                })
                ->orderByRaw('CASE WHEN area_id = ? THEN 0 ELSE 1 END', [$areaId])
                ->value('serie');

            return [$documentType => $serie];
        })->all();
    }
    public function processSplit(Request $request, Order $order) { $this->authorizePendingOrder($order); return redirect()->back(); }
    public function precheck(Order $order) { $this->authorizeOrder($order); $settings = Setting::pluck('value', 'key')->toArray(); return view('sales.ticket', compact('order', 'settings')); }
    public function kitchenTicket(Order $order) { $this->authorizeOrder($order); return view('sales.kitchen_ticket', compact('order')); }

    public function checkout(Request $request, Order $order)
    {
        $this->authorizePendingOrder($order);
        if($order->status !== 'pending') {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => 'Orden cerrada.'], 422)
                : redirect()->route('pos.index')->with('error', 'Orden cerrada.');
        }

        $request->validate([
            'payment_method' => ['required', 'in:cash,card,transfer,yape,plin'],
            'received_amount' => ['required_if:payment_method,cash', 'nullable', 'numeric', 'min:0'],
            'client_id' => ['nullable', 'integer', 'exists:bape_clients,id'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'client_document' => ['nullable', 'string', 'max:20'],
            'document_type' => ['required', 'in:Ticket,Boleta,Factura'],
        ]);

        $method = $request->input('payment_method');
        $received = $method === 'cash' ? (float) $request->input('received_amount') : (float) $order->total;
        if ($method === 'cash' && $received < (float) $order->total) {
            return back()->with('error', 'El monto recibido es insuficiente.');
        }
        $change = max(0, $received - $order->total);
        $clientId = $request->input('client_id');
        $client = $clientId ? Client::findOrFail($clientId) : null;
        $clientName = $client?->name ?: ($request->input('client_name') ?? 'Público');
        $documentType = $request->input('document_type');
        $clientDocument = $client?->document_number ?: $request->input('client_document');

        if (in_array($documentType, ['Boleta', 'Factura'], true)) {
            $config = new SunatConfig();
            $seriesKey = $documentType === 'Factura' ? 'factura' : 'boleta';
            $areaId = $order->table()->value('area_id');

            if (!DocumentSeries::where('document_type', $seriesKey)
                ->where('is_active', true)
                ->where(function ($query) use ($areaId) {
                    $query->where('area_id', $areaId)->orWhereNull('area_id');
                })
                ->exists()) {
                return $request->expectsJson()
                    ? response()->json(['success' => false, 'message' => 'No hay una serie activa configurada para ' . $documentType . '.'], 422)
                    : redirect()->back()->with('error', 'No hay una serie activa configurada para ' . $documentType . '.');
            }
        }

        // Validación específica para Factura (Perú): requiere RUC (11 dígitos) y razón social
        if ($documentType === 'Factura') {
            $doc = preg_replace('/\D/', '', (string) $clientDocument);
            $clientDocument = $doc;
            if (strlen($doc) !== 11) {
                return $request->expectsJson()
                    ? response()->json(['success' => false, 'message' => 'Para emitir Factura el cliente debe tener RUC de 11 dígitos.'], 422)
                    : redirect()->back()->with('error', 'Para emitir Factura el cliente debe tener RUC de 11 dígitos.');
            }
            if (empty(trim((string) $clientName)) || $clientName === 'Público') {
                return $request->expectsJson()
                    ? response()->json(['success' => false, 'message' => 'Para emitir Factura debe indicar la razón social del cliente.'], 422)
                    : redirect()->back()->with('error', 'Para emitir Factura debe indicar la razón social del cliente.');
            }
                $clientAddress = (string) ($client?->address ?? '');
                if (trim($clientAddress) === '') {
                    return $request->expectsJson()
                        ? response()->json(['success' => false, 'message' => 'Para emitir Factura debe indicar la dirección del cliente.'], 422)
                        : redirect()->back()->with('error', 'Para emitir Factura debe indicar la dirección del cliente.');
                }
        }

        // Track if NubeFact is configured for later use
        $nubefactConfigured = (new SunatConfig())->isNubefactConfigured();

        $order = $this->posCheckoutService->completeCheckout($order, [
            'payment_method' => $method,
            'received' => $received,
            'change' => $change,
            'client_id' => $clientId,
            'client_name' => $clientName,
            'client_document' => $clientDocument,
            'document_type' => $documentType,
        ]);

        $order->refresh();

        if ($order->isElectronic() && (new SunatConfig())->isNubefactConfigured()) {
            $this->posCheckoutService->sendElectronicInvoiceIfNeeded($order);
        }

        $order->refresh();
        $msg = 'Venta registrada.';
        if ($order->isElectronic()) {
            // Mostrar estado actual del envío a NubeFact
            if ($order->sunat_status === 'ACCEPTED') {
                $msg .= ' Comprobante ' . $order->full_number . ' aceptado por SUNAT.';
            } elseif ($order->sunat_status === 'OBSERVED') {
                $msg .= ' Comprobante ' . $order->full_number . ' observado: ' . ($order->sunat_description ?? 'Ver detalles');
            } elseif ($order->sunat_status === 'REJECTED') {
                $msg .= ' Comprobante ' . $order->full_number . ' rechazado: ' . ($order->sunat_description ?? 'Error desconocido');
            } elseif ($order->sunat_status === 'PENDING') {
                $msg .= ' Comprobante ' . $order->full_number . ' pendiente. Consulta su estado desde Comprobantes.';
            } elseif ($order->sunat_status === 'NO_ENVIADO') {
                $msg .= ' Comprobante ' . $order->full_number . ' registrado. Configure NubeFact y envíelo desde Comprobantes.';
            } else {
                $msg .= ' Comprobante ' . $order->full_number . ' - ' . $order->sunat_status . '. Revisa el detalle desde Comprobantes.';
            }
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'order_id' => $order->id,
                'print_url' => $order->isElectronic()
                    ? route('billing.pdf.ticket', $order->id)
                    : route('sales.ticket', $order->id),
                'redirect' => route('pos.index'),
            ]);
        }

        return redirect()->route('pos.index')
            ->with('success', $msg)
            ->with('last_order_id', $order->id);
    }

    private function recalculateTotal(Order $order)
    {
        $subtotal = $order->details->sum(fn($d) => $d->price * $d->quantity);
        $total = $subtotal - ($order->discount ?? 0);
        $order->update(['total' => max(0, $total)]);
    }

    private function authorizePendingDetail(OrderDetail $detail): void
    {
        $this->authorizePendingOrder($detail->order);
    }

    private function authorizeTableOrder(Table $table): void
    {
        $user = Auth::user();
        abort_unless($user && in_array($user->role, ['admin', 'cashier', 'cashier_waiter', 'waiter'], true), 403);
        $this->authorizeTableAccess($table);

        $order = Order::where('table_id', $table->id)
            ->where('status', 'pending')
            ->first();

        if ($order && $user->role === 'waiter') {
            abort_unless((int) $order->user_id === (int) $user->id, 403);
        }
    }

    private function authorizeTableAccess(Table $table): void
    {
        abort_unless(Auth::user()?->canAccessArea((int) $table->area_id), 403);
    }

    private function authorizePendingOrder(Order $order): void
    {
        abort_unless($order->status === 'pending', 403);
        $this->authorizeOrder($order);
    }

    private function authorizeOrder(Order $order): void
    {
        $user = Auth::user();
        $canManageAll = in_array($user?->role, ['admin', 'cashier', 'cashier_waiter'], true);
        abort_unless($canManageAll || (int) $order->user_id === (int) $user?->id, 403);
    }

    private function getCartHtml(Table $table)
    {
        $order = Order::where('table_id', $table->id)->where('status', 'pending')->with('details.product.category')->first();
        $clients = Client::select('id', 'name', 'document_number')->orderBy('name')->get();
        $currency = Setting::where('key', 'currency_symbol')->value('value') ?? 'S/';
        return view('pos.partials.cart', compact('order', 'clients', 'currency'))->render();
    }
}
