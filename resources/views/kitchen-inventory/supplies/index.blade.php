@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-0"><i class="bi bi-box-seam me-2"></i>Insumos de Cocina</h2>
            <p class="text-muted mb-0">Existencias del inventario de cocina</p>
        </div>
        <div>
            @if(auth()->user()->role === 'admin')
                <a href="{{ route('kitchen_inventory.movements.index') }}" class="btn btn-outline-secondary fw-bold shadow-sm me-2">
                    <i class="bi bi-clock-history me-1"></i> Kardex
                </a>
                <a href="{{ route('kitchen_inventory.categories.index') }}" class="btn btn-outline-secondary fw-bold shadow-sm me-2">
                    <i class="bi bi-tags me-1"></i> Categorías
                </a>
            @endif
            <a href="{{ route('kitchen_inventory.recipes.index') }}" class="btn btn-outline-secondary fw-bold shadow-sm me-2">
                <i class="bi bi-journal-bookmark me-1"></i> Recetas
            </a>
            @if(auth()->user()->role === 'admin')
                <a href="{{ route('kitchen_inventory.supplies.create') }}" class="btn btn-primary fw-bold shadow-sm">
                    <i class="bi bi-plus-lg me-1"></i> Nuevo Insumo
                </a>
            @endif
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('kitchen_inventory.supplies.index') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Buscar</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Nombre del insumo">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Categoría</label>
                    <select name="category_id" class="form-select">
                        <option value="">Todas</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Estado</label>
                    <select name="status" class="form-select">
                        <option value="active" @selected(request('status', 'active') === 'active')>Activos</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Inactivos</option>
                        <option value="all" @selected(request('status') === 'all')>Todos</option>
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
                            <th class="ps-4">Insumo</th>
                            <th>Categoría</th>
                            <th class="text-center">Unidad</th>
                            <th class="text-center">Stock Actual</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($supplies as $supply)
                            <tr>
                                <td class="ps-4 fw-bold">{{ $supply->name }}</td>
                                <td>{{ $supply->category->name ?? '---' }}</td>
                                <td class="text-center">{{ $supply->unit }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $supply->stock_current > 0 ? 'bg-success' : 'bg-secondary' }} rounded-pill">
                                        {{ rtrim(rtrim(number_format($supply->stock_current, 4, '.', ''), '0'), '.') }} {{ $supply->unit }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    @if($supply->is_active)
                                        <span class="badge bg-success">Activo</span>
                                    @else
                                        <span class="badge bg-secondary">Inactivo</span>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    @if(auth()->user()->role === 'admin')
                                        <a href="{{ route('kitchen_inventory.supplies.adjust.form', $supply) }}" class="btn btn-sm btn-outline-success me-1" title="Ajustar stock">
                                            <i class="bi bi-sliders"></i>
                                        </a>
                                        <a href="{{ route('kitchen_inventory.supplies.edit', $supply) }}" class="btn btn-sm btn-outline-primary me-1" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form action="{{ route('kitchen_inventory.supplies.destroy', $supply) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar el insumo {{ $supply->name }}? Solo es posible si no tiene historial.')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @else
                                        <span class="text-muted small">Solo lectura</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No hay insumos registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white">
            {{ $supplies->links() }}
        </div>
    </div>
</div>
@endsection
