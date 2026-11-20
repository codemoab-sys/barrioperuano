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
                            <th class="px-4 py-3 text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Monto real</th>
                            <th class="px-4 py-3 text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Diferencia</th>
                            <th class="px-4 py-3 text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Estado</th>
                            <th class="px-4 py-3 text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 text-end">Detalle</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($registers as $reg)
                            @php
                                $cashSales = (float) $reg->orders->sum('total');
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
                                    <button type="button"
                                            class="btn btn-sm btn-outline-primary js-cash-register-detail"
                                            data-detail-url="{{ route('cash_registers.show', $reg) }}?modal=1"
                                            data-register-id="{{ $reg->id }}"
                                            title="Ver detalle de la caja">
                                        <i class="bi bi-eye me-1"></i> Detalle
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5">
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

            @if($registers->hasPages())
                <div class="p-4 border-top">
                    {{ $registers->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

<div class="modal fade" id="cashRegisterDetailModal" tabindex="-1" aria-labelledby="cashRegisterDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="cashRegisterDetailModalLabel">Detalle del turno de caja</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0 position-relative" style="min-height: 360px;">
                <div id="cashRegisterDetailLoading" class="position-absolute top-50 start-50 translate-middle text-center text-muted">
                    <div class="spinner-border spinner-border-sm mb-2" role="status" aria-hidden="true"></div>
                    <div>Cargando detalle...</div>
                </div>
                <iframe id="cashRegisterDetailFrame" title="Detalle del turno de caja" class="w-100 border-0 d-none" style="height: 70vh;"></iframe>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('cashRegisterDetailModal');
    const frame = document.getElementById('cashRegisterDetailFrame');
    const loading = document.getElementById('cashRegisterDetailLoading');
    if (!modalElement || !frame || !loading || typeof bootstrap === 'undefined') return;

    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    document.querySelectorAll('.js-cash-register-detail').forEach(function (button) {
        button.addEventListener('click', function () {
            frame.classList.add('d-none');
            loading.classList.remove('d-none');
            frame.src = button.dataset.detailUrl;
            modal.show();
        });
    });

    frame.addEventListener('load', function () {
        loading.classList.add('d-none');
        frame.classList.remove('d-none');
    });

    modalElement.addEventListener('hidden.bs.modal', function () {
        frame.src = 'about:blank';
        frame.classList.add('d-none');
        loading.classList.remove('d-none');
    });
});
</script>
@endpush
@endsection
