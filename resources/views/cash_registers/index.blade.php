@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0 text-dark">Historial de Turnos de Caja</h2>
            <p class="text-muted mb-0">Auditoría de turnos por usuario, estado y rango de fechas.</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
        <div class="card-body">
            <form method="GET" action="{{ route('cash_registers.index') }}" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label text-muted small mb-1">Estado</label>
                    <select name="status" class="form-select">
                        <option value="all" {{ request('status', 'all') === 'all' ? 'selected' : '' }}>Todos</option>
                        <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>Abiertos</option>
                        <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Cerrados</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted small mb-1">Usuario</label>
                    <select name="user_id" class="form-select">
                        <option value="">Todos</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small mb-1">Desde</label>
                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small mb-1">Hasta</label>
                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                    <a href="{{ route('cash_registers.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm" style="border-radius: 16px;">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="px-4 py-3 text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">ID</th>
                            <th class="px-4 py-3 text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Usuario</th>
                            <th class="px-4 py-3 text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Apertura</th>
                            <th class="px-4 py-3 text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Cierre</th>
                            <th class="px-4 py-3 text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Monto proyectado</th>
                            <th class="px-4 py-3 text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Medios de pago</th>
                            <th class="px-4 py-3 text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Monto real</th>
                            <th class="px-4 py-3 text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Diferencia</th>
                            <th class="px-4 py-3 text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Estado</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($registers as $reg)
                            @php
                                $cashSales = (float) $reg->orders->sum('total');
                                $paymentTotals = $reg->orders->groupBy('payment_method')->map(fn ($orders) => (float) $orders->sum('total'));
                                $totalExpenses = (float) $reg->expenses->sum('amount');
                                $expectedValue = $reg->status === 'closed'
                                    ? (float) ($reg->expected_amount ?? $reg->opening_amount)
                                    : ((float) $reg->opening_amount + $cashSales - $totalExpenses);
                            @endphp
                            <tr>
                                <td class="px-4 py-3"><span class="text-sm fw-bold">#{{ $reg->id }}</span></td>
                                <td class="px-4 py-3">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 32px; height: 32px; font-weight: bold; font-size: 14px;">
                                            {{ strtoupper(substr($reg->user->name ?? 'U', 0, 1)) }}
                                        </div>
                                        <h6 class="mb-0 text-sm">{{ $reg->user->name ?? 'Usuario' }}</h6>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-sm d-block">{{ $reg->opening_time?->format('d/m/Y') ?? '-' }}</span>
                                    <span class="text-xs text-muted">{{ $reg->opening_time?->format('h:i A') ?? '-' }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    @if($reg->closing_time)
                                        <span class="text-sm d-block">{{ $reg->closing_time->format('d/m/Y') }}</span>
                                        <span class="text-xs text-muted">{{ $reg->closing_time->format('h:i A') }}</span>
                                    @else
                                        <span class="text-sm text-muted">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm fw-bold">
                                    {{ $currencySymbol }} {{ number_format($expectedValue, 2) }}
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    <div>Efectivo: <strong>{{ $currencySymbol }} {{ number_format($paymentTotals->get('cash', 0), 2) }}</strong></div>
                                    <div>Tarjeta: <strong>{{ $currencySymbol }} {{ number_format($paymentTotals->get('card', 0), 2) }}</strong></div>
                                    <div>Transferencia: <strong>{{ $currencySymbol }} {{ number_format($paymentTotals->get('transfer', 0), 2) }}</strong></div>
                                    <div>Yape: <strong>{{ $currencySymbol }} {{ number_format($paymentTotals->get('yape', 0), 2) }}</strong></div>
                                    <div>Plin: <strong>{{ $currencySymbol }} {{ number_format($paymentTotals->get('plin', 0), 2) }}</strong></div>
                                </td>
                                <td class="px-4 py-3 text-sm fw-bold">
                                    @if($reg->status == 'closed')
                                        {{ $currencySymbol }} {{ number_format((float) ($reg->closing_amount ?? 0), 2) }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($reg->status == 'closed')
                                        @if((float) $reg->difference === 0)
                                            <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill">{{ $currencySymbol }} 0.00 (Cuadrado)</span>
                                        @elseif((float) $reg->difference > 0)
                                            <span class="badge bg-info bg-opacity-10 text-info px-3 py-2 rounded-pill">+ {{ $currencySymbol }} {{ number_format((float) $reg->difference, 2) }} (Sobrante)</span>
                                        @else
                                            <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 rounded-pill">{{ $currencySymbol }} {{ number_format((float) $reg->difference, 2) }} (Faltante)</span>
                                        @endif
                                    @else
                                        <span class="badge bg-light text-muted px-3 py-2 rounded-pill">En curso</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($reg->status == 'open')
                                        <span class="badge bg-warning bg-opacity-10 text-warning px-3 py-2 rounded-pill"><i class="bi bi-door-open me-1"></i> Abierta</span>
                                    @else
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary px-3 py-2 rounded-pill"><i class="bi bi-door-closed me-1"></i> Cerrada</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#cashDetailModal{{ $reg->id }}" title="Ver detalle de la caja">
                                        <i class="bi bi-eye me-1"></i> Detalle
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                                        No hay registros de caja disponibles con los filtros actuales.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @foreach($registers as $reg)
                @php
                    $detailPayments = $reg->orders->groupBy('payment_method')->map(fn ($orders) => (float) $orders->sum('total'));
                    $detailCash = $detailPayments->get('cash', 0);
                    $detailExpenses = (float) $reg->expenses->sum('amount');
                    $detailExpected = $reg->status === 'closed'
                        ? (float) ($reg->expected_amount ?? $reg->opening_amount)
                        : (float) $reg->opening_amount + $detailCash - $detailExpenses;
                @endphp
                <div class="modal fade" id="cashDetailModal{{ $reg->id }}" tabindex="-1" aria-labelledby="cashDetailLabel{{ $reg->id }}" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title fw-bold" id="cashDetailLabel{{ $reg->id }}">Detalle de caja #{{ $reg->id }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row g-2 mb-3">
                                    <div class="col-md-3"><small class="text-muted d-block">Responsable</small><strong>{{ $reg->user->name ?? 'Usuario' }}</strong></div>
                                    <div class="col-md-3"><small class="text-muted d-block">Apertura</small><strong>{{ $reg->opening_time?->format('d/m/Y H:i') ?? '-' }}</strong></div>
                                    <div class="col-md-3"><small class="text-muted d-block">Cierre</small><strong>{{ $reg->closing_time?->format('d/m/Y H:i') ?? 'Abierta' }}</strong></div>
                                    <div class="col-md-3"><small class="text-muted d-block">Estado</small><strong>{{ ucfirst($reg->status) }}</strong></div>
                                </div>
                                <div class="row g-2 mb-4">
                                    <div class="col-md-3"><div class="border rounded p-2"><small class="text-muted d-block">Apertura</small><strong>{{ $currencySymbol }} {{ number_format($reg->opening_amount, 2) }}</strong></div></div>
                                    <div class="col-md-3"><div class="border rounded p-2"><small class="text-muted d-block">Efectivo esperado</small><strong>{{ $currencySymbol }} {{ number_format($detailExpected, 2) }}</strong></div></div>
                                    <div class="col-md-3"><div class="border rounded p-2"><small class="text-muted d-block">Efectivo real</small><strong>{{ $reg->closing_amount !== null ? $currencySymbol . ' ' . number_format($reg->closing_amount, 2) : '-' }}</strong></div></div>
                                    <div class="col-md-3"><div class="border rounded p-2"><small class="text-muted d-block">Gastos</small><strong>{{ $currencySymbol }} {{ number_format($detailExpenses, 2) }}</strong></div></div>
                                </div>
                                <h6 class="fw-bold">Medios de pago</h6>
                                <div class="row g-2 mb-4">
                                    @foreach(['cash' => 'Efectivo', 'card' => 'Tarjeta', 'transfer' => 'Transferencia', 'yape' => 'Yape', 'plin' => 'Plin'] as $method => $label)
                                        <div class="col-md"><div class="bg-light rounded p-2"><small class="text-muted d-block">{{ $label }}</small><strong>{{ $currencySymbol }} {{ number_format($detailPayments->get($method, 0), 2) }}</strong></div></div>
                                    @endforeach
                                </div>
                                <h6 class="fw-bold">Ventas ({{ $reg->orders->count() }})</h6>
                                <div class="table-responsive mb-4">
                                    <table class="table table-sm align-middle">
                                        <thead><tr><th>Fecha</th><th>Documento</th><th>Medio</th><th>Creador</th><th>Generó</th><th class="text-end">Total</th></tr></thead>
                                        <tbody>
                                            @forelse($reg->orders as $sale)
                                                <tr>
                                                    <td>{{ ($sale->issued_at ?? $sale->created_at)?->format('d/m/Y H:i') }}</td>
                                                    <td>{{ $sale->document_type ?? 'Venta' }} {{ $sale->full_number ?? '' }}</td>
                                                    <td>{{ ucfirst($sale->payment_method) }}</td>
                                                    <td>{{ $sale->user->name ?? '-' }}</td>
                                                    <td>{{ $sale->issuedBy->name ?? '-' }}</td>
                                                    <td class="text-end">{{ $currencySymbol }} {{ number_format($sale->total, 2) }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="6" class="text-center text-muted">Sin ventas completadas.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <h6 class="fw-bold">Gastos ({{ $reg->expenses->count() }})</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle">
                                        <thead><tr><th>Fecha</th><th>Descripción</th><th>Registró</th><th class="text-end">Importe</th></tr></thead>
                                        <tbody>
                                            @forelse($reg->expenses as $expense)
                                                <tr><td>{{ $expense->created_at?->format('d/m/Y H:i') }}</td><td>{{ $expense->description }}</td><td>{{ $expense->user->name ?? '-' }}</td><td class="text-end">{{ $currencySymbol }} {{ number_format($expense->amount, 2) }}</td></tr>
                                            @empty
                                                <tr><td colspan="4" class="text-center text-muted">Sin gastos registrados.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div>
                        </div>
                    </div>
                </div>
            @endforeach

            @if($registers->hasPages())
                <div class="p-4 border-top">
                    {{ $registers->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
