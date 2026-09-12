@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h4 mb-0 fw-bold text-gray-800"><i class="bi bi-bicycle me-2 text-primary"></i>Delivery (Pedidos a Domicilio)</h2>
            <p class="text-muted small mb-0 mt-1">Gestión de pedidos para enviar hoy.</p>
        </div>
        <div>
            <a href="{{ route('delivery.drivers') }}" class="btn btn-outline-secondary me-2">
                <i class="bi bi-person-vcard me-1"></i> Repartidores
            </a>
            <a href="{{ route('delivery.create') }}" class="btn btn-primary shadow-sm fw-bold">
                <i class="bi bi-plus-lg me-1"></i> Nuevo Pedido
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- KANBAN BOARD --}}
    <div class="row g-3">
        {{-- Pendiente --}}
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 bg-light">
                <div class="card-header border-0 bg-transparent pt-3 pb-2">
                    <h6 class="fw-bold mb-0" style="color: {{ $statuses['pending'] ?? '#f59e0b' }}">
                        <i class="bi bi-hourglass-split me-1"></i> Pendiente
                        <span class="badge bg-warning text-dark rounded-pill float-end">{{ $counts['pending'] }}</span>
                    </h6>
                </div>
                <div class="card-body p-2" id="col-pending">
                    @foreach($deliveries->get('pending', []) as $delivery)
                        @include('delivery.partials.card', ['delivery' => $delivery])
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Preparando --}}
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 bg-light">
                <div class="card-header border-0 bg-transparent pt-3 pb-2">
                    <h6 class="fw-bold mb-0" style="color: {{ $statuses['preparing'] ?? '#3b82f6' }}">
                        <i class="bi bi-fire me-1"></i> Preparando
                        <span class="badge bg-primary rounded-pill float-end">{{ $counts['preparing'] }}</span>
                    </h6>
                </div>
                <div class="card-body p-2" id="col-preparing">
                    @foreach($deliveries->get('preparing', []) as $delivery)
                        @include('delivery.partials.card', ['delivery' => $delivery])
                    @endforeach
                </div>
            </div>
        </div>

        {{-- En Camino --}}
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 bg-light">
                <div class="card-header border-0 bg-transparent pt-3 pb-2">
                    <h6 class="fw-bold mb-0" style="color: {{ $statuses['on_way'] ?? '#f97316' }}">
                        <i class="bi bi-bicycle me-1"></i> En Camino
                        <span class="badge rounded-pill float-end" style="background-color: #f97316;">{{ $counts['on_way'] }}</span>
                    </h6>
                </div>
                <div class="card-body p-2" id="col-on_way">
                    @foreach($deliveries->get('on_way', []) as $delivery)
                        @include('delivery.partials.card', ['delivery' => $delivery])
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Entregado --}}
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 bg-light">
                <div class="card-header border-0 bg-transparent pt-3 pb-2">
                    <h6 class="fw-bold mb-0" style="color: {{ $statuses['delivered'] ?? '#22c55e' }}">
                        <i class="bi bi-check2-all me-1"></i> Entregados Hoy
                        <span class="badge bg-success rounded-pill float-end">{{ $counts['delivered'] }}</span>
                    </h6>
                </div>
                <div class="card-body p-2" id="col-delivered">
                    @foreach($deliveries->get('delivered', []) as $delivery)
                        @include('delivery.partials.card', ['delivery' => $delivery])
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
