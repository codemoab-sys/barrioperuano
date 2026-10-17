@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="{{ route('kitchen_inventory.supplies.index') }}" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left"></i> Volver a Insumos</a>
            <h2 class="fw-bold text-dark mb-0"><i class="bi bi-tags me-2"></i>Categorías de Insumos</h2>
            <p class="text-muted mb-0">Agrupación de insumos del inventario de cocina</p>
        </div>
        <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#createCategoryModal">
            <i class="bi bi-plus-lg me-1"></i> Nueva Categoría
        </button>
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
                            <th class="ps-4">Nombre</th>
                            <th class="text-center">Insumos</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categories as $category)
                            <tr>
                                <td class="ps-4 fw-bold">{{ $category->name }}</td>
                                <td class="text-center"><span class="badge bg-light text-dark border">{{ $category->supplies_count }}</span></td>
                                <td class="text-center">
                                    @if($category->is_active)
                                        <span class="badge bg-success">Activa</span>
                                    @else
                                        <span class="badge bg-secondary">Inactiva</span>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-sm btn-outline-primary me-1" onclick='editCategory(@js(["id" => $category->id, "name" => $category->name, "is_active" => (bool) $category->is_active]))' title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form action="{{ route('kitchen_inventory.categories.destroy', $category) }}" method="POST" class="d-inline" onsubmit='return confirm(@js("¿Eliminar la categoría " . $category->name . "? Solo es posible si no tiene insumos."))'>
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">No hay categorías registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white">{{ $categories->links() }}</div>
    </div>
</div>

<div class="modal fade" id="createCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('kitchen_inventory.categories.store') }}">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Nueva Categoría</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label fw-bold">Nombre <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" maxlength="100" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary fw-bold">Crear</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="editCategoryForm" method="POST" action="">
            @csrf @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Editar Categoría</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label fw-bold">Nombre <span class="text-danger">*</span></label>
                <input type="text" name="name" id="editCategoryName" class="form-control" maxlength="100" required>
                <div class="form-check form-switch mt-3">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="editCategoryActive">
                    <label class="form-check-label fw-bold" for="editCategoryActive">Activa</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary fw-bold">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
function editCategory(category) {
    document.getElementById('editCategoryForm').action = '{{ url('kitchen-inventory/categories') }}/' + category.id;
    document.getElementById('editCategoryName').value = category.name;
    document.getElementById('editCategoryActive').checked = category.is_active;
    new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
}
</script>
@endsection
