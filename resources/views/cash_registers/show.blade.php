@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
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

    <div class="d-flex justify-content-between align-items-center mb-4 gap-3">
        <div>
            <a href="{{ route('cash_registers.index') }}" class="text-decoration-none">
                <i class="bi bi-arrow-left"></i> Historial de cajas
            </a>
            <h2 class="fw-bold mb-0 mt-2">Detalle de caja #{{ $cashRegister->id }}</h2>
            <div class="text-muted">{{ $cashRegister->user?->name ?? 'Usuario' }} · {{ $cashRegister->status === 'open' ? 'Abierta' : 'Cerrada' }}</div>
        </div>
        <span class="badge {{ $cashRegister->status === 'open' ? 'bg-warning text-dark' : 'bg-secondary' }} fs-6">
            {{ $cashRegister->status === 'open' ? 'En curso' : 'Cerrada' }}
        </span>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted">Apertura</small><h4 class="fw-bold mb-0">{{ $currencySymbol }} {{ number_format($cashRegister->opening_amount, 2) }}</h4><small>{{ $cashRegister->opening_time?->format('d/m/Y H:i') }}</small></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted">Efectivo esperado</small><h4 class="fw-bold mb-0">{{ $currencySymbol }} {{ number_format($expectedValue, 2) }}</h4><small>Solo efectivo fisico</small></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted">Monto real</small><h4 class="fw-bold mb-0">{{ $cashRegister->closing_amount !== null ? $currencySymbol . ' ' . number_format($cashRegister->closing_amount, 2) : '-' }}</h4><small>{{ $cashRegister->closing_time?->format('d/m/Y H:i') ?? 'Aun abierta' }}</small></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted">Diferencia</small><h4 class="fw-bold mb-0">{{ $cashRegister->difference !== null ? $currencySymbol . ' ' . number_format($cashRegister->difference, 2) : '-' }}</h4><small>Arqueo de efectivo</small></div></div></div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white"><strong>Resumen por medio de pago</strong></div>
        <div class="card-body">
            <div class="row g-3">
                @foreach($paymentLabels as $method => $label)
                    <div class="col-sm-6 col-lg">
                        <div class="border rounded p-3 h-100">
                            <small class="text-muted d-block">{{ $label }}</small>
                            <strong>{{ $currencySymbol }} {{ number_format($paymentTotals->get($method, 0), 2) }}</strong>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center"><strong>Ventas del turno</strong><span class="badge bg-primary">{{ $cashRegister->orders->count() }}</span></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Fecha</th><th>Comprobante</th><th>Medio</th><th>Creo la orden</th><th>Genero</th><th class="text-end">Total</th></tr></thead>
                <tbody>
                    @forelse($cashRegister->orders as $order)
                        <tr>
                            <td>{{ ($order->issued_at ?? $order->created_at)?->format('d/m/Y H:i') }}</td>
                            <td>{{ $order->document_type ?? 'Ticket' }} {{ $order->full_number ?? '#' . $order->id }}</td>
                            <td>{{ $paymentLabels[$order->payment_method] ?? ucfirst((string) $order->payment_method) }}</td>
                            <td>{{ $order->user?->name ?? '-' }}</td>
                            <td>{{ $order->issuedBy?->name ?? '-' }}</td>
                            <td class="text-end fw-bold">{{ $currencySymbol }} {{ number_format($order->total, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No hay ventas en este turno.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center"><strong>Gastos del turno</strong><strong>{{ $currencySymbol }} {{ number_format($expensesTotal, 2) }}</strong></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Fecha</th><th>Descripción</th><th>Usuario</th><th class="text-end">Monto</th></tr></thead>
                <tbody>
                    @forelse($cashRegister->expenses as $expense)
                        <tr><td>{{ $expense->created_at?->format('d/m/Y H:i') }}</td><td>{{ $expense->description }}</td><td>{{ $expense->user?->name ?? '-' }}</td><td class="text-end">{{ $currencySymbol }} {{ number_format($expense->amount, 2) }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">No hay gastos en este turno.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
