@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="{{ route('kitchen_inventory.movements.index') }}" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left"></i> Volver al Kardex</a>
            <h2 class="fw-bold text-dark mb-0"><i class="bi bi-clipboard-check me-2"></i>Regularización de Faltantes</h2>
            <p class="text-muted mb-0">Consumos con faltante de stock pendiente de regularizar</p>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Consumo</th>
                            <th>Insumo</th>
                            <th>Plato / Orden</th>
                            <th class="text-center">Faltante original</th>
                            <th class="text-center">Ya regularizado</th>
                            <th class="text-center">Pendiente</th>
                            <th class="text-end pe-4">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($consumptions as $consumption)
                            @php
                                $regularized = (float) ($regularizedTotals[$consumption->id] ?? 0);
                                $pending = (float) $consumption->shortage_quantity - $regularized;
                            @endphp
                            <tr>
                                <td class="ps-4 small text-muted">#{{ $consumption->id }}</td>
                                <td class="fw-bold">{{ $consumption->supply->name ?? '---' }}</td>
                                <td class="small">
                                    {{ $consumption->product->name ?? '---' }}<br>
                                    <span class="text-muted">Orden #{{ $consumption->order_id ?? '---' }}</span>
                                </td>
                                <td class="text-center">{{ rtrim(rtrim(number_format((float) $consumption->shortage_quantity, 4, '.', ''), '0'), '.') }}</td>
                                <td class="text-center">{{ rtrim(rtrim(number_format($regularized, 4, '.', ''), '0'), '.') }}</td>
                                <td class="text-center">
                                    <span class="badge bg-warning text-dark">{{ rtrim(rtrim(number_format($pending, 4, '.', ''), '0'), '.') }}</span>
                                </td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-sm btn-outline-primary" onclick='regularize(@js([
                                        "id" => $consumption->id,
                                        "supply" => $consumption->supply->name ?? '',
                                        "unit" => $consumption->supply->unit ?? '',
                                        "pending" => $pending,
                                    ]))' title="Regularizar">
                                        <i class="bi bi-clipboard-check"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No hay faltantes pendientes de regularizar.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white">{{ $consumptions->links() }}</div>
    </div>
</div>

<div class="modal fade" id="regularizeModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('kitchen_inventory.regularizations.store') }}">
            @csrf
            <input type="hidden" name="form_uuid" value="{{ \Illuminate\Support\Str::uuid() }}">
            <input type="hidden" name="consumption_id" id="regConsumptionId">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Regularizar Faltante</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">Insumo: <strong id="regSupplyName"></strong> — Pendiente: <strong id="regPending"></strong></p>
                <div class="mb-3">
                    <label class="form-label fw-bold">Cantidad a regularizar <span class="text-danger">*</span></label>
                    <input type="number" name="quantity" id="regQuantity" step="0.0001" min="0.0001" class="form-control" required>
                    <div class="form-text">No puede superar el faltante pendiente.</div>
                </div>
                <div class="mb-0">
                    <label class="form-label fw-bold">Motivo <span class="text-danger">*</span></label>
                    <textarea name="note" rows="3" class="form-control" maxlength="500" placeholder="Ej.: Diferencia verificada en conteo físico de almacén" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary fw-bold">Registrar</button>
            </div>
        </form>
    </div>
</div>

<script>
function regularize(data) {
    document.getElementById('regConsumptionId').value = data.id;
    document.getElementById('regSupplyName').textContent = data.supply;
    document.getElementById('regPending').textContent = data.pending + ' ' + data.unit;
    document.getElementById('regQuantity').max = data.pending;
    new bootstrap.Modal(document.getElementById('regularizeModal')).show();
}
</script>
@endsection
