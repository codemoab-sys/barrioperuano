@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-0 text-gray-800"><i class="bi bi-bicycle me-2 text-primary"></i>Delivery #{{ $delivery->id }}</h3>
            <span class="badge rounded-pill mt-2" style="background-color: {{ \App\Models\Delivery::$statusColors[$delivery->status] ?? '#6c757d' }}">
                {{ \App\Models\Delivery::$statusLabels[$delivery->status] ?? $delivery->status }}
            </span>
        </div>
        <div>
            <a href="{{ route('delivery.index') }}" class="btn btn-outline-secondary me-2">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
            @if(!in_array($delivery->status, ['delivered', 'cancelled']))
                <button class="btn btn-danger me-2" id="cancelBtn">
                    <i class="bi bi-x-circle me-1"></i> Cancelar
                </button>
                <form id="cancel-form" action="{{ route('delivery.cancel', $delivery) }}" method="POST" class="d-none">@csrf</form>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        {{-- Detalles del Cliente y Estado --}}
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-person-lines-fill me-2"></i>Datos del Cliente</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <small class="text-muted d-block fw-bold">Nombre / Teléfono</small>
                        <div class="fw-bold fs-6">{{ $delivery->client_name }}</div>
                        <div><i class="bi bi-telephone text-primary me-2"></i>{{ $delivery->client_phone }}</div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block fw-bold">Dirección de Entrega</small>
                        <div><i class="bi bi-geo-alt-fill text-danger me-2"></i>{{ $delivery->address }}</div>
                        @if($delivery->reference)
                            <div class="text-muted small mt-1"><i class="bi bi-info-circle me-1"></i>{{ $delivery->reference }}</div>
                        @endif
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block fw-bold">Método de Pago</small>
                        <div class="text-capitalize"><i class="bi bi-credit-card-2-front me-2"></i>
                            @if($delivery->payment_method == 'cash') Efectivo
                            @elseif($delivery->payment_method == 'card') Tarjeta
                            @elseif($delivery->payment_method == 'transfer') Transferencia
                            @elseif($delivery->payment_method == 'yape') Yape
                            @elseif($delivery->payment_method == 'plin') Plin
                            @else {{ $delivery->payment_method }} @endif
                        </div>
                    </div>
                    @if($delivery->notes)
                        <div class="mb-3">
                            <small class="text-muted d-block fw-bold">Notas</small>
                            <div class="bg-light p-2 rounded small border">{{ $delivery->notes }}</div>
                        </div>
                    @endif
                </div>
            </div>

            @if(!in_array($delivery->status, ['delivered', 'cancelled']))
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="fw-bold mb-0"><i class="bi bi-gear me-2"></i>Gestión de Envío</h6>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('delivery.driver', $delivery) }}" method="POST" class="mb-4">
                            @csrf
                            <label class="form-label small fw-bold">Repartidor Asignado</label>
                            <div class="input-group">
                                <select name="driver_id" class="form-select">
                                    <option value="">Sin asignar</option>
                                    @foreach($drivers as $driver)
                                        <option value="{{ $driver->id }}" {{ $delivery->driver_id == $driver->id ? 'selected' : '' }}>{{ $driver->name }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-outline-primary" type="submit">Actualizar</button>
                            </div>
                        </form>

                        <form id="statusForm" action="{{ route('delivery.status', $delivery) }}" method="POST">
                            @csrf
                            <label class="form-label small fw-bold">Cambiar Estado</label>
                            <div class="input-group">
                                <select name="status" class="form-select">
                                    @foreach($statuses as $key => $label)
                                        @if($key != 'cancelled' && $key != 'delivered')
                                            <option value="{{ $key }}" {{ $delivery->status == $key ? 'selected' : '' }}>{{ $label }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                <button class="btn btn-outline-success" type="submit">Cambiar</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>

        {{-- Detalles de la Orden y Cobro --}}
        <div class="col-12 col-md-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0"><i class="bi bi-receipt me-2"></i>Detalle de la Orden</h6>
                    <small class="text-muted"><i class="bi bi-clock me-1"></i>Pedido: {{ $delivery->created_at->format('d/m/Y H:i') }}</small>
                </div>
                <div class="card-body d-flex flex-column p-0">
                    <div class="table-responsive flex-grow-1 p-3">
                        <table class="table table-borderless align-middle">
                            <thead class="border-bottom text-muted small">
                                <tr>
                                    <th>CANT.</th>
                                    <th>PRODUCTO</th>
                                    <th class="text-end">P.UNIT</th>
                                    <th class="text-end">TOTAL</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($delivery->order->details as $detail)
                                    <tr class="border-bottom">
                                        <td class="fw-bold">{{ $detail->quantity }}</td>
                                        <td>
                                            <span class="fw-bold">{{ $detail->product->name }}</span>
                                            @if($detail->note)
                                                <br><small class="text-muted"><i class="bi bi-chat-left-text me-1"></i>{{ $detail->note }}</small>
                                            @endif
                                            @if($detail->sauce_choices)
                                                <br><small class="text-danger fw-bold">Sabores: {{ implode(', ', $detail->sauce_choices) }}</small>
                                            @endif
                                        </td>
                                        <td class="text-end text-muted">{{ $currency }}{{ number_format($detail->price, 2) }}</td>
                                        <td class="text-end fw-bold">{{ $currency }}{{ number_format($detail->quantity * $detail->price, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="bg-light p-4 border-top flex-shrink-0">
                        <div class="row">
                            <div class="col-md-6 offset-md-6">
                                <div class="d-flex justify-content-between mb-2 text-muted">
                                    <span>Subtotal de productos:</span>
                                    <span>{{ $currency }}{{ number_format($delivery->order->total, 2) }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-3 text-muted">
                                    <span>Costo de envío:</span>
                                    <span>+ {{ $currency }}{{ number_format($delivery->delivery_fee, 2) }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-4 fs-4 fw-bold border-top pt-2">
                                    <span>TOTAL:</span>
                                    <span class="text-primary">{{ $currency }}{{ number_format($delivery->total_with_fee, 2) }}</span>
                                </div>
                                
                                @if(!in_array($delivery->status, ['delivered', 'cancelled']))
                                    <button class="btn btn-success btn-lg w-100 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#checkoutModal">
                                        <i class="bi bi-check-circle me-2"></i> MARCAR COMO ENTREGADO Y COBRAR
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- MODAL DE COBRO --}}
@if(!in_array($delivery->status, ['delivered', 'cancelled']))
<div class="modal fade" id="checkoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-cash-coin me-2"></i>Completar Delivery</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('delivery.checkout', $delivery) }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <div class="text-muted small fw-bold mb-1">TOTAL A COBRAR</div>
                        <h2 class="display-4 fw-bold text-success mb-0">{{ $currency }}{{ number_format($delivery->total_with_fee, 2) }}</h2>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Comprobante</label>
                        <select name="document_type" class="form-select form-select-lg">
                            <option value="Ticket">Ticket de Venta</option>
                            <option value="Boleta">Boleta Electrónica</option>
                            <option value="Factura">Factura Electrónica</option>
                        </select>
                    </div>

                    @if($delivery->payment_method === 'cash')
                        <div class="mb-3">
                            <label class="form-label fw-bold">Monto Recibido</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text">{{ $currency }}</span>
                                <input type="number" step="0.01" name="received_amount" id="received_amount" class="form-control fw-bold text-end" value="{{ number_format($delivery->total_with_fee, 2, '.', '') }}" required>
                            </div>
                        </div>
                        <div class="mb-3 d-flex justify-content-between align-items-center bg-light p-3 rounded border">
                            <span class="fw-bold text-muted">Vuelto a entregar:</span>
                            <span class="fs-4 fw-bold text-danger" id="change_amount">{{ $currency }}0.00</span>
                        </div>
                    @else
                        <input type="hidden" name="received_amount" value="{{ $delivery->total_with_fee }}">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle-fill me-2"></i> Pago por <strong>{{ $delivery->payment_method === 'card' ? 'Tarjeta' : 'Transferencia' }}</strong> registrado previamente.
                        </div>
                    @endif
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success px-4 fw-bold shadow-sm"><i class="bi bi-check2-circle me-2"></i>Finalizar y Cobrar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const total = {{ number_format($delivery->total_with_fee, 2, '.', '') }};
        const receivedInput = document.getElementById('received_amount');
        const changeSpan = document.getElementById('change_amount');

        if(receivedInput) {
            receivedInput.addEventListener('input', function() {
                const received = parseFloat(this.value) || 0;
                let change = received - total;
                if(change < 0) change = 0;
                changeSpan.innerText = '{{ $currency }}' + change.toFixed(2);
            });
            // Focus and select all on show
            document.getElementById('checkoutModal').addEventListener('shown.bs.modal', function () {
                receivedInput.focus();
                receivedInput.select();
            });
        }

        // Cambiar estado del pedido (AJAX + SweetAlert2)
        const statusForm = document.getElementById('statusForm');
        if(statusForm) {
            statusForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const form = e.target;
                const btn = form.querySelector('button[type="submit"]');
                const original = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';

                fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: new FormData(form)
                })
                .then(r => r.json())
                .then(data => {
                    btn.disabled = false;
                    btn.innerHTML = original;
                    if(data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Estado actualizado',
                            text: 'Nuevo estado: ' + (data.label || ''),
                            timer: 1800,
                            showConfirmButton: false,
                            didClose: function() { window.location.href = "{{ route('delivery.index') }}"; }
                        });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'No se pudo actualizar el estado.' });
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.innerHTML = original;
                    Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo actualizar el estado.' });
                });
            });
        }

        // Confirmación de cancelación con SweetAlert2
        const cancelBtn = document.getElementById('cancelBtn');
        if(cancelBtn) {
            cancelBtn.addEventListener('click', function() {
                Swal.fire({
                    icon: 'warning',
                    title: '¿Cancelar este pedido?',
                    text: 'Esta acción no se puede deshacer.',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    confirmButtonText: 'Sí, cancelar',
                    cancelButtonText: 'No, volver'
                }).then(result => {
                    if(result.isConfirmed) document.getElementById('cancel-form').submit();
                });
            });
        }
    });
</script>
@endif

@endsection
