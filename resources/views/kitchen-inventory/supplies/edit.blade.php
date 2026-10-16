@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="mb-4">
        <a href="{{ route('kitchen_inventory.supplies.index') }}" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left"></i> Volver a Insumos</a>
        <h2 class="fw-bold text-dark mb-0"><i class="bi bi-pencil me-2"></i>Editar Insumo</h2>
        <p class="text-muted mb-0">El stock solo se modifica desde "Ajustar stock" (kardex auditable).</p>
    </div>

    <div class="card border-0 shadow-sm" style="max-width: 640px;">
        <div class="card-body">
            <form method="POST" action="{{ route('kitchen_inventory.supplies.update', $supply) }}">
                @csrf @method('PUT')
                <div class="mb-3">
                    <label class="form-label fw-bold">Categoría <span class="text-danger">*</span></label>
                    <select name="category_id" class="form-select @error('category_id') is-invalid @enderror" required>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('category_id', $supply->category_id) == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Nombre <span class="text-danger">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $supply->name) }}" class="form-control @error('name') is-invalid @enderror" maxlength="100" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Unidad <span class="text-danger">*</span></label>
                    @php
                        $units = ['kg' => 'Kilogramo (kg)', 'g' => 'Gramo (g)', 'l' => 'Litro (l)', 'ml' => 'Mililitro (ml)', 'und' => 'Unidad (und)', 'docena' => 'Docena', 'caja' => 'Caja', 'bandeja' => 'Bandeja', 'frasco' => 'Frasco', 'bolsa' => 'Bolsa'];
                        $unit = old('unit', $supply->unit);
                        if (! array_key_exists($unit, $units)) {
                            $units[$unit] = $unit;
                        }
                    @endphp
                    <select name="unit" class="form-select @error('unit') is-invalid @enderror" required>
                        @foreach($units as $unitValue => $unitLabel)
                            <option value="{{ $unitValue }}" @selected($unit === $unitValue)>{{ $unitLabel }}</option>
                        @endforeach
                    </select>
                    @error('unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $supply->is_active))>
                        <label class="form-check-label fw-bold" for="is_active">Activo (disponible para recetas y consumo)</label>
                    </div>
                    @error('is_active')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="alert alert-light border small mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Stock actual: <strong>{{ rtrim(rtrim(number_format($supply->stock_current, 4, '.', ''), '0'), '.') }} {{ $supply->unit }}</strong>.
                    Para desactivar un insumo con historial, use el interruptor "Activo" (no se elimina del kardex).
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary fw-bold"><i class="bi bi-check-lg me-1"></i> Guardar</button>
                    <a href="{{ route('kitchen_inventory.supplies.index') }}" class="btn btn-outline-secondary fw-bold">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
