<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\TableController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\KitchenController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\BillingController;

/*
|--------------------------------------------------------------------------
| Web Routes (SISTEMA PROFESIONAL v5.0 - PRODUCCIÓN)
|--------------------------------------------------------------------------
*/

// --- 1. AUTENTICACIÓN Y PÚBLICO ---
Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.perform');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
Route::get('/uploaded-assets/{path}', [App\Http\Controllers\PublicAssetController::class, 'show'])
    ->where('path', '.*')
    ->name('uploaded-assets.show');

// Menú Digital Público (Escaneo QR)
Route::get('/menu', [App\Http\Controllers\MenuController::class, 'index'])->name('menu.index');

// Debug temporal: devuelve el JSON exacto que se construiría para la última factura/boleta
Route::get('/debug/ultima-factura-json', function () {
    $order = \App\Models\Order::whereIn('document_type', ['Factura', 'Boleta'])
        ->orderByDesc('created_at')
        ->first();

    if (!$order) {
        return response()->json([
            'error' => 'No hay facturas ni boletas en la base de datos.',
        ], 404);
    }

    $order->loadMissing('details.product', 'client');

    $payload = (new \App\Services\Sunat\NubeFactInvoiceBuilder(new \App\Services\Sunat\SunatConfig()))
        ->build($order);

    return response()->json($payload, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
})->middleware(['auth', 'role:admin'])->name('debug.last.invoice.json');

// Debug temporal: lista todas las facturas/boletas ordenadas por serie y correlativo
Route::get('/debug/todas-facturas-json', function () {
    $orders = \App\Models\Order::whereIn('document_type', ['Factura', 'Boleta'])
        ->orderByRaw('CASE WHEN serie IS NULL THEN 1 ELSE 0 END ASC')
        ->orderBy('serie', 'asc')
        ->orderByRaw('CAST(correlativo AS UNSIGNED) ASC')
        ->get();

    return response()->json([
        'total' => $orders->count(),
        'facturas' => $orders->map(function ($order) {
            return [
                'id' => $order->id,
                'document_type' => $order->document_type,
                'full_number' => $order->full_number,
                'serie' => $order->serie,
                'correlativo' => $order->correlativo,
                'client_name' => $order->client_name,
                'client_document' => $order->client_document,
                'total' => (float) $order->total,
                'created_at' => $order->created_at?->toDateTimeString(),
            ];
        })->values(),
    ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
})->middleware(['auth', 'role:admin'])->name('debug.all.invoice.json');

// Debug temporal: devuelve la respuesta cruda que NubeFact respondió para la última factura
Route::get('/debug/ultima-factura-response', function () {
    $order = \App\Models\Order::whereIn('document_type', ['Factura', 'Boleta'])
        ->orderByDesc('created_at')
        ->first();

    if (!$order) {
        return response()->json(['error' => 'No hay facturas ni boletas.'], 404);
    }

    $order->refresh();

    $config = new \App\Services\Sunat\SunatConfig();
    $ruta = $config->nubefactRuta();
    $token = $config->nubefactToken();

    if (!$ruta || !$token) {
        return response()->json([
            'error' => 'NubeFact no está configurado.',
            'ruta' => $ruta,
            'token_configurado' => (bool) $token,
        ], 422);
    }

    $json = (new \App\Services\Sunat\NubeFactInvoiceBuilder($config))->build($order);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $ruta,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($json, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    return response()->json([
        'order_id' => $order->id,
        'full_number' => $order->full_number,
        'http_code' => $httpCode,
        'curl_error' => $err ?: null,
        'raw_body' => $response ?: null,
        'decoded' => $response ? json_decode($response, true) : null,
    ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
})->middleware(['auth', 'role:admin'])->name('debug.last.invoice.response');

// Debug temporal: sincroniza la última factura local con el estado real de NubeFact
Route::post('/debug/sync-ultima-factura-status', function () {
    $order = \App\Models\Order::whereIn('document_type', ['Factura', 'Boleta'])
        ->orderByDesc('created_at')
        ->first();

    if (!$order) {
        return response()->json(['error' => 'No hay facturas ni boletas.'], 404);
    }

    $updated = (new \App\Services\Sunat\NubeFactService())->syncOrderStatusFromQuery($order->fresh('details.product'));

    return response()->json([
        'order_id' => $updated->id,
        'full_number' => $updated->full_number,
        'sunat_status' => $updated->sunat_status,
        'sunat_description' => $updated->sunat_description,
        'sunat_code' => $updated->sunat_code,
        'pdf_path' => $updated->pdf_path,
        'xml_path' => $updated->xml_path,
        'cdr_path' => $updated->cdr_path,
        'hash' => $updated->hash,
    ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
})->middleware(['auth', 'role:admin'])->name('debug.sync.last.invoice.status');

// Debug: intenta enviar manualmente la última factura y muestra TODOS los detalles
Route::post('/debug/manual-send-ultima-factura', function () {
    $order = \App\Models\Order::whereIn('document_type', ['Factura', 'Boleta'])
        ->orderByDesc('created_at')
        ->first();

    if (!$order) {
        return response()->json(['error' => 'No hay facturas ni boletas.'], 404);
    }

    $order->load('details.product', 'client');

    // Validar configuración de NubeFact
    $config = new \App\Services\Sunat\SunatConfig();
    if (!$config->isNubefactConfigured()) {
        return response()->json([
            'error' => 'NubeFact no está configurado',
            'ruta' => $config->nubefactRuta(),
            'token' => $config->nubefactToken() ? 'Configurado' : 'No configurado',
        ], 422);
    }

    // Construir el JSON
    try {
        $json = (new \App\Services\Sunat\NubeFactInvoiceBuilder($config))->build($order);
    } catch (\Throwable $e) {
        return response()->json([
            'error_building_json' => $e->getMessage(),
            'order_id' => $order->id,
            'document_type' => $order->document_type,
            'serie' => $order->serie,
            'correlativo' => $order->correlativo,
        ], 422);
    }

    // Intentar enviar
    try {
        $service = new \App\Services\Sunat\NubeFactService($config);
        $service->sendInvoice($order);
    } catch (\Throwable $e) {
        return response()->json([
            'error_sending' => $e->getMessage(),
            'order_id' => $order->id,
            'trace' => $e->getTraceAsString(),
            'json_enviado' => $json,
        ], 422);
    }

    $order->fresh();
    return response()->json([
        'success' => true,
        'order_id' => $order->id,
        'full_number' => $order->full_number,
        'sunat_status' => $order->sunat_status,
        'sunat_description' => $order->sunat_description,
        'sunat_code' => $order->sunat_code,
        'pdf_path' => $order->pdf_path,
        'xml_path' => $order->xml_path,
        'cdr_path' => $order->cdr_path,
        'hash' => $order->hash,
    ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
})->middleware(['auth', 'role:admin'])->name('debug.manual.send.ultima.factura');

// Web Informativa Pública (Landing) — ocultada temporalmente
// Route::get('/', [App\Http\Controllers\LandingController::class, 'index'])->name('landing.index');
Route::get('/inicio', [App\Http\Controllers\LandingController::class, 'index'])->name('landing.home');

// La raíz redirige al login
Route::get('/', fn() => redirect()->route('login'));

// --- 2. SISTEMA INTERNO ---
Route::middleware(['auth'])->group(function () {

    // =========================================================
    // ZONA OPERATIVA (Accesible para Mozo, Cajero, Admin)
    // =========================================================

    // Rutas de Caja (Apertura y Cierre) - Todos pueden intentar entrar, pero el controller valida
    Route::get('/cash-registers/open', [App\Http\Controllers\CashRegisterController::class, 'create'])->name('cash_registers.create');
    Route::post('/cash-registers/open', [App\Http\Controllers\CashRegisterController::class, 'store'])->name('cash_registers.store');
    Route::get('/cash-registers/close', [App\Http\Controllers\CashRegisterController::class, 'close'])->name('cash_registers.close');
    Route::post('/cash-registers/close', [App\Http\Controllers\CashRegisterController::class, 'processClose'])->name('cash_registers.processClose');

    // POS (Punto de Venta) - Protegido para que cajeros abran caja primero
    Route::middleware(['cash_register'])->group(function () {
        Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
        Route::get('/pos/table/{table}', [PosController::class, 'order'])->name('pos.order');
    });

    Route::post('/pos/order/{table}/add', [PosController::class, 'addToOrder'])->name('pos.add');

    // --- NUEVA RUTA: CÓDIGO DE BARRAS ---
    Route::post('/pos/order/{table}/barcode', [PosController::class, 'addByBarcode'])->name('pos.barcode');

    // Herramientas de Orden
    Route::get('/pos/order/{order}/precheck', [PosController::class, 'precheck'])->name('pos.precheck');
    Route::get('/pos/order/{order}/kitchen-ticket', [PosController::class, 'kitchenTicket'])->name('pos.kitchen');
    Route::post('/pos/order/{order}/discount', [PosController::class, 'applyDiscount'])->name('pos.discount');
    Route::get('/pos/order/{order}/checkout-modal', [PosController::class, 'checkoutModal'])->name('pos.checkout.modal');
    Route::post('/pos/order/{order}/move', [PosController::class, 'moveTable'])->name('pos.move');

    // División de Cuenta
    Route::get('/pos/order/{order}/split-content', [PosController::class, 'getSplitContent'])->name('pos.split.content');
    Route::post('/pos/order/{order}/split', [PosController::class, 'processSplit'])->name('pos.split');

    // Gestión de Items
    Route::post('/pos/detail/{detail}/update', [PosController::class, 'updateQuantity'])->name('pos.update');
    Route::post('/pos/detail/{detail}/note', [PosController::class, 'updateNote'])->name('pos.note');
    Route::delete('/pos/detail/{detail}', [PosController::class, 'removeItem'])->name('pos.remove');

    // Monitor de Cocina
    Route::get('/kitchen', [KitchenController::class, 'index'])->name('kitchen.index');
    Route::post('/kitchen/{detail}/status', [KitchenController::class, 'updateStatus'])->name('kitchen.update');

    // RESERVAS Y AGENDA
    Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
    Route::post('/reservations', [ReservationController::class, 'store'])->name('reservations.store');
    Route::put('/reservations/{reservation}/status', [ReservationController::class, 'updateStatus'])->name('reservations.status');
    Route::delete('/reservations/{reservation}', [ReservationController::class, 'destroy'])->name('reservations.destroy');


    // =========================================================
    // ZONA FINANCIERA (Cajeros y Admins)
    // =========================================================
    Route::middleware(['role:admin,cashier,cashier_waiter'])->group(function () {
        // Cobro Final
        Route::post('/pos/order/{order}/checkout', [PosController::class, 'checkout'])->name('pos.checkout');

        // Ventas, Caja y Gastos
        Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
        Route::get('/sales/daily-report', [SaleController::class, 'dailyReport'])->name('sales.daily.report');
        Route::get('/sales/{order}/ticket', [SaleController::class, 'ticket'])->name('sales.ticket');

        Route::resource('expenses', ExpenseController::class)->only(['store', 'destroy']);

        // ── DELIVERY ──────────────────────────────────────────
        Route::get('/delivery',                          [DeliveryController::class, 'index'])->name('delivery.index');
        Route::get('/delivery/create',                   [DeliveryController::class, 'create'])->name('delivery.create');
        Route::post('/delivery',                         [DeliveryController::class, 'store'])->name('delivery.store');
        Route::get('/delivery/{delivery}',               [DeliveryController::class, 'show'])->name('delivery.show');
        Route::post('/delivery/{delivery}/status',       [DeliveryController::class, 'updateStatus'])->name('delivery.status');
        Route::post('/delivery/{delivery}/driver',       [DeliveryController::class, 'assignDriver'])->name('delivery.driver');
        Route::post('/delivery/{delivery}/checkout',     [DeliveryController::class, 'checkout'])->name('delivery.checkout');
        Route::post('/delivery/{delivery}/cancel',       [DeliveryController::class, 'cancel'])->name('delivery.cancel');
        // Repartidores
        Route::get('/delivery-drivers',                  [DeliveryController::class, 'driversIndex'])->name('delivery.drivers');
        Route::post('/delivery-drivers',                 [DeliveryController::class, 'driversStore'])->name('delivery.drivers.store');
        Route::put('/delivery-drivers/{driver}',         [DeliveryController::class, 'driversUpdate'])->name('delivery.drivers.update');
        Route::delete('/delivery-drivers/{driver}',      [DeliveryController::class, 'driversDestroy'])->name('delivery.drivers.destroy');
    });


    // =========================================================
    // COMPROBANTES: Admin, Cajero y Cajero/Mozo
    // =========================================================
    Route::middleware(['role:admin,cashier,cashier_waiter'])->group(function () {
        Route::get('/billing',                    [BillingController::class, 'index'])->name('billing.index');
        Route::get('/billing/{order}',            [BillingController::class, 'show'])->name('billing.show');
        Route::post('/billing/{order}/retry',     [BillingController::class, 'retry'])->name('billing.retry');
        Route::post('/billing/{order}/sync',      [BillingController::class, 'syncStatus'])->name('billing.sync');
        Route::post('/billing/{order}/cancel',    [BillingController::class, 'cancel'])->name('billing.cancel');
        Route::post('/billing/{order}/cancel/query', [BillingController::class, 'queryCancellation'])->name('billing.cancel.query');
        Route::get('/billing/{order}/xml',        [BillingController::class, 'downloadXml'])->name('billing.xml');
        Route::get('/billing/{order}/cdr',        [BillingController::class, 'downloadCdr'])->name('billing.cdr');
        Route::get('/billing/{order}/cancellation/pdf', [BillingController::class, 'downloadCancellationPdf'])->name('billing.cancellation.pdf');
        Route::get('/billing/{order}/cancellation/xml', [BillingController::class, 'downloadCancellationXml'])->name('billing.cancellation.xml');
        Route::get('/billing/{order}/cancellation/cdr', [BillingController::class, 'downloadCancellationCdr'])->name('billing.cancellation.cdr');
        Route::get('/billing/{order}/pdf',        [\App\Http\Controllers\BillingPdfController::class, 'show'])->name('billing.pdf');
        Route::get('/billing/{order}/ticket',     [\App\Http\Controllers\BillingPdfController::class, 'ticket'])->name('billing.pdf.ticket');
        Route::post('/billing/{order}/send-email', [\App\Http\Controllers\BillingController::class, 'sendEmail'])->name('billing.sendEmail');
    });

    // Clientes: consulta y registro para los roles operativos
    Route::middleware(['role:admin,cashier,cashier_waiter,waiter'])->group(function () {
        Route::get('/clients/search-document', [ClientController::class, 'searchDocument'])->name('clients.searchDocument');
        Route::resource('clients', ClientController::class);
    });

    // =========================================================
    // ZONA ADMINISTRATIVA (Solo Admin)
    // =========================================================
    Route::middleware(['role:admin'])->group(function () {

        // Notas de Crédito
        Route::get('/credit-notes',                       [\App\Http\Controllers\CreditNoteController::class, 'index'])->name('credit_notes.index');
        Route::get('/credit-notes/create/{order}',        [\App\Http\Controllers\CreditNoteController::class, 'create'])->name('credit_notes.create');
        Route::post('/credit-notes/{order}',              [\App\Http\Controllers\CreditNoteController::class, 'store'])->name('credit_notes.store');
        Route::get('/credit-notes/{creditNote}',          [\App\Http\Controllers\CreditNoteController::class, 'show'])->name('credit_notes.show');
        Route::post('/credit-notes/{creditNote}/retry',   [\App\Http\Controllers\CreditNoteController::class, 'retry'])->name('credit_notes.retry');
        Route::get('/credit-notes/{creditNote}/pdf',      [\App\Http\Controllers\CreditNoteController::class, 'downloadPdf'])->name('credit_notes.pdf');
        Route::get('/credit-notes/{creditNote}/xml',      [\App\Http\Controllers\CreditNoteController::class, 'downloadXml'])->name('credit_notes.xml');
        Route::get('/credit-notes/{creditNote}/cdr',      [\App\Http\Controllers\CreditNoteController::class, 'downloadCdr'])->name('credit_notes.cdr');

        // Notas de Débito
        Route::get('/debit-notes',                        [\App\Http\Controllers\DebitNoteController::class, 'index'])->name('debit_notes.index');
        Route::get('/debit-notes/create/{order}',         [\App\Http\Controllers\DebitNoteController::class, 'create'])->name('debit_notes.create');
        Route::post('/debit-notes/{order}',               [\App\Http\Controllers\DebitNoteController::class, 'store'])->name('debit_notes.store');
        Route::get('/debit-notes/{debitNote}',            [\App\Http\Controllers\DebitNoteController::class, 'show'])->name('debit_notes.show');
        Route::post('/debit-notes/{debitNote}/retry',     [\App\Http\Controllers\DebitNoteController::class, 'retry'])->name('debit_notes.retry');
        Route::get('/debit-notes/{debitNote}/pdf',        [\App\Http\Controllers\DebitNoteController::class, 'downloadPdf'])->name('debit_notes.pdf');
        Route::get('/debit-notes/{debitNote}/xml',        [\App\Http\Controllers\DebitNoteController::class, 'downloadXml'])->name('debit_notes.xml');
        Route::get('/debit-notes/{debitNote}/cdr',        [\App\Http\Controllers\DebitNoteController::class, 'downloadCdr'])->name('debit_notes.cdr');

        // Dashboard y BI
        Route::get('/sistema', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

        // Gestión
        Route::get('/cash-registers', [App\Http\Controllers\CashRegisterController::class, 'index'])->name('cash_registers.index');
        Route::resource('categories', CategoryController::class);

        // Productos e Inventario
        Route::post('/products/{product}/adjust', [ProductController::class, 'adjustStock'])->name('products.adjust');
        Route::post('/products/{product}/toggle', [ProductController::class, 'toggleStatus'])->name('products.toggle');
        Route::resource('products', ProductController::class);
        Route::get('/inventory/logs', function() {
            $logs = \App\Models\InventoryLog::with('product', 'user')->orderBy('created_at', 'desc')->paginate(50);
            return view('products.kardex', compact('logs'));
        })->name('inventory.logs');

        // Configuración y Usuarios
        Route::resource('users', UserController::class);
        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');

        // Web Informativa (Landing)
        Route::get('/web', [App\Http\Controllers\LandingSettingController::class, 'edit'])->name('landing.settings');
        Route::post('/web', [App\Http\Controllers\LandingSettingController::class, 'update'])->name('landing.settings.update');

        // MANTENIMIENTO DEL SISTEMA (RESET Y BACKUPS)
        Route::get('/system', [SystemController::class, 'index'])->name('system.index');
        Route::post('/system/reset', [SystemController::class, 'resetData'])->name('system.reset');
        Route::post('/system/backup', [SystemController::class, 'backup'])->name('system.backup');
        Route::post('/system/restore', [SystemController::class, 'restore'])->name('system.restore');

        // Mapa de Mesas
        Route::get('/tables', [TableController::class, 'index'])->name('tables.index');
        Route::post('/tables/area', [TableController::class, 'storeArea'])->name('tables.storeArea');
        Route::put('/tables/area/{area}', [TableController::class, 'updateArea'])->name('tables.updateArea');
        Route::patch('/tables/area/{area}/deactivate', [TableController::class, 'deactivateArea'])->name('tables.deactivateArea');
        Route::patch('/tables/area/{area}/activate', [TableController::class, 'activateArea'])->name('tables.activateArea');
        Route::post('/tables/table', [TableController::class, 'storeTable'])->name('tables.storeTable');
        Route::delete('/tables/table/{table}', [TableController::class, 'destroyTable'])->name('tables.destroyTable');
        Route::post('/tables/update-positions', [TableController::class, 'updatePositions'])->name('tables.updatePositions');
    });

});
