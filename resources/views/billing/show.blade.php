@extends('layouts.app')

@section('content')
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <a href="{{ route('billing.index') }}" class="text-decoration-none">
                <i class="bi bi-arrow-left"></i> Comprobantes
            </a>
            <h2 class="fw-bold mb-0 mt-2">
                {{ $order->document_type }} {{ $order->full_number }}
                @php
                    $color = match($order->sunat_status) {
                        'ACCEPTED'  => 'success',
                        'OBSERVED'  => 'warning',
                        'PENDING'   => 'secondary',
                        'NO_ENVIADO'=> 'info',
                        'VOIDED'    => 'dark',
                        default     => 'danger',
                    };
                @endphp
                <span class="badge bg-{{ $color }} ms-2">{{ $order->sunat_status }}</span>
            </h2>
        </div>
        <div class="btn-group">
            <a href="{{ route('billing.pdf', $order) }}" target="_blank" rel="noopener" class="btn btn-danger">
                <i class="bi bi-file-earmark-pdf"></i> PDF oficial
            </a>
            @if($order->pdf_path)
                @php
                    $clientPhone = $order->client?->phone ?? '';
                    $whatsappBase = "📄 {$order->document_type} {$order->full_number}\n💰 Total: S/ " . number_format($order->total, 2) . "\n🔗 {$order->pdf_path}";
                @endphp
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" title="Enviar" style="font-size:1.1rem;letter-spacing:2px;">
                        ⋮
                    </button>
                    <ul class="dropdown-menu">
                        @if(!empty($clientPhone))
                            <li>
                                <a href="https://wa.me/51{{ $clientPhone }}?text={{ urlencode($whatsappBase) }}" target="_blank" class="dropdown-item">
                                    <i class="bi bi-whatsapp me-2 text-success"></i>WhatsApp: {{ $clientPhone }}
                                </a>
                            </li>
                        @else
                            <li>
                                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#whatsappModal">
                                    <i class="bi bi-whatsapp me-2 text-success"></i>WhatsApp (pedir número)
                                </button>
                            </li>
                        @endif
                        <li>
                            <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#emailModal">
                                <i class="bi bi-envelope me-2"></i>Enviar por Correo
                            </button>
                        </li>
                        <li>
                            <a href="{{ route('billing.pdf', $order) }}" target="_blank" rel="noopener" class="dropdown-item">
                                <i class="bi bi-file-earmark-pdf me-2"></i>PDF oficial
                            </a>
                        </li>
                    </ul>
                </div>
            @endif
            @if($order->xml_path || $order->sunat_status === 'ACCEPTED')
                <a href="{{ route('billing.xml', $order) }}" class="btn btn-outline-info">
                    <i class="bi bi-filetype-xml"></i> XML
                </a>
            @endif
            @if($order->cdr_path || $order->sunat_status === 'ACCEPTED')
                <a href="{{ route('billing.cdr', $order) }}" class="btn btn-outline-success">
                    <i class="bi bi-archive"></i> CDR
                </a>
            @endif
            @if(in_array($order->sunat_status, ['PENDING','ERROR','REJECTED','NO_ENVIADO']))
                <form method="POST" action="{{ route('billing.retry', $order) }}" class="d-inline">
                    @csrf
                    <button class="btn btn-warning" onclick="return confirm('¿Reintentar envío a SUNAT?')">
                        <i class="bi bi-arrow-repeat"></i> Reintentar
                    </button>
                </form>
            @endif
            @if($order->sunat_status === 'ACCEPTED')
                <a href="{{ route('credit_notes.create', $order) }}" class="btn btn-outline-warning">
                    <i class="bi bi-arrow-counterclockwise"></i> Emitir N.C.
                </a>
                <a href="{{ route('debit_notes.create', $order) }}" class="btn btn-outline-info">
                    <i class="bi bi-arrow-clockwise"></i> Emitir N.D.
                </a>
            @endif
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <div class="row g-3">
        <div class="col-md-7">
            <div class="card border-0 shadow-sm billing-detail-card">
                <div class="card-header bg-white"><strong>Detalle del Comprobante</strong></div>
                <div class="card-body p-0">
                    <div class="table-responsive billing-detail-table-wrap">
                    <table class="table table-sm mb-0 billing-detail-table">
                        <colgroup>
                            <col class="billing-detail-qty">
                            <col class="billing-detail-product">
                            <col class="billing-detail-price">
                            <col class="billing-detail-subtotal">
                        </colgroup>
                        <thead class="table-light">
                            <tr>
                                <th>Cant</th><th>Producto</th>
                                <th class="text-end">Precio</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->details as $d)
                                <tr>
                                    <td>{{ $d->quantity }}</td>
                                    <td>{{ $d->product->name ?? '—' }}</td>
                                    <td class="text-end">S/ {{ number_format($d->price, 2) }}</td>
                                    <td class="text-end">S/ {{ number_format($d->price * $d->quantity, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr><th colspan="3" class="text-end">Op. Gravada:</th>
                                <td class="text-end">S/ {{ number_format($order->total_gravada, 2) }}</td></tr>
                            <tr><th colspan="3" class="text-end">IGV ({{ rtrim(rtrim(number_format($igvRate, 2, '.', ''), '0'), '.') }}%):</th>
                                <td class="text-end">S/ {{ number_format($order->igv, 2) }}</td></tr>
                            <tr class="table-success">
                                <th colspan="3" class="text-end">TOTAL:</th>
                                <th class="text-end">S/ {{ number_format($order->total, 2) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                    </div>
                </div>
            </div>

            @if($order->creditNotes->isNotEmpty())
                <div class="card border-0 shadow-sm mt-3">
                    <div class="card-header bg-white"><strong>Notas de Crédito asociadas</strong></div>
                    <ul class="list-group list-group-flush">
                        @foreach($order->creditNotes as $cn)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <a href="{{ route('credit_notes.show', $cn) }}" class="fw-bold">{{ $cn->full_number }}</a>
                                    <span class="text-muted small">— [{{ $cn->reason_code }}] {{ $cn->reason_description }}</span>
                                </div>
                                <span class="badge bg-{{ $cn->sunat_status === 'ACCEPTED' ? 'success' : 'danger' }}">{{ $cn->sunat_status }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <div class="col-md-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><strong>Datos del Cliente</strong></div>
                <div class="card-body small">
                    <div><strong>Nombre:</strong> {{ $order->client_name }}</div>
                    <div><strong>Documento:</strong> {{ $order->client_document }}</div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mt-3">
                <div class="card-header bg-white"><strong>Responsables</strong></div>
                <div class="card-body small">
                    <div><strong>Creó la orden:</strong> {{ $order->user?->name ?? '—' }}</div>
                    <div><strong>Generó el comprobante:</strong> {{ $order->issuedBy?->name ?? '—' }}</div>
                    <div><strong>Fecha de generación:</strong> {{ $order->issued_at?->format('d/m/Y H:i:s') ?? '—' }}</div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mt-3">
                <div class="card-header bg-white"><strong>Trazabilidad SUNAT</strong></div>
                <div class="card-body small">
                    <div><strong>Estado:</strong> <span class="badge bg-{{ $color }}">{{ $order->sunat_status }}</span></div>
                    <div><strong>Código:</strong> {{ $order->sunat_code ?? '—' }}</div>
                    <div><strong>Descripción:</strong> {{ $order->sunat_description ?? '—' }}</div>
                    <div><strong>Hash:</strong> <code>{{ $order->hash ?? '—' }}</code></div>
                    <div><strong>Enviado:</strong> {{ $order->sent_at?->format('d/m/Y H:i:s') ?? '—' }}</div>
                    <div class="mt-2"><strong>XML:</strong> <code class="small">{{ $order->xml_path ?? '—' }}</code></div>
                    <div><strong>CDR:</strong> <code class="small">{{ $order->cdr_path ?? '—' }}</code></div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .billing-detail-table {
        width: 100%;
        max-width: 100%;
        table-layout: fixed;
    }

    .billing-detail-card,
    .billing-detail-table-wrap {
        min-width: 0;
        max-width: 100%;
    }

    .billing-detail-qty { width: 12%; }
    .billing-detail-product { width: 42%; }
    .billing-detail-price,
    .billing-detail-subtotal { width: 23%; }

    .billing-detail-table th,
    .billing-detail-table td {
        overflow-wrap: anywhere;
        vertical-align: middle;
    }

    .billing-detail-table td.text-end,
    .billing-detail-table th.text-end {
        white-space: nowrap;
    }

    @media (max-width: 576px) {
        .billing-detail-table {
            font-size: .82rem;
        }

        .billing-detail-table th,
        .billing-detail-table td {
            padding-left: .35rem;
            padding-right: .5rem;
        }

        .billing-detail-table tfoot th.text-end {
            white-space: normal;
            overflow-wrap: anywhere;
        }
    }
</style>

{{-- Modal Enviar PDF --}}
@if($order->pdf_path)
@if(empty($order->client?->phone))
<div class="modal fade" id="whatsappModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-success text-white py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-whatsapp me-1"></i>Enviar por WhatsApp</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">{{ $order->document_type }} {{ $order->full_number }}</p>
                <p class="small mb-2">El cliente <strong>{{ $order->client_name ?: 'sin nombre' }}</strong> no tiene teléfono registrado.</p>
                <div class="mb-2">
                    <label class="form-label small fw-bold">Número de celular <span class="text-danger">*</span></label>
                    <input type="text" id="wa-phone-show" class="form-control form-control-sm"
                           placeholder="Ej: 999888777" maxlength="9" pattern="[0-9]{9}">
                </div>
                <div class="d-flex justify-content-end gap-1">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success btn-sm" onclick="openWhatsAppShow()">
                        <i class="bi bi-whatsapp me-1"></i>Abrir WhatsApp
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
<div class="modal fade" id="emailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-envelope me-2"></i>Enviar por Correo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Se enviará el PDF de <strong>{{ $order->document_type }} {{ $order->full_number }}</strong> al correo del cliente.
                </p>
                <form action="{{ route('billing.sendEmail', $order) }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-bold">Correo del cliente <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required
                               value="{{ $order->client?->email ?? '' }}"
                               placeholder="cliente@correo.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Mensaje (opcional)</label>
                        <textarea name="message" class="form-control" rows="2" maxlength="500"
                                  placeholder="Adjuntamos su comprobante electrónico..."></textarea>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send me-1"></i> Enviar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

<script>
function openWhatsAppShow() {
    const phone = document.getElementById('wa-phone-show').value.trim();
    if (!phone || phone.length < 9) {
        alert('Ingresa un número de celular válido (9 dígitos).');
        return;
    }
    const msg = {!! json_encode($whatsappBase ?? '') !!};
    window.open('https://wa.me/51' + phone + '?text=' + encodeURIComponent(msg), '_blank');
}
</script>
@endsection
