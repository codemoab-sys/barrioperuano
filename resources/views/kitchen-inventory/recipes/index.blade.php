@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-0"><i class="bi bi-journal-bookmark me-2"></i>Recetas de Cocina</h2>
            <p class="text-muted mb-0">Insumos y proporciones por plato (versiones publicadas)</p>
        </div>
        <a href="{{ route('kitchen_inventory.supplies.index') }}" class="btn btn-outline-secondary fw-bold shadow-sm">
            <i class="bi bi-box-seam me-1"></i> Insumos
        </a>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('kitchen_inventory.recipes.index') }}" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small fw-bold">Buscar</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Nombre de receta o plato">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Estado</label>
                    <select name="status" class="form-select">
                        <option value="active" @selected(request('status', 'active') === 'active')>Activas</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Inactivas</option>
                        <option value="all" @selected(request('status') === 'all')>Todas</option>
                    </select>
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
                            <th class="ps-4">Receta</th>
                            <th>Plato (producto)</th>
                            <th class="text-center">Versión publicada</th>
                            <th class="text-center">Pedidos pendientes</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recipes as $recipe)
                            @php $published = $recipe->versions->first(); @endphp
                            <tr>
                                <td class="ps-4 fw-bold">{{ $recipe->name }}</td>
                                <td>{{ $recipe->product->name ?? '---' }}</td>
                                <td class="text-center">
                                    @if($published)
                                        <span class="badge bg-info text-dark">#{{ $published->version }}</span>
                                    @else
                                        <span class="badge bg-secondary">Sin versión</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @php $pending = $pendingByProduct[$recipe->product_id] ?? 0; @endphp
                                    @if($pending > 0)
                                        <span class="badge bg-warning text-dark">{{ $pending }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($recipe->is_active)
                                        <span class="badge bg-success">Activa</span>
                                    @else
                                        <span class="badge bg-secondary">Inactiva</span>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    @if(auth()->user()->role === 'admin')
                                        <a href="{{ route('kitchen_inventory.recipes.show', $recipe) }}" class="btn btn-sm btn-outline-primary" title="Ver detalle">
                                            <i class="bi bi-eye-fill"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No hay recetas registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white">{{ $recipes->links() }}</div>
    </div>

    @if(auth()->user()->role === 'admin')
        <div class="mt-3">
            <a href="{{ route('kitchen_inventory.recipes.create') }}" class="btn btn-primary fw-bold shadow-sm">
                <i class="bi bi-plus-lg me-1"></i> Nueva Receta
            </a>
        </div>
    @endif
</div>
@endsection
