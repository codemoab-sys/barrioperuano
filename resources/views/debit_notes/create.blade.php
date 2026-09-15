@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <div class="mb-3">
                <a href="{{ route('billing.show', $order) }}" class="text-decoration-none small">
                    <i class="bi bi-arrow-left"></i> Volver al comprobante
                </a>
                <h2 class="fw-bold mb-0 mt-1">
                    <i class="bi bi-arrow-clockwise text-info me-2"></i>
                    Emitir Nota de Débito
                </h2>
                <div class="text-muted">
                    Afecta a: <strong>{{ $order->document_type }} {{ $order->full_number }}</strong>
                    · Total S/ {{ number_format($order->total, 2) }}
                </div>
            </div>

            <form action="{{ route('debit_notes.store', $order) }}" method="POST"
                  class="card border-0 shadow-sm">
                @csrf
                <div class="card-body p-4">

                    <div class="alert alert-info py-2 small">
                        <i class="bi bi-info-circle"></i>
                        La nota de débito se enviará a SUNAT inmediatamente. Si tiene errores podrás reintentar después.
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Motivo (Catálogo 10 SUNAT) <span class="text-danger">*</span></label>
                        <select name="reason_code" class="form-select" required>
                            <option value="">Seleccionar motivo...</option>
                            @foreach($reasons as $code => $desc)
                                <option value="{{ $code }}">{{ $code }} — {{ $desc }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Descripción del motivo <span class="text-danger">*</span></label>
                        <textarea name="reason_description" class="form-control" rows="3" maxlength="255" required
                                  placeholder="Ej: Intereses por mora por pago fuera de plazo"></textarea>
                        <small class="text-muted">Máximo 255 caracteres.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Monto adicional <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">S/</span>
                            <input type="number" name="amount" class="form-control" min="0.01" step="0.01" required placeholder="0.00">
                        </div>
                        <small class="text-muted">Ingresa solo el importe adicional que se cobrará.</small>
                    </div>

                    <div class="card bg-light border-0">
                        <div class="card-body p-3">
                            <h6 class="fw-bold mb-2">Documento afectado</h6>
                            <table class="table table-sm mb-0">
                                <tr><th style="width:40%">Comprobante:</th><td>{{ $order->full_number }}</td></tr>
                                <tr><th>Cliente:</th><td>{{ $order->client_name }} ({{ $order->client_document }})</td></tr>
                                <tr><th>Fecha de emisión:</th><td>{{ $order->created_at->format('d/m/Y') }}</td></tr>
                                <tr><th>Importe a debitar:</th><td><strong>S/ {{ number_format($order->total, 2) }}</strong></td></tr>
                            </table>
                        </div>
                    </div>

                    <div class="mt-4 d-flex justify-content-end gap-2">
                        <a href="{{ route('billing.show', $order) }}" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-info fw-bold text-white"
                                onclick="return confirm('¿Emitir nota de débito? Se enviará a SUNAT.')">
                            <i class="bi bi-send"></i> Emitir y enviar a SUNAT
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
