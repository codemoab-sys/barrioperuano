@extends('layouts.app')

@section('content')
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="{{ route('billing.index') }}" class="text-decoration-none small">
                <i class="bi bi-arrow-left"></i> Comprobantes
            </a>
            <h2 class="fw-bold mb-0">
                <i class="bi bi-arrow-counterclockwise me-2"></i>Notas de Crédito
            </h2>
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label small mb-1">Estado SUNAT</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        @foreach(['PENDING','ACCEPTED','OBSERVED','REJECTED','ERROR'] as $st)
                            <option value="{{ $st }}" {{ request('status') === $st ? 'selected' : '' }}>{{ $st }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small mb-1">Buscar</label>
                    <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm"
                           placeholder="Serie o número">
                </div>
                <div class="col-12 col-md-auto">
                    <button class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel"></i></button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>N.C.</th>
                        <th>Fecha</th>
                        <th>Comprobante afectado</th>
                        <th>Motivo</th>
                        <th class="text-end">Total</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($notes as $cn)
                        <tr>
                            <td class="fw-bold">{{ $cn->full_number }}</td>
                            <td class="small">{{ $cn->created_at->format('d/m/Y H:i') }}</td>
                            <td class="small">
                                <a href="{{ route('billing.show', $cn->order_id) }}">
                                    {{ $cn->order?->full_number ?? '#' . $cn->order_id }}
                                </a>
                            </td>
                            <td class="small">
                                <code>{{ $cn->reason_code }}</code>
                                {{ $cn->reason_description }}
                            </td>
                            <td class="text-end fw-bold">S/ {{ number_format($cn->total, 2) }}</td>
                            <td>
                                @php
                                    $color = $cn->sunat_status === 'ACCEPTED' ? 'success' :
                                             ($cn->sunat_status === 'OBSERVED' ? 'warning' :
                                             ($cn->sunat_status === 'PENDING' ? 'secondary' : 'danger'));
                                @endphp
                                <span class="badge bg-{{ $color }}">{{ $cn->sunat_status }}</span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('credit_notes.show', $cn) }}" class="btn btn-outline-secondary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    @if($cn->pdf_path)
                                        <a href="{{ route('credit_notes.pdf', $cn) }}" class="btn btn-outline-danger"><i class="bi bi-file-earmark-pdf"></i></a>
                                    @endif
                                    @if($cn->xml_path)
                                        <a href="{{ route('credit_notes.xml', $cn) }}" class="btn btn-outline-info">
                                            <i class="bi bi-filetype-xml"></i>
                                        </a>
                                    @endif
                                    @if($cn->cdr_path)
                                        <a href="{{ route('credit_notes.cdr', $cn) }}" class="btn btn-outline-success">
                                            <i class="bi bi-archive"></i>
                                        </a>
                                    @endif
                                    @if(in_array($cn->sunat_status, ['PENDING','ERROR','REJECTED']))
                                        <form method="POST" action="{{ route('credit_notes.retry', $cn) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-outline-warning" onclick="return confirm('¿Reintentar envío?')">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Sin notas de crédito.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">{{ $notes->links() }}</div>
    </div>
</div>
@endsection
