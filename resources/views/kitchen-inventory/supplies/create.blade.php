@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="mb-4">
        <a href="{{ route('kitchen_inventory.supplies.index') }}" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left"></i> Volver a Insumos</a>
        <h2 class="fw-bold text-dark mb-0"><i class="bi bi-plus-lg me-2"></i>Nuevo Insumo de Cocina</h2>
        <p class="text-muted mb-0">El insumo se crea con stock 0. Registre la existencia inicial con una entrada auditable.</p>
    </div>

    <div class="card border-0 shadow-sm" style="max-width: 640px;">
        <div class="card-body">
            <form method="POST" action="{{ route('kitchen_inventory.supplies.store') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-bold">Categoría <span class="text-danger">*</span></label>
                    <select name="category_id" class="form-select @error('category_id') is-invalid @enderror" required>
                        <option value="">Seleccione...</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Nombre <span class="text-danger">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" maxlength="100" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Unidad <span class="text-danger">*</span></label>
                    <input type="text" name="unit" value="{{ old('unit') }}" class="form-control @error('unit') is-invalid @enderror" maxlength="30" placeholder="kg, l, und, caja..." required>
                    @error('unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary fw-bold"><i class="bi bi-check-lg me-1"></i> Crear Insumo</button>
                    <a href="{{ route('kitchen_inventory.supplies.index') }}" class="btn btn-outline-secondary fw-bold">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
