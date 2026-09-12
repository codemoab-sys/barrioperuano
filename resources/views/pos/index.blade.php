@extends('layouts.app')

@section('content')
<div class="container-fluid">
    @if(session('success'))
        @php $printOrderId = session('last_order_id'); @endphp
        <div class="alert alert-success border-0 shadow-sm rounded-3 d-flex align-items-center justify-content-between mb-3">
            <div class="d-flex align-items-center">
                <i class="bi bi-check-circle-fill fs-4 me-2"></i>
                <span>{{ session('success') }}</span>
            </div>
            <div class="d-flex gap-2">
                @if($printOrderId)
                    @php
                        $printOrder = \App\Models\Order::find($printOrderId);
                        $printRoute = $printOrder && in_array($printOrder->document_type, ['Boleta','Factura'])
                            ? route('billing.pdf.ticket', $printOrderId)
                            : route('sales.ticket', $printOrderId);
                    @endphp
                    <a href="{{ $printRoute }}" target="_blank" class="btn btn-sm btn-dark">
                        <i class="bi bi-printer me-1"></i> Imprimir
                    </a>
                @endif
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger border-0 shadow-sm rounded-3 d-flex align-items-center justify-content-between mb-3">
            <div class="d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill fs-4 me-2"></i>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-0">Punto de Venta</h2>
            <p class="text-muted mb-0">Selecciona una mesa para comenzar</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <button type="button" class="btn btn-outline-dark btn-sm fw-bold" data-bs-toggle="offcanvas" data-bs-target="#lastSalesOffcanvas">
                <i class="bi bi-clock-history me-1"></i> Últimas Ventas
            </button>
            <div class="d-flex align-items-center">
                <span class="d-inline-block bg-success rounded-circle p-1 me-1" style="width: 10px; height: 10px;"></span>
                <small class="fw-bold text-muted small">Disponible</small>
            </div>
            <div class="d-flex align-items-center">
                <span class="d-inline-block bg-danger rounded-circle p-1 me-1" style="width: 10px; height: 10px;"></span>
                <small class="fw-bold text-muted small">Ocupada</small>
            </div>
            <div class="d-flex align-items-center">
                <span class="d-inline-block bg-warning rounded-circle p-1 me-1" style="width: 10px; height: 10px;"></span>
                <small class="fw-bold text-muted small">Reservada</small>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs nav-pills mb-3 flex-nowrap overflow-auto pb-1" id="posTabs" role="tablist" style="-webkit-overflow-scrolling: touch;">
        @foreach($areas as $index => $area)
            <li class="nav-item me-2" role="presentation">
                <button class="nav-link {{ $index == 0 ? 'active' : '' }} fw-bold px-4 border" 
                        id="tab-{{ $area->id }}" 
                        data-bs-toggle="tab" 
                        data-bs-target="#area-{{ $area->id }}" 
                        type="button" role="tab">
                    {{ $area->name }}
                </button>
            </li>
        @endforeach
    </ul>

    <div class="tab-content">
        @foreach($areas as $index => $area)
            <div class="tab-pane fade {{ $index == 0 ? 'show active' : '' }}" id="area-{{ $area->id }}" role="tabpanel">
                
                <div class="position-relative border rounded-3 shadow-sm bg-light" 
                     style="height: 650px; overflow: auto; background-image: radial-gradient(#cbd5e1 1px, transparent 1px); background-size: 20px 20px;">
                    
                    @foreach($area->tables as $table)
                        @php
                            $order = $table->orders->first(); // Orden activa
                            $reservations = $table->reservations; // TODAS las reservas confirmadas de hoy
                            
                            $isBusy = $order ? true : false;
                            $hasReservations = $reservations->count() > 0;
                            
                            // Estilos dinámicos
                            $cardClass = $isBusy 
                                ? 'bg-white border-danger border-2 text-danger shadow-sm' 
                                : ($hasReservations ? 'bg-white border-warning border-2 text-dark shadow-sm' : 'bg-white border-success border-2 text-success shadow-sm');
                                
                            $icon = $isBusy ? 'bi-display-fill' : 'bi-display';
                        @endphp

                        <a href="{{ route('pos.order', $table->id) }}" class="text-decoration-none text-dark">
                            <div class="pos-table-card position-absolute d-flex flex-column align-items-center justify-content-between p-2 rounded-3 {{ $cardClass }}"
                                 style="width: 110px; height: 110px; 
                                        left: {{ $table->x_pos }}px; 
                                        top: {{ $table->y_pos }}px; 
                                        transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);">
                                
                                <div class="w-100 text-center border-bottom pb-1 mb-1">
                                    <span class="fw-bold small text-uppercase" style="font-size: 0.75rem;">{{ $table->name }}</span>
                                </div>

                                <div class="flex-grow-1 d-flex align-items-center justify-content-center position-relative w-100">
                                    <i class="bi {{ $icon }} fs-1 {{ $isBusy ? 'text-danger' : 'text-secondary opacity-50' }}"></i>
                                    
                                    @if($hasReservations && !$isBusy)
                                        <div class="position-absolute top-50 start-50 translate-middle badge bg-warning text-dark border border-dark shadow-sm" 
                                             style="font-size: 0.6rem; width: 100%; white-space: normal; line-height: 1.1; z-index: 2; max-height: 60px; overflow-y: auto;">
                                            
                                            @foreach($reservations as $res)
                                                <div class="{{ !$loop->last ? 'border-bottom border-dark pb-1 mb-1' : '' }}">
                                                    <i class="bi bi-clock-fill"></i> <strong>{{ $res->reservation_time->format('H:i') }}</strong>
                                                    <br>{{ Str::limit($res->client_name, 9) }}
                                                </div>
                                            @endforeach

                                        </div>
                                    @endif
                                </div>

                                <div class="w-100 text-center mt-1">
                                    @if($isBusy)
                                        <div class="badge bg-danger w-100 py-1 shadow-sm">
                                            <small style="font-size: 0.65rem;">CONSUMO</small><br>
                                            <span class="fs-6 fw-bold">{{ $currency ?? 'S/' }}{{ number_format($order->total, 2) }}</span>
                                        </div>
                                    @else
                                        @if($hasReservations)
                                            <div class="badge bg-warning text-dark w-100 py-2 shadow-sm border border-warning">
                                                {{ $reservations->count() }} RESERVA(S)
                                            </div>
                                        @else
                                            <div class="badge bg-success w-100 py-2 shadow-sm">
                                                LIBRE
                                            </div>
                                        @endif
                                    @endif
                                </div>

                            </div>
                        </a>
                    @endforeach

                </div>
            </div>
        @endforeach
    </div>
