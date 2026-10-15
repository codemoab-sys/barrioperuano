@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="mb-4">
        <a href="{{ route('kitchen_inventory.recipes.index') }}" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left"></i> Volver a Recetas</a>
        <h2 class="fw-bold text-dark mb-0"><i class="bi bi-plus-lg me-2"></i>Nueva Receta</h2>
        <p class="text-muted mb-0">Cada producto admite una sola receta. Después cree una versión borrador con sus insumos.</p>
    </div>

    <div class="card border-0 shadow-sm" style="max-width: 640px;">
        <div class="card-body">
            <form method="POST" action="{{ route('kitchen_inventory.recipes.store') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-bold">Plato (producto) <span class="text-danger">*</span></label>
                    <select name="product_id" class="form-select @error('product_id') is-invalid @enderror" required>
                        <option value="">Seleccione un producto sin receta...</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>{{ $product->name }}</option>
                        @endforeach
                    </select>
                    @error('product_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Nombre de la receta <span class="text-danger">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" maxlength="100" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary fw-bold"><i class="bi bi-check-lg me-1"></i> Crear Receta</button>
                    <a href="{{ route('kitchen_inventory.recipes.index') }}" class="btn btn-outline-secondary fw-bold">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
