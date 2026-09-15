@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-center">
    <div class="col-md-10 col-lg-9">
        <div class="d-flex align-items-center mb-4">
            <a href="{{ route('products.index') }}" class="btn btn-outline-secondary btn-sm me-3"><i class="bi bi-arrow-left"></i></a>
            <h2 class="fw-bold text-dark mb-0">Nuevo Producto</h2>
        </div>

        <form id="productCreateForm" action="{{ route('products.store') }}" method="POST" enctype="multipart/form-data" class="row g-4">
            @csrf

            <div class="col-md-8">
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
                                <label class="form-label fw-bold small text-muted">Código de Barras (Opcional)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="bi bi-upc-scan"></i></span>
                                    <input type="text" name="barcode" class="form-control" placeholder="Escanear o escribir...">
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted">Costo Unitario</label>
                                <div class="input-group">
                                    <span class="input-group-text">S/</span>
                                    <input type="number" step="0.01" name="cost" class="form-control" placeholder="0.00" value="0.00">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted">Precio de Venta <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">S/</span>
                                    <input type="number" step="0.01" name="price" class="form-control" placeholder="0.00" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted">Stock Inicial</label>
                                <input type="number" name="stock" class="form-control" placeholder="0">
                                <small class="text-muted" style="font-size: 0.75rem;">Se creará un registro de entrada en el Kardex.</small>
                            </div>
                        </div>

                        <div class="form-check form-switch mb-4 bg-light p-3 rounded border">
                            <input class="form-check-input" type="checkbox" name="is_saleable" id="saleableCheck" checked>
                            <label class="form-check-label fw-bold ms-2" for="saleableCheck">
                                Disponible para la venta (POS y Menú)
                            </label>
                            <small class="d-block text-muted ms-5" style="font-size: 0.75rem;">Si se desmarca, solo será un insumo para recetas.</small>
                        </div>

                        <div class="alert bg-primary bg-opacity-10 border border-primary border-opacity-25 rounded-3 mb-4 p-3">
                            <h6 class="text-primary fw-bold mb-3"><i class="bi bi-qr-code-scan me-2"></i>Opciones para Carta Digital (Menú QR)</h6>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-bold small text-muted">Precio de Promoción</label>
                                    <div class="input-group">
                                        <span class="input-group-text">S/</span>
                                        <input type="number" step="0.01" name="promotional_price" class="form-control" placeholder="0.00">
                                    </div>
                                    <small class="text-muted" style="font-size: 0.7rem;">Si tiene valor, tachará el precio normal.</small>
                                </div>
                                <div class="col-md-4 d-flex align-items-center mt-4 pt-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="is_chef_recommendation" id="chefCheck">
                                        <label class="form-check-label fw-bold text-dark ms-2" for="chefCheck">Sugerencia del Chef</label>
                                    </div>
                                </div>
                                <div class="col-md-4 d-flex align-items-center mt-4 pt-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="is_new" id="newCheck">
                                        <label class="form-check-label fw-bold text-dark ms-2" for="newCheck">Producto Nuevo</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold small text-muted">Imagen (Opcional)</label>
                            <input type="file" name="image" class="form-control" accept="image/*">
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-primary text-white py-2 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-basket me-2"></i>Receta / Insumos</h6>
                        <button type="button" class="btn btn-sm btn-light text-primary fw-bold" onclick="addIngredient()">+ Agregar</button>
                    </div>
                    <div class="card-body p-3 bg-light overflow-auto" style="max-height: 400px;">
                        <p class="small text-muted mb-3">Selecciona los insumos que componen este producto. Al venderlo, se descontarán automáticamente.</p>

                        <div id="ingredients-list"></div>

                        <div class="d-none" id="ingredient-select-template">
                            <div class="input-group mb-2 ingredient-row">
                                <select class="form-select form-select-sm" style="width: 55%;" onchange="setIngredientName(this)">
                                    <option value="">- Insumo -</option>
                                    @foreach($ingredients as $ing)
                                        <option value="{{ $ing->id }}">{{ $ing->name }}</option>
                                    @endforeach
                                </select>
                                <input type="number" step="0.01" class="form-control form-control-sm text-center" placeholder="Cant.">
                                <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()"><i class="bi bi-x"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-0 text-end">
                        <button type="submit" class="btn btn-primary fw-bold w-100" id="saveProductButton">
                            <i class="bi bi-save me-2"></i> Guardar Producto
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    document.getElementById('productCreateForm')?.addEventListener('submit', function (event) {
        if (this.dataset.submitting === 'true') {
            event.preventDefault();
            return;
        }

        this.dataset.submitting = 'true';
        const button = document.getElementById('saveProductButton');
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Guardando...';
    });

    function addIngredient() {
        let container = document.getElementById('ingredients-list');
        let template = document.getElementById('ingredient-select-template').innerHTML;
        let div = document.createElement('div');
        div.innerHTML = template;
        container.appendChild(div.firstElementChild);
    }

    function setIngredientName(select) {
        let row = select.parentElement;
        let inputQty = row.querySelector('input[type="number"]');
        if (select.value) {
            inputQty.name = "ingredients[" + select.value + "]";
            inputQty.required = true;
        }
    }
</script>
@endsection
