@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="mb-4">
        <a href="{{ route('kitchen_inventory.recipes.show', $version->recipe_id) }}" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left"></i> Volver a la receta</a>
        <h2 class="fw-bold text-dark mb-0"><i class="bi bi-pencil me-2"></i>Borrador Versión #{{ $version->version }}</h2>
        <p class="text-muted mb-0">{{ $version->recipe->name }} — {{ $version->recipe->product->name ?? '' }}</p>
    </div>

    <div class="card border-0 shadow-sm" style="max-width: 860px;">
        <div class="card-body">
            <form method="POST" action="{{ route('kitchen_inventory.versions.update', $version) }}">
                @csrf @method('PUT')
                <div id="itemsContainer">
                    @php $rows = old('items', $version->items->map(fn ($i) => ['kitchen_supply_id' => $i->kitchen_supply_id, 'quantity' => $i->quantity])->toArray()); @endphp
                    @if(count($rows) === 0)
                        @php $rows = [['kitchen_supply_id' => '', 'quantity' => '']]; @endphp
                    @endif
                    @foreach($rows as $index => $row)
                        <div class="row g-2 mb-2 item-row align-items-end">
                            <div class="col-md-7">
                                @if($index === 0)<label class="form-label small fw-bold">Insumo <span class="text-danger">*</span></label>@endif
                                <select name="items[{{ $index }}][kitchen_supply_id]" class="form-select supply-select @error('items.'.$index.'.kitchen_supply_id') is-invalid @enderror" required>
                                    <option value="">Seleccione insumo...</option>
                                    @foreach($supplies as $supply)
                                        <option value="{{ $supply->id }}" @selected($row['kitchen_supply_id'] == $supply->id)>{{ $supply->name }} ({{ $supply->unit }})</option>
                                    @endforeach
                                </select>
                                @error('items.'.$index.'.kitchen_supply_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-3">
                                @if($index === 0)<label class="form-label small fw-bold">Cantidad por plato <span class="text-danger">*</span></label>@endif
                                <input type="number" name="items[{{ $index }}][quantity]" value="{{ $row['quantity'] ?? '' }}" step="0.0001" min="0.0001" class="form-control @error('items.'.$index.'.quantity') is-invalid @enderror" placeholder="0.0000" required>
                                @error('items.'.$index.'.quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-outline-danger remove-row w-100" title="Quitar fila"><i class="bi bi-dash-lg"></i></button>
                            </div>
                        </div>
                    @endforeach
                </div>
                @error('items')<div class="alert alert-danger mt-2">{{ $message }}</div>@enderror

                <button type="button" id="addRow" class="btn btn-outline-primary fw-bold mt-2"><i class="bi bi-plus-lg me-1"></i> Agregar insumo</button>

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary fw-bold"><i class="bi bi-check-lg me-1"></i> Guardar Borrador</button>
                    <a href="{{ route('kitchen_inventory.recipes.show', $version->recipe_id) }}" class="btn btn-outline-secondary fw-bold">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<template id="itemRowTemplate">
    <div class="row g-2 mb-2 item-row align-items-end">
        <div class="col-md-7">
            <select name="items[__INDEX__][kitchen_supply_id]" class="form-select supply-select" required>
                <option value="">Seleccione insumo...</option>
                @foreach($supplies as $supply)
                    <option value="{{ $supply->id }}">{{ $supply->name }} ({{ $supply->unit }})</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <input type="number" name="items[__INDEX__][quantity]" step="0.0001" min="0.0001" class="form-control" placeholder="0.0000" required>
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-outline-danger remove-row w-100" title="Quitar fila"><i class="bi bi-dash-lg"></i></button>
        </div>
    </div>
</template>

<script>
(function () {
    const container = document.getElementById('itemsContainer');
    const template = document.getElementById('itemRowTemplate');

    document.getElementById('addRow').addEventListener('click', function () {
        const index = container.querySelectorAll('.item-row').length;
        const html = template.innerHTML.replaceAll('__INDEX__', String(index));
        container.insertAdjacentHTML('beforeend', html);
    });

    container.addEventListener('click', function (event) {
        const button = event.target.closest('.remove-row');
        if (!button) return;
        const rows = container.querySelectorAll('.item-row');
        if (rows.length <= 1) {
            rows[0].querySelector('.supply-select').value = '';
            rows[0].querySelector('input[type="number"]').value = '';
            return;
        }
        button.closest('.item-row').remove();
    });
})();
</script>
@endsection
