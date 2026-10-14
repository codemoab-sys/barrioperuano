@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-center">
    <div class="col-12 col-xl-11">
        <div class="d-flex align-items-center mb-4">
            <a href="{{ route('products.index') }}" class="btn btn-outline-secondary btn-sm me-3"><i class="bi bi-arrow-left"></i></a>
            <h2 class="fw-bold text-dark mb-0">Nuevo Producto</h2>
        </div>

        <form id="productCreateForm" action="{{ route('products.store') }}" method="POST" enctype="multipart/form-data" class="row g-4">
            @csrf

            <div class="col-12">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <h6 class="fw-bold text-primary mb-3 border-bottom pb-2">Información General</h6>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Nombre del Producto <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control form-control-lg" placeholder="Ej: Lomo Saltado" required autofocus>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">Categoría <span class="text-danger">*</span></label>
                                <select name="category_id" class="form-select js-searchable-select" required>
                                    <option value="" selected disabled>-- Seleccionar --</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">Precio de Venta <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">S/</span>
                                    <input type="number" step="0.01" name="price" class="form-control" placeholder="0.00" required>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">Stock Inicial</label>
                                <input type="number" name="stock" id="productStock" class="form-control" placeholder="0" min="0">
                                <small class="text-muted" style="font-size: 0.75rem;">Se registrará en el inventario si se indica.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">Código de Barras</label>
                                <input type="text" name="barcode" class="form-control" placeholder="Opcional">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold small text-muted">Máximo de sabores</label>
                            <input type="number" name="required_sauce_count" class="form-control" min="1" max="10" value="{{ old('required_sauce_count') }}" placeholder="Opcional">
                            <small class="text-muted" style="font-size: 0.75rem;">Solo para categorías Alitas o Barcos. Déjalo vacío si no permite elegir sabores.</small>
                        </div>

                        <div class="form-check form-switch mb-4 bg-light p-3 rounded border">
                            <input class="form-check-input" type="checkbox" name="control_stock" id="controlStockCheck" checked>
                            <label class="form-check-label fw-bold ms-2" for="controlStockCheck">
                                Controlar stock
                            </label>
                        </div>

                        <div class="form-check form-switch mb-4 bg-light p-3 rounded border">
                            <input class="form-check-input" type="checkbox" name="is_saleable" id="saleableCheck" checked>
                            <label class="form-check-label fw-bold ms-2" for="saleableCheck">
                                Disponible para la venta
                            </label>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold small text-muted">Imagen (Opcional)</label>
                            <input type="file" name="image" class="form-control" accept="image/*">
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 mt-3 text-end">
                <button type="submit" class="btn btn-primary fw-bold px-4" id="saveProductButton">
                    <i class="bi bi-save me-2"></i> Guardar Producto
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const controlStockCheck = document.getElementById('controlStockCheck');
    const productStock = document.getElementById('productStock');
    controlStockCheck?.addEventListener('change', function () {
        productStock.disabled = !this.checked;
        if (!this.checked) {
            productStock.value = '';
        }
    });

    document.getElementById('productCreateForm')?.addEventListener('submit', function (event) {
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
