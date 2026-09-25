@extends('layouts.app')

@section('content')
<div class="d-flex align-items-center mb-4">
    <a href="{{ route('products.index') }}" class="btn btn-outline-secondary btn-sm me-3"><i class="bi bi-arrow-left"></i></a>
    <div>
        <h2 class="fw-bold text-dark mb-0">Editar Producto</h2>
        <p class="text-muted mb-0">Gestiona los detalles del producto</p>
    </div>
</div>

<form id="productEditForm" action="{{ route('products.update', $product->id) }}" method="POST" enctype="multipart/form-data" class="row g-4">
    @csrf
    @method('PUT')

    <div class="col-12">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h6 class="fw-bold text-primary mb-3">Información General</h6>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted">Nombre del Producto</label>
                    <input type="text" name="name" class="form-control" value="{{ $product->name }}" required>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Categoría</label>
                        <select name="category_id" class="form-select js-searchable-select" required>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" {{ $product->category_id == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Precio de Venta</label>
                        <input type="number" step="0.01" name="price" class="form-control" value="{{ $product->price }}" required>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Stock Actual</label>
                        <input type="text" class="form-control bg-light" value="{{ $product->stock }}" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Código de Barras</label>
                        <input type="text" name="barcode" class="form-control" value="{{ $product->barcode }}" placeholder="Opcional">
                    </div>
                </div>

                <div class="form-check form-switch mb-4 bg-light p-3 rounded border">
                    <input class="form-check-input" type="checkbox" name="is_saleable" id="saleableCheck" {{ $product->is_saleable ? 'checked' : '' }}>
                    <label class="form-check-label fw-bold ms-2" for="saleableCheck">
                        Disponible para la venta
                    </label>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted">Imagen</label>
                    <div class="d-flex align-items-center gap-3">
                        @if($product->image)
                            <img src="{{ route('uploaded-assets.show', ['path' => $product->image]) }}" class="rounded border" width="60" height="60" style="object-fit: cover;">
                        @endif
                        <input type="file" name="image" class="form-control" accept="image/*">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 mt-3 text-end">
        <button type="submit" class="btn btn-success fw-bold px-4" id="saveProductButton">
            <i class="bi bi-check-lg me-2"></i> Guardar Cambios
        </button>
    </div>
</form>

<script>
    document.getElementById('productEditForm')?.addEventListener('submit', function (event) {
        if (this.dataset.submitting === 'true') {
            event.preventDefault();
            return;
        }

        this.dataset.submitting = 'true';
        const button = document.getElementById('saveProductButton');
        if (button) {
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Guardando...';
        }
    });
</script>
@endsection
