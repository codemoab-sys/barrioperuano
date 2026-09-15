@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-9">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <a href="{{ route('debit_notes.index') }}" class="text-decoration-none small">
                        <i class="bi bi-arrow-left"></i> Notas de Débito
                    </a>
                    <h2 class="fw-bold mb-0 mt-1">
                        Nota de Débito {{ $dn->full_number }}
                        @php
                            $color = $dn->sunat_status === 'ACCEPTED' ? 'success' :
                                     ($dn->sunat_status === 'OBSERVED' ? 'warning' :
                                     ($dn->sunat_status === 'PENDING' ? 'secondary' : 'danger'));
                        @endphp
                        <span class="badge bg-{{ $color }} ms-2">{{ $dn->sunat_status }}</span>
                    </h2>
                </div>
                <div class="btn-group">
                    @if($dn->pdf_path)
                        <a href="{{ route('debit_notes.pdf', $dn) }}" class="btn btn-outline-danger"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                    @endif
                    @if($dn->xml_path)
                        <a href="{{ route('debit_notes.xml', $dn) }}" class="btn btn-outline-info">
                            <i class="bi bi-filetype-xml"></i> XML
                        </a>
                    @endif
                    @if($dn->cdr_path)
                        <a href="{{ route('debit_notes.cdr', $dn) }}" class="btn btn-outline-success">
                            <i class="bi bi-archive"></i> CDR
                        </a>
                    @endif
                    @if(in_array($dn->sunat_status, ['PENDING','ERROR','REJECTED']))
                        <form method="POST" action="{{ route('debit_notes.retry', $dn) }}" class="d-inline">
                            @csrf
                            <button class="btn btn-warning" onclick="return confirm('¿Reintentar envío a SUNAT?')">
                                <i class="bi bi-arrow-repeat"></i> Reintentar
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
            @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-muted">Documento afectado</h6>
                    <a href="{{ route('billing.show', $dn->order_id) }}" class="fs-5 fw-bold">
                        {{ $dn->order?->document_type }} {{ $dn->order?->full_number }}
                    </a>

                    <hr>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="text-muted small">Motivo (Catálogo 10)</div>
                            <div><code>{{ $dn->reason_code }}</code> — {{ $dn->reason_description }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-muted small">Subtotal</div>
                            <div>S/ {{ number_format($dn->subtotal, 2) }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-muted small">IGV</div>
                            <div>S/ {{ number_format($dn->igv, 2) }}</div>
                        </div>
                        <div class="col-md-12">
                            <div class="text-muted small">Total</div>
                            <div class="fs-4 fw-bold">S/ {{ number_format($dn->total, 2) }}</div>
                        </div>
                    </div>

                    <hr>

                    <h6 class="fw-bold text-muted">Trazabilidad SUNAT</h6>
                    <table class="table table-sm">
                        <tr><th style="width:30%">Estado:</th><td><span class="badge bg-{{ $color }}">{{ $dn->sunat_status }}</span></td></tr>
                        <tr><th>Código:</th><td>{{ $dn->sunat_code ?? '—' }}</td></tr>
                        <tr><th>Descripción:</th><td>{{ $dn->sunat_description ?? '—' }}</td></tr>
                        <tr><th>Hash (DigestValue):</th><td><code class="small">{{ $dn->hash ?? '—' }}</code></td></tr>
                        <tr><th>Enviado:</th><td>{{ $dn->sent_at?->format('d/m/Y H:i:s') ?? '—' }}</td></tr>
                        <tr><th>XML:</th><td><code class="small">{{ $dn->xml_path ?? '—' }}</code></td></tr>
                        <tr><th>CDR:</th><td><code class="small">{{ $dn->cdr_path ?? '—' }}</code></td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
