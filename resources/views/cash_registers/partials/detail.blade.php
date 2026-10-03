@php
    $paymentLabels = [
        'cash' => 'Efectivo',
        'card' => 'Tarjeta',
        'transfer' => 'Transferencia',
        'yape' => 'Yape',
        'plin' => 'Plin',
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

<div class="p-3 p-lg-4">
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
                <div class="col-6 col-lg"><small class="text-muted d-block">{{ $label }}</small><strong>{{ $currencySymbol }} {{ number_format($paymentTotals->get($method, 0), 2) }}</strong></div>
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