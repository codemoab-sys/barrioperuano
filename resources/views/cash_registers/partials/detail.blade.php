<style>
    :root {
        color-scheme: dark;
        --cash-bg: #171827;
        --cash-panel: #202136;
        --cash-panel-soft: #292b43;
        --cash-border: rgba(255,255,255,.09);
        --cash-text: #f7f7fb;
        --cash-muted: #aeb1c8;
        --cash-accent: #f2a900;
    }

    * { box-sizing: border-box; }
    body { margin: 0; background: var(--cash-bg); color: var(--cash-text); font: 14px/1.45 Arial, sans-serif; }
    .cash-modal-detail { padding: 22px; }
    .cash-modal-detail h4 { margin: 0 0 4px; font-size: 22px; color: var(--cash-text); }
    .cash-modal-detail .text-muted { color: var(--cash-muted) !important; }
    .cash-modal-detail .d-flex { display: flex; }
    .cash-modal-detail .justify-content-between { justify-content: space-between; }
    .cash-modal-detail .align-items-start { align-items: flex-start; }
    .cash-modal-detail .mb-1 { margin-bottom: 4px; }
    .cash-modal-detail .mb-2 { margin-bottom: 10px; }
    .cash-modal-detail .mb-3 { margin-bottom: 16px; }
    .cash-modal-detail .p-3 { padding: 16px; }
    .cash-modal-detail .row { display: flex; flex-wrap: wrap; gap: 10px; }
    .cash-modal-detail .col-6 { flex: 1 1 calc(50% - 10px); min-width: 150px; }
    .cash-modal-detail .col-lg { flex: 1 1 130px; min-width: 125px; }
    .cash-modal-detail .border { border: 1px solid var(--cash-border) !important; }
    .cash-modal-detail .rounded,
    .cash-modal-detail .rounded-3 { border-radius: 10px; }
    .cash-modal-detail .h-100 { height: 100%; }
    .cash-modal-detail .bg-light-subtle { background: var(--cash-panel-soft); }
    .cash-modal-detail .fw-bold { font-weight: 700; }
    .cash-modal-detail .fs-5 { font-size: 17px; }
    .cash-modal-detail .d-block { display: block; }
    .cash-modal-detail .small { font-size: 12px; }
    .cash-modal-detail .border-bottom { border-bottom: 1px solid var(--cash-border) !important; }
    .cash-modal-detail .badge { display: inline-block; padding: 5px 9px; border-radius: 999px; font-size: 11px; font-weight: 700; }
    .cash-modal-detail .bg-primary { background: #3f73e8; color: #fff; }
    .cash-modal-detail .bg-danger { background: #d95368; color: #fff; }
    .cash-modal-detail .bg-warning { background: var(--cash-accent); color: #241d08; }
    .cash-modal-detail .text-dark { color: #241d08; }
    .cash-modal-detail .table-responsive { overflow-x: auto; }
    .cash-modal-detail table { width: 100%; border-collapse: collapse; color: var(--cash-text); }
    .cash-modal-detail th,
    .cash-modal-detail td { padding: 10px 12px; border-bottom: 1px solid var(--cash-border); text-align: left; white-space: nowrap; }
    .cash-modal-detail th { color: var(--cash-muted); font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
    .cash-modal-detail .text-end { text-align: right; }
    .cash-modal-detail .text-center { text-align: center; }
    @media (max-width: 640px) {
        .cash-modal-detail { padding: 16px; }
        .cash-modal-detail th, .cash-modal-detail td { padding: 8px; }
    }
</style>

@php
    $paymentLabels = [
        'cash' => 'Efectivo',
        'card' => 'Tarjeta',
        'transfer' => 'Transferencia',
        'yape' => 'Yape',
        'plin' => 'Plin',
    ];
    $paymentIcons = [
        'cash' => 'bi-cash-coin',
        'card' => 'bi-credit-card-2-front',
        'transfer' => 'bi-bank',
        'yape' => 'bi-phone',
        'plin' => 'bi-phone-fill',
    ];
    $paymentTotals = $cashRegister->orders
        ->groupBy('payment_method')
        ->map(fn ($orders) => (float) $orders->sum('total'));
    $cashSales = $paymentTotals->get('cash', 0);
    $expensesTotal = (float) $cashRegister->expenses->sum('amount');
    $expectedValue = $cashRegister->status === 'closed'
        ? (float) ($cashRegister->expected_amount ?? $cashRegister->opening_amount)
        : (float) $cashRegister->opening_amount + $cashSales - $expensesTotal;
@endphp

<div class="p-3 p-lg-4 cash-modal-detail">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h4 class="fw-bold mb-1">Turno de caja #{{ $cashRegister->id }}</h4>
            <div class="text-muted small">{{ $cashRegister->user?->name ?? 'Usuario' }}</div>
        </div>
        <span class="badge {{ $cashRegister->status === 'open' ? 'bg-warning text-dark' : 'bg-secondary' }}">
            {{ $cashRegister->status === 'open' ? 'En curso' : 'Cerrada' }}
        </span>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-6 col-lg-3"><div class="border rounded p-3 h-100"><small class="text-muted d-block">Apertura</small><strong>{{ $currencySymbol }} {{ number_format($cashRegister->opening_amount, 2) }}</strong><small class="d-block">{{ $cashRegister->opening_time?->format('d/m/Y H:i') }}</small></div></div>
        <div class="col-6 col-lg-3"><div class="border rounded p-3 h-100"><small class="text-muted d-block">Efectivo esperado</small><strong>{{ $currencySymbol }} {{ number_format($expectedValue, 2) }}</strong><small class="d-block">Solo efectivo</small></div></div>
        <div class="col-6 col-lg-3"><div class="border rounded p-3 h-100"><small class="text-muted d-block">Monto real</small><strong>{{ $cashRegister->closing_amount !== null ? $currencySymbol . ' ' . number_format($cashRegister->closing_amount, 2) : '-' }}</strong><small class="d-block">{{ $cashRegister->closing_time?->format('d/m/Y H:i') ?? 'Aun abierta' }}</small></div></div>
        <div class="col-6 col-lg-3"><div class="border rounded p-3 h-100"><small class="text-muted d-block">Diferencia</small><strong>{{ $cashRegister->difference !== null ? $currencySymbol . ' ' . number_format($cashRegister->difference, 2) : '-' }}</strong><small class="d-block">Arqueo físico</small></div></div>
    </div>

    <div class="border rounded p-3 mb-3">
        <div class="fw-bold mb-2">Resumen por medio de pago</div>
        <div class="row g-2">
            @foreach($paymentLabels as $method => $label)
                <div class="col-6 col-lg">
                    <div class="border rounded-3 p-3 h-100 bg-light-subtle">
                        <div class="text-muted small"><i class="bi {{ $paymentIcons[$method] }} me-1"></i>{{ $label }}</div>
                        <strong class="fs-5 d-block mt-1">{{ $currencySymbol }} {{ number_format($paymentTotals->get($method, 0), 2) }}</strong>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="border rounded mb-3">
        <div class="p-3 fw-bold border-bottom">Ventas del turno <span class="badge bg-primary">{{ $cashRegister->orders->count() }}</span></div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead><tr><th>Fecha</th><th>Comprobante</th><th>Medio</th><th>Generó</th><th class="text-end">Total</th></tr></thead>
                <tbody>
                    @forelse($cashRegister->orders as $order)
                        <tr><td>{{ ($order->issued_at ?? $order->created_at)?->format('d/m/Y H:i') }}</td><td>{{ $order->document_type ?? 'Ticket' }} {{ $order->full_number ?? '#' . $order->id }}</td><td>{{ $paymentLabels[$order->payment_method] ?? ucfirst((string) $order->payment_method) }}</td><td>{{ $order->issuedBy?->name ?? $order->user?->name ?? '-' }}</td><td class="text-end fw-bold">{{ $currencySymbol }} {{ number_format($order->total, 2) }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">No hay ventas en este turno.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="border rounded">
        <div class="p-3 fw-bold border-bottom">Gastos del turno <span class="badge bg-danger">{{ $currencySymbol }} {{ number_format($expensesTotal, 2) }}</span></div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead><tr><th>Fecha</th><th>Descripción</th><th>Registró</th><th class="text-end">Monto</th></tr></thead>
                <tbody>
                    @forelse($cashRegister->expenses as $expense)
                        <tr><td>{{ $expense->created_at?->format('d/m/Y H:i') }}</td><td>{{ $expense->description }}</td><td>{{ $expense->user?->name ?? '-' }}</td><td class="text-end fw-bold">{{ $currencySymbol }} {{ number_format($expense->amount, 2) }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">No hay gastos en este turno.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>