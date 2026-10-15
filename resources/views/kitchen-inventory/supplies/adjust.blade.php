@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="mb-4">
        <a href="{{ route('kitchen_inventory.supplies.index') }}" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left"></i> Volver a Insumos</a>
        <h2 class="fw-bold text-dark mb-0"><i class="bi bi-sliders me-2"></i>Ajustar Stock: {{ $supply->name }}</h2>
        <p class="text-muted mb-0">Todo movimiento queda auditado en el kardex con su motivo.</p>
    </div>

    <div class="card border-0 shadow-sm" style="max-width: 640px;">
        <div class="card-body">
            <div class="alert alert-light border mb-3">
                Stock actual: <strong class="fs-5">{{ rtrim(rtrim(number_format($supply->stock_current, 4, '.', ''), '0'), '.') }} {{ $supply->unit }}</strong>
            </div>

            <form method="POST" action="{{ route('kitchen_inventory.supplies.adjust', $supply) }}">
                @csrf
                <input type="hidden" name="form_uuid" value="{{ \Illuminate\Support\Str::uuid() }}">
                <div class="mb-3">
                    <label class="form-label fw-bold">Tipo de movimiento <span class="text-danger">*</span></label>
                    <select name="type" class="form-select @error('type') is-invalid @enderror" required>
                        <option value="entry" @selected(old('type') === 'entry')>Entrada (compra / recepción / stock inicial)</option>
                        <option value="adjustment_in" @selected(old('type') === 'adjustment_in')>Ajuste + (corrección al alza)</option>
                        <option value="adjustment_out" @selected(old('type') === 'adjustment_out')>Ajuste - (corrección a la baja)</option>
                        <option value="waste" @selected(old('type') === 'waste')>Merma (descarte / vencimiento)</option>
                    </select>
                    @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Cantidad ({{ $supply->unit }}) <span class="text-danger">*</span></label>
                    <input type="number" name="quantity" value="{{ old('quantity') }}" class="form-control @error('quantity') is-invalid @enderror" step="0.0001" min="0.0001" required>
                    @error('quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Motivo <span class="text-danger">*</span></label>
                    <textarea name="note" rows="3" class="form-control @error('note') is-invalid @enderror" maxlength="500" placeholder="Ej.: Stock inicial del insumo / Recepción de compra N° 001 / Merma por vencimiento" required>{{ old('note') }}</textarea>
                    @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                @error('form_uuid')<div class="alert alert-warning">{{ $message }}</div>@enderror
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success fw-bold"><i class="bi bi-check-lg me-1"></i> Registrar Movimiento</button>
                    <a href="{{ route('kitchen_inventory.supplies.index') }}" class="btn btn-outline-secondary fw-bold">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
