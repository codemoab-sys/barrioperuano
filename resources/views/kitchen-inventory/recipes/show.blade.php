@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="{{ route('kitchen_inventory.recipes.index') }}" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left"></i> Volver a Recetas</a>
            <h2 class="fw-bold text-dark mb-0"><i class="bi bi-journal-bookmark me-2"></i>{{ $recipe->name }}</h2>
            <p class="text-muted mb-0">Plato: <strong>{{ $recipe->product->name ?? '---' }}</strong></p>
        </div>
        <div>
            @if($recipe->is_active)
                <form action="{{ route('kitchen_inventory.recipes.deactivate', $recipe) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('¿Desactivar la receta? Dejará de validar y consumir stock de cocina en los próximos checkouts.');">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger fw-bold shadow-sm me-2">
                        <i class="bi bi-pause-circle me-1"></i> Desactivar
                    </button>
                </form>
            @else
                <form action="{{ route('kitchen_inventory.recipes.activate', $recipe) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-success fw-bold shadow-sm me-2">
                        <i class="bi bi-play-circle me-1"></i> Activar
                    </button>
                </form>
            @endif
            <form action="{{ route('kitchen_inventory.recipes.versions.store', $recipe) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-primary fw-bold shadow-sm">
                    <i class="bi bi-plus-lg me-1"></i> Nueva Versión
                </button>
            </form>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @if($pending > 0)
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <strong>{{ $pending }} pedido(s) pendiente(s)</strong> usan actualmente este plato. Mientras existan, la receta <strong>no puede desactivarse</strong> (los pedidos pendientes la consumirán al cobrarse).
        </div>
    @endif

    @if(! $recipe->is_active)
        <div class="alert alert-secondary">
            <i class="bi bi-pause-circle me-1"></i>
            Receta <strong>inactiva</strong>: el plato deja de validar y consumir stock de cocina en los próximos checkouts. Puede preparar borradores; no tendrán efecto hasta reactivar.
        </div>
    @endif

    <div class="alert alert-light border">
        <i class="bi bi-info-circle me-1"></i>
        Al desactivar, el plato dejará de validar y consumir stock de cocina en el próximo checkout. La desactivación está bloqueada mientras haya pedidos pendientes con este plato (la protección reduce el riesgo, pero no elimina la carrera residual con pedidos creados al mismo instante).
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-bold"><i class="bi bi-layers me-1"></i> Versiones</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Versión</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">Insumos</th>
                            <th>Publicada</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recipe->versions as $version)
                            <tr>
                                <td class="ps-4 fw-bold">#{{ $version->version }}</td>
                                <td class="text-center">
                                    @if($version->status === 'published')
                                        <span class="badge bg-success">Publicada</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Borrador</span>
                                    @endif
                                </td>
                                <td class="text-center">{{ $version->items_count }}</td>
                                <td class="small text-muted">{{ $version->published_at?->format('d/m/Y H:i') ?? '---' }}</td>
                                <td class="text-end pe-4">
                                    @if($version->status === 'draft')
                                        <a href="{{ route('kitchen_inventory.versions.edit', $version) }}" class="btn btn-sm btn-outline-primary me-1" title="Editar borrador">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form action="{{ route('kitchen_inventory.versions.publish', $version) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('¿Publicar la versión #{{ $version->version }}? Quedará inmutable y será la que consuma el sistema.');">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Publicar">
                                                <i class="bi bi-upload"></i>
                                            </button>
                                        </form>
                                        <form action="{{ route('kitchen_inventory.versions.destroy', $version) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('¿Eliminar el borrador #{{ $version->version }}? Se borrarán sus insumos. Esta acción no se puede deshacer.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar borrador">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-muted small me-1">Inmutable</span>
                                        <form action="{{ route('kitchen_inventory.versions.destroy', $version) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('¿Eliminar la versión publicada #{{ $version->version }}? Solo es posible si nunca se usó en consumos de pedidos. Esta acción no se puede deshacer.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar versión">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">Sin versiones. Cree la primera versión borrador.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
