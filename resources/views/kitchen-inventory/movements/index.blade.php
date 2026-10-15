@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="{{ route('kitchen_inventory.supplies.index') }}" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left"></i> Volver a Insumos</a>
            <h2 class="fw-bold text-dark mb-0"><i class="bi bi-clock-history me-2"></i>Kardex de Cocina</h2>
            <p class="text-muted mb-0">Auditoría de movimientos del inventario de cocina</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('kitchen_inventory.movements.index') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Insumo</label>
                    <select name="supply_id" class="form-select">
                        <option value="">Todos</option>
                        @foreach($supplies as $supply)
                            <option value="{{ $supply->id }}" @selected(request('supply_id') == $supply->id)>{{ $supply->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Tipo</label>
                    <select name="type" class="form-select">
                        <option value="">Todos</option>
                        @foreach($typeLabels as $value => $label)
                            <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Desde</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Hasta</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-primary fw-bold w-100"><i class="bi bi-funnel me-1"></i> Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Fecha / Hora</th>
                            <th>Insumo</th>
                            <th>Tipo</th>
                            <th class="text-center">Cantidad</th>
                            <th class="text-center">Saldo (antes → después)</th>
                            <th>Motivo</th>
                            <th>Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($movements as $movement)
                            <tr>
                                <td class="ps-4 text-muted small">
                                    {{ $movement->created_at->format('d/m/Y') }}<br>
                                    {{ $movement->created_at->format('H:i:s') }}
                                </td>
                                <td class="fw-bold">{{ $movement->supply->name ?? '---' }}</td>
                                <td>
                                    @php
                                        $badge = match ($movement->type) {
                                            'sale' => 'bg-danger',
                                            'entry' => 'bg-success',
                                            'adjustment_in' => 'bg-primary',
                                            'adjustment_out' => 'bg-warning text-dark',
                                            'waste' => 'bg-dark',
                                            'return', 'cancellation' => 'bg-info text-dark',
                                            default => 'bg-secondary',
                                        };
                                    @endphp
                                    <span class="badge {{ $badge }}">{{ $typeLabels[$movement->type] ?? $movement->type }}</span>
                                </td>
                                <td class="text-center">{{ rtrim(rtrim(number_format($movement->quantity, 4, '.', ''), '0'), '.') }}</td>
                                <td class="text-center small">
                                    {{ rtrim(rtrim(number_format($movement->stock_before, 4, '.', ''), '0'), '.') }}
                                    <i class="bi bi-arrow-right"></i>
                                    {{ rtrim(rtrim(number_format($movement->stock_after, 4, '.', ''), '0'), '.') }}
                                </td>
                                <td class="small">{{ $movement->note ?? '---' }}</td>
                                <td class="small">{{ $movement->user->name ?? '---' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No hay movimientos con los filtros seleccionados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white">{{ $movements->links() }}</div>
    </div>
</div>
@endsection
