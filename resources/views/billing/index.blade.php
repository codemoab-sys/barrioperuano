@extends('layouts.app')

@section('content')
<div class="container-fluid">

    {{-- Cabecera --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-0">
                <i class="bi bi-receipt-cutoff me-2"></i>Comprobantes Electrónicos
            </h2>
            <p class="text-muted mb-0">Boletas y Facturas emitidas a SUNAT</p>
        </div>
        <div>
            <a href="{{ route('credit_notes.index') }}" class="btn btn-outline-warning me-2">
                <i class="bi bi-arrow-counterclockwise"></i> Notas de Crédito
            </a>
        </div>
    </div>

    {{-- Stats por estado --}}
    <div class="row g-3 mb-4">
        @foreach([
            'ACCEPTED' => ['Aceptados',  'success',   'check-circle-fill'],
            'OBSERVED' => ['Observados', 'warning',   'exclamation-triangle-fill'],
            'PENDING'  => ['Pendientes', 'secondary', 'hourglass-split'],
            'ERROR'    => ['Con error',  'danger',    'x-circle-fill'],
            'REJECTED' => ['Rechazados', 'danger',    'slash-circle-fill'],
        ] as $key => [$label, $color, $icon])
            <div class="col-6 col-md">
                <div class="card border-0 shadow-sm border-start border-{{ $color }} border-3">
                    <div class="card-body p-2 d-flex align-items-center">
                        <i class="bi bi-{{ $icon }} fs-3 text-{{ $color }} me-2"></i>
                        <div>
                            <div class="small text-muted">{{ $label }}</div>
                            <div class="fs-5 fw-bold">{{ $stats[$key] ?? 0 }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Filtros --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Tipo</label>
                    <select name="type" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="Boleta"  {{ request('type') === 'Boleta'  ? 'selected' : '' }}>Boleta</option>
                        <option value="Factura" {{ request('type') === 'Factura' ? 'selected' : '' }}>Factura</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Estado</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        @foreach(['PENDING','ACCEPTED','OBSERVED','REJECTED','ERROR','VOIDED'] as $st)
                            <option value="{{ $st }}" {{ request('status') === $st ? 'selected' : '' }}>{{ $st }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Desde</label>
                    <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Hasta</label>
                    <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label small mb-1">Buscar</label>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Serie, número, cliente, RUC/DNI"
                           class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-auto">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel"></i></button>
                </div>
            </form>
        </div>
    </div>

    {{-- Tabla --}}
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Comprobante</th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th class="text-end">Total</th>
                        <th>Estado SUNAT</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $o)
                        <tr>
                            <td>
                                <span class="badge bg-{{ $o->document_type === 'Factura' ? 'primary' : 'info' }}">
                                    {{ $o->document_type }}
                                </span>
                                <span class="fw-bold ms-1">{{ $o->full_number }}</span>
                            </td>
                            <td class="small">{{ ($o->issued_at ?? $o->created_at)->format('d/m/Y H:i') }}</td>
                            <td class="small">
                                <div>{{ $o->client_name ?: 'Cliente' }}</div>
                                <div class="text-muted">{{ $o->client_document }}</div>
                            </td>
                            <td class="text-end fw-bold">S/ {{ number_format($o->total, 2) }}</td>
                            <td>
                                @php
                                    $color = match($o->sunat_status) {
                                        'ACCEPTED' => 'success',
                                        'OBSERVED' => 'warning',
                                        'PENDING'  => 'secondary',
                                        'VOIDED'   => 'dark',
                                        default    => 'danger',
                                    };
                                @endphp
                                <span class="badge bg-{{ $color }}">{{ $o->sunat_status }}</span>
                                @if($o->sunat_code)
                                    <small class="text-muted d-block">[{{ $o->sunat_code }}]</small>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('billing.show', $o) }}" class="btn btn-outline-secondary" title="Ver">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    @if($o->pdf_path)
                                        @php
                                            $clientPhone = $o->client?->phone ?? '';
                                            $whatsappBase = "📄 {$o->document_type} {$o->full_number}\n💰 Total: S/ " . number_format($o->total, 2) . "\n🔗 {$o->pdf_path}";
                                        @endphp
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" title="Enviar" style="font-size:1.1rem;letter-spacing:2px;">
                                                ⋮
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                @if(!empty($clientPhone))
                                                    <li>
                                                        <a href="https://wa.me/51{{ $clientPhone }}?text={{ urlencode($whatsappBase) }}" target="_blank" class="dropdown-item">
                                                            <i class="bi bi-whatsapp me-2 text-success"></i>WhatsApp: {{ $clientPhone }}
                                                        </a>
                                                    </li>
                                                @else
                                                    <li>
                                                        <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#whatsappModal-{{ $o->id }}">
                                                            <i class="bi bi-whatsapp me-2 text-success"></i>WhatsApp (pedir número)
                                                        </button>
                                                    </li>
                                                @endif
                                                <li>
                                                    <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#emailModal-{{ $o->id }}">
                                                        <i class="bi bi-envelope me-2"></i>Correo
                                                    </button>
                                                </li>
                                                <li>
                                                    <a href="{{ route('billing.pdf.ticket', $o) }}" target="_blank" class="dropdown-item">
                                                        <i class="bi bi-printer me-2"></i>Imprimir
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    @endif
                                    @if($o->xml_path || $o->sunat_status === 'ACCEPTED')
                                        <a href="{{ route('billing.xml', $o) }}" class="btn btn-outline-info" title="XML">
                                            <i class="bi bi-filetype-xml"></i>
                                        </a>
                                    @endif
                                    @if($o->cdr_path || $o->sunat_status === 'ACCEPTED')
                                        <a href="{{ route('billing.cdr', $o) }}" class="btn btn-outline-success" title="CDR">
                                            <i class="bi bi-archive"></i>
                                        </a>
                                    @endif
                                    @if(in_array($o->sunat_status, ['ERROR','REJECTED']))
                                        <form method="POST" action="{{ route('billing.retry', $o) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-outline-warning" title="Reintentar envío"
                                                    onclick="return confirm('¿Reintentar envío a SUNAT?')">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </button>
                                        </form>
                                    @endif
                                    @if(in_array($o->sunat_status, ['PENDING', 'OBSERVED']) || $o->sunat_code === '23' || str_contains((string) $o->sunat_description, 'Este documento ya existe'))
                                        <form method="POST" action="{{ route('billing.sync', $o) }}" class="d-inline sync-status-form">
                                            @csrf
                                            <button class="btn btn-outline-info" title="Consultar estado"><i class="bi bi-arrow-clockwise"></i></button>
                                        </form>
                                    @endif
                                    @if($o->sunat_status === 'ACCEPTED' && $o->anulacion_status !== 'ACCEPTED')
                                        <button class="btn btn-outline-danger" title="Anular" data-bs-toggle="modal" data-bs-target="#cancelModal-{{ $o->id }}">
                                            <i class="bi bi-x-circle"></i>
                                        </button>
                                    @endif
                                    @if($o->anulacion_status === 'PENDING')
                                        <form method="POST" action="{{ route('billing.cancel.query', $o) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-outline-warning" title="Consultar anulación"><i class="bi bi-arrow-repeat"></i></button>
                                        </form>
                                    @endif
                                    @if($o->anulacion_status === 'ACCEPTED')
                                        @if($o->anulacion_pdf_path)
                                            <a href="{{ route('billing.cancellation.pdf', $o) }}" class="btn btn-outline-danger" title="PDF de anulación"><i class="bi bi-file-earmark-pdf"></i></a>
                                        @endif
                                        @if($o->anulacion_xml_path)
                                            <a href="{{ route('billing.cancellation.xml', $o) }}" class="btn btn-outline-info" title="XML de anulación"><i class="bi bi-filetype-xml"></i></a>
                                        @endif
                                        @if($o->anulacion_cdr_path)
                                            <a href="{{ route('billing.cancellation.cdr', $o) }}" class="btn btn-outline-success" title="CDR de anulación"><i class="bi bi-archive"></i></a>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                Sin comprobantes que coincidan con los filtros.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">
            {{ $orders->links() }}
        </div>
    </div>

    @foreach($orders as $o)
        @if($o->sunat_status === 'ACCEPTED' && $o->anulacion_status !== 'ACCEPTED')
            <div class="modal fade" id="cancelModal-{{ $o->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-sm">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white py-2">
                            <h6 class="modal-title fw-bold">Anular {{ $o->document_type }} {{ $o->full_number }}</h6>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST" action="{{ route('billing.cancel', $o) }}" class="cancellation-form">
                            @csrf
                            <div class="modal-body">
                                <label class="form-label small fw-bold">Motivo de anulación</label>
                                <textarea name="motivo" class="form-control" maxlength="100" required placeholder="Ej: ERROR DE SISTEMA"></textarea>
                                <small class="text-muted">El proveedor electrónico devolverá un ticket si la aceptación queda pendiente.</small>
                            </div>
                            <div class="modal-footer py-2">
                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-danger btn-sm">Enviar anulación</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
        @if($o->pdf_path)
            @if(empty($o->client?->phone))
            <div class="modal fade" id="whatsappModal-{{ $o->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-sm">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white py-2">
                            <h6 class="modal-title fw-bold"><i class="bi bi-whatsapp me-1"></i>Enviar por WhatsApp</h6>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="small text-muted mb-2">{{ $o->document_type }} {{ $o->full_number }}</p>
                            <p class="small mb-2">El cliente <strong>{{ $o->client_name ?: 'sin nombre' }}</strong> no tiene teléfono registrado.</p>
                            <div class="mb-2">
                                <label class="form-label small fw-bold">Número de celular <span class="text-danger">*</span></label>
                                <input type="text" id="wa-phone-{{ $o->id }}" class="form-control form-control-sm"
                                       placeholder="Ej: 999888777" maxlength="9" pattern="[0-9]{9}">
                            </div>
                            <div class="d-flex justify-content-end gap-1">
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                                <button type="button" class="btn btn-success btn-sm" onclick="openWhatsApp({{ $o->id }}, `{!! addslashes($whatsappBase ?? '') !!}`)">
                                    <i class="bi bi-whatsapp me-1"></i>Abrir WhatsApp
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        <div class="modal fade" id="emailModal-{{ $o->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white py-2">
                        <h6 class="modal-title fw-bold"><i class="bi bi-envelope me-1"></i>Enviar por Correo</h6>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted mb-2">{{ $o->document_type }} {{ $o->full_number }}</p>
                        <form action="{{ route('billing.sendEmail', $o) }}" method="POST">
                            @csrf
                            <div class="mb-2">
                                <input type="email" name="email" class="form-control form-control-sm" required
                                       value="{{ $o->client?->email ?? '' }}" placeholder="correo@cliente.com">
                            </div>
                            <div class="mb-2">
                                <textarea name="message" class="form-control form-control-sm" rows="1" maxlength="500"
                                          placeholder="Mensaje opcional..."></textarea>
                            </div>
                            <div class="d-flex justify-content-end gap-1">
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-send me-1"></i>Enviar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        @endif
    @endforeach
</div>

<script>
    function showNubeFactResponse(data) {
        if (!data.success) {
            Swal.fire({
                icon: 'error',
                title: 'Error al consultar',
                text: data.message || 'El proveedor electrónico no devolvió una respuesta válida.',
                confirmButtonText: 'Entendido'
            });
            return;
        }

        const json = JSON.stringify(data.response || {}, null, 2);
        const escapedJson = json.replace(/[&<>"']/g, function (character) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[character];
        });

        Swal.fire({
            icon: data.status === 'ACCEPTED' ? 'success' : 'info',
            title: 'Respuesta del proveedor electrónico',
            html: '<pre class="text-start small mb-0" style="max-height:55vh;overflow:auto;white-space:pre-wrap;">' + escapedJson + '</pre>',
            width: 700,
            confirmButtonText: 'Cerrar'
        });
    }

    document.querySelectorAll('.sync-status-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            const button = form.querySelector('button');
            const original = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new FormData(form)
            })
                .then(function (response) { return response.json(); })
                .then(showNubeFactResponse)
                .catch(function () {
                    Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo consultar el proveedor electrónico.' });
                })
                .finally(function () {
                    button.disabled = false;
                    button.innerHTML = original;
                });
        });
    });

function openWhatsApp(orderId, baseMsg) {
    const phone = document.getElementById('wa-phone-' + orderId).value.trim();
    if (!phone || phone.length < 9) {
        Swal.fire({
            icon: 'warning',
            title: 'Número inválido',
            text: 'Ingresa un número de celular válido de 9 dígitos.',
            confirmButtonText: 'Entendido'
        });
        return;
    }
    window.open('https://wa.me/51' + phone + '?text=' + encodeURIComponent(baseMsg), '_blank');
}
</script>

@push('scripts')
<script>
    document.querySelectorAll('.cancellation-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();

            Swal.fire({
                icon: 'warning',
                title: '¿Enviar anulación?',
                text: 'El proveedor electrónico procesará el comprobante seleccionado. Esta operación no debe repetirse.',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, enviar',
                cancelButtonText: 'Cancelar'
            }).then(function (result) {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });

</script>
@endpush
@endsection