</div>

{{-- Últimas Ventas: panel deslizante --}}
<div class="offcanvas offcanvas-end" tabindex="-1" id="lastSalesOffcanvas" aria-labelledby="lastSalesLabel" style="width: min(380px, 100vw);">
    <div class="offcanvas-header border-bottom">
        <h6 class="offcanvas-title fw-bold" id="lastSalesLabel">
            <i class="bi bi-clock-history me-1"></i> Últimas Ventas
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <div class="offcanvas-body p-2">
        @php
            $docBadges = ['Ticket' => 'secondary', 'Boleta' => 'info', 'Factura' => 'primary'];
        @endphp
        @forelse($lastSales as $sale)
            @php
                $clientName = $sale->client ? $sale->client->name : ($sale->client_name ?: 'Público');
                $badge = $docBadges[$sale->document_type] ?? 'secondary';
            @endphp
            <div class="card border-0 shadow-sm mb-2">
                <div class="card-body py-2 px-3 d-flex align-items-center justify-content-between gap-2">
                    <div class="min-w-0">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-{{ $badge }}">{{ $sale->document_type }}</span>
                            <span class="text-muted small fw-bold">#{{ str_pad($sale->id, 6, '0', STR_PAD_LEFT) }}</span>
                        </div>
                        <div class="fw-bold text-truncate" style="max-width: 180px;">{{ $clientName }}</div>
                        <div class="small text-muted">{{ ($sale->issued_at ?? $sale->created_at)->format('d/m H:i') }}</div>
                    </div>
                    <div class="d-flex flex-column align-items-end gap-1">
                        <span class="fw-bold fs-6 text-success">{{ $currency ?? 'S/' }} {{ number_format($sale->total, 2) }}</span>
                        @if(in_array($sale->document_type, ['Boleta', 'Factura']))
                            <a href="{{ route('billing.pdf', $sale) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-dark" title="Imprimir">
                                <i class="bi bi-printer"></i> Imprimir
                            </a>
                        @else
                            <a href="{{ route('sales.ticket', $sale->id) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-dark" title="Imprimir">
                                <i class="bi bi-printer"></i> Imprimir
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center text-muted py-5">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                Aún no hay ventas registradas.
            </div>
        @endforelse
    </div>
</div>

<style>
    .pos-table-card:hover {
        transform: scale(1.1) translateY(-5px); 
        z-index: 100 !important;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1) !important;
        cursor: pointer;
    }
    /* Scrollbar invisible para el badge de reservas */
    .badge::-webkit-scrollbar { width: 0px; background: transparent; }
    
    .nav-pills .nav-link.active {
        background-color: #0d6efd; color: white;
        box-shadow: 0 4px 6px rgba(13, 110, 253, 0.3);
    }
    .nav-pills .nav-link { color: #495057; background-color: #fff; }

    /* MODO OSCURO: solo aplica con data-theme="dark", no altera el claro */
    [data-theme="dark"] .nav-pills .nav-link { color: var(--text-muted); background-color: #242539; }
    [data-theme="dark"] .nav-pills .nav-link.active { background-color: #0d6efd; color: #fff; box-shadow: 0 4px 6px rgba(13,110,253,0.3); }

    @media (max-width: 575.98px) {
        .nav-tabs .nav-link { padding: 8px 14px; font-size: 0.85rem; white-space: nowrap; }
    }
</style>
@endsection