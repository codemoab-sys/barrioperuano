@extends('layouts.app')

@php
    // Pre-compute safe arrays for JS (avoids Blade parse issues with array_fill in @push)
    $jsMonthlySales        = $monthlySales        ?? array_fill(0, 12, 0);
    $jsMonthlyOrders       = $monthlyOrders       ?? array_fill(0, 12, 0);
    $jsMonthlyReservations = $monthlyReservations ?? array_fill(0, 12, 0);
    $jsRadarLabels         = $radarLabels         ?? ['Entradas', 'Platos', 'Bebidas', 'Postres', 'Especiales'];
    $jsRadarData           = $radarData           ?? [0, 0, 0, 0, 0];
    $jsGoalPercent         = $goalPercent         ?? 60;
@endphp

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const brand = window.BRAND_COLORS || { primary: '#ff8c00', primarySoft: '#fff4e6' };
    const primaryColor = brand.primary;
    /* ====================================================
       CHART 1 — Actividad Anual (Line Chart)
    ==================================================== */
    const lineCtx = document.getElementById('lineChart').getContext('2d');

    const monthlySales = @json($jsMonthlySales);

    new Chart(lineCtx, {
        type: 'line',
        data: {
            labels: ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'],
            datasets: [
                {
                    label: 'Ventas',
                    data: monthlySales,
                    borderColor: primaryColor,
                    backgroundColor: 'rgba(255,140,0,0.08)',
                    borderWidth: 2.5,
                    pointBackgroundColor: primaryColor,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    tension: 0.45,
                    fill: true,
                },
                {
                    label: 'Pedidos',
                    data: @json($jsMonthlyOrders),
                    borderColor: '#9b5de5',
                    backgroundColor: 'rgba(155,93,229,0.06)',
                    borderWidth: 2,
                    pointBackgroundColor: '#9b5de5',
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    tension: 0.45,
                    fill: true,
                },
                {
                    label: 'Reservas',
                    data: @json($jsMonthlyReservations),
                    borderColor: '#22c55e',
                    backgroundColor: 'rgba(34,197,94,0.05)',
                    borderWidth: 2,
                    pointBackgroundColor: '#22c55e',
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    tension: 0.45,
                    fill: true,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: { usePointStyle: true, pointStyleWidth: 8, padding: 18, font: { family:'Inter', size:11 } }
                },
                tooltip: {
                    backgroundColor: '#2d1b5e',
                    titleFont: { family:'Inter', weight:'700' },
                    bodyFont:  { family:'Inter' },
                    padding: 12,
                    cornerRadius: 10,
                }
            },
            scales: {
                x: { grid: { display: false }, ticks: { font: { family:'Inter', size:11 } } },
                y: {
                    grid: { color: 'rgba(0,0,0,0.04)' },
                    ticks: { font: { family:'Inter', size:11 }, callback: v => 'S/'+v },
                    beginAtZero: true
                }
            }
        }
    });

    /* ====================================================
       CHART 2 — Donut (Ingreso Actual)
    ==================================================== */
    const donutCtx = document.getElementById('donutChart').getContext('2d');

    const goalPct = {{ $jsGoalPercent }};

    new Chart(donutCtx, {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [goalPct, 100 - goalPct],
                backgroundColor: ['#2d1b5e', '#ede9f8'],
                borderWidth: 0,
                hoverOffset: 4,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            plugins: {
                legend: { display: false },
                tooltip: { enabled: false },
            }
        }
    });

});
</script>
@endpush

@section('content')
<div class="container-fluid p-0">

    {{-- ============================================================
         FILA 1: 4 Paneles KPI + Ventas Mes + Meta
    ============================================================ --}}
    <div class="row g-3 mb-3">

        {{-- KPI 1: Ventas de Hoy --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="kpi-panel kpi-sales">
                <div class="kpi-icon-wrap">
                    <i class="bi bi-cash-coin"></i>
                </div>
                <div class="kpi-content">
                    <span class="kpi-label">Ventas de Hoy</span>
                    <div class="kpi-value">{{ $currency ?? 'S/' }} {{ number_format($totalSalesToday ?? 0, 2, '.', ',') }}</div>
                    <div class="kpi-sub">
                        <i class="bi bi-receipt me-1"></i>
                        {{ $ordersCountToday ?? 0 }} órdenes completadas
                    </div>
                </div>
                <div class="kpi-badge">HOY</div>
            </div>
        </div>

        {{-- KPI 2: Mesas en Servicio --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="kpi-panel kpi-tables">
                <div class="kpi-icon-wrap">
                    <i class="bi bi-grid-3x3-gap-fill"></i>
                </div>
                <div class="kpi-content">
                    <span class="kpi-label">Mesas en Servicio</span>
                    <div class="kpi-value">{{ $activeTables ?? 0 }}</div>
                    <div class="kpi-sub">
                        <i class="bi bi-clock me-1"></i>
                        Ocupadas ahora mismo
                    </div>
                </div>
                <div class="kpi-badge">LIVE</div>
            </div>
        </div>

        {{-- KPI 3: Ventas del Mes --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="kpi-panel kpi-month">
                <div class="kpi-icon-wrap">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <div class="kpi-content">
                    <span class="kpi-label">Ventas del Mes</span>
                    <div class="kpi-value">{{ $currency ?? 'S/' }} {{ number_format($totalSalesMonth ?? 0, 0, '.', ',') }}</div>
                    <div class="kpi-sub">
                        <i class="bi bi-bullseye me-1"></i>
                        Meta: {{ $currency ?? 'S/' }} {{ number_format($monthlyGoal ?? 5000, 0, '.', ',') }}
                    </div>
                </div>
                <div class="kpi-badge">MES</div>
            </div>
        </div>

        {{-- KPI 4: Stock Bajo --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="kpi-panel kpi-stock {{ ($lowStockProducts ?? 0) > 0 ? 'kpi-alert' : 'kpi-ok' }}">
                <div class="kpi-icon-wrap">
                    <i class="bi bi-{{ ($lowStockProducts ?? 0) > 0 ? 'exclamation-triangle-fill' : 'box-seam-fill' }}"></i>
                </div>
                <div class="kpi-content">
                    <span class="kpi-label">Alertas de Stock</span>
                    <div class="kpi-value">{{ $lowStockProducts ?? 0 }}</div>
                    <div class="kpi-sub">
                        @if(($lowStockProducts ?? 0) > 0)
                            <i class="bi bi-exclamation-circle me-1"></i> Productos por reponer
                        @else
                            <i class="bi bi-check-circle me-1"></i> Inventario en buen estado
                        @endif
                    </div>
                </div>
                <a href="{{ route('inventory.logs') }}" class="kpi-badge kpi-badge-link">VER</a>
            </div>
        </div>

    </div>

    {{-- ============================================================
         FILA 1B: Meta mensual + Progreso
    ============================================================ --}}
    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="goal-bar-panel">
                <div class="goal-bar-left">
                    <i class="bi bi-trophy-fill"></i>
                    <div>
                        <span class="goal-bar-title">Progreso de Meta Mensual</span>
                        <span class="goal-bar-sub">Logrado: <strong>{{ $currency ?? 'S/' }} {{ number_format($totalSalesMonth ?? 0, 2, '.', ',') }}</strong> de <strong>{{ $currency ?? 'S/' }} {{ number_format($monthlyGoal ?? 5000, 2, '.', ',') }}</strong></span>
                    </div>
                </div>
                <div class="goal-bar-track-wrap">
                    <div class="goal-bar-track">
                        <div class="goal-bar-fill" style="width: {{ $goalPercent ?? 0 }}%;">
                            <span class="goal-bar-pct">{{ $goalPercent ?? 0 }}%</span>
                        </div>
                    </div>
                </div>
                <a href="{{ route('reports.index') }}" class="goal-bar-btn">
                    <i class="bi bi-bar-chart-line-fill me-1"></i> Ver Reportes
                </a>
            </div>
        </div>
    </div>

    {{-- ============================================================
         FILA 2: Actividad Anual | Ingreso Actual
    ============================================================ --}}
    <div class="row g-3">

        {{-- Gráfico Actividad Anual --}}
        <div class="col-12 col-xl-8">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between py-2 px-3">
                    <span class="fw-bold" style="font-size:.85rem;">Actividad del Año</span>
                    <div style="background:var(--primary, #ff8c00);width:22px;height:22px;border-radius:6px;display:flex;align-items:center;justify-content:center;">
                        <span style="color:white;font-size:13px;line-height:1;">—</span>
                    </div>
                </div>
                <div class="card-body p-3" style="height:280px;">
                    <canvas id="lineChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Donut: Ingreso Actual --}}
        <div class="col-12 col-xl-4">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between py-2 px-3">
                    <span class="fw-bold" style="font-size:.85rem;">Ingreso Actual</span>
                    <div style="background:var(--primary, #ff8c00);width:22px;height:22px;border-radius:6px;display:flex;align-items:center;justify-content:center;">
                        <span style="color:white;font-size:13px;line-height:1;">—</span>
                    </div>
                </div>
                <div class="card-body d-flex flex-column align-items-center justify-content-center p-3 gap-3">
                    {{-- Donut --}}
                    <div style="position:relative;width:150px;height:150px;">
                        <canvas id="donutChart"></canvas>
                        <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;">
                            <div style="font-size:1.7rem;font-weight:800;color:#2d1b5e;line-height:1;">{{ $goalPercent ?? 60 }}%</div>
                            <div style="font-size:.65rem;color:#9090b0;font-weight:600;">de meta</div>
                        </div>
                    </div>

                    {{-- Stat --}}
                    <div class="text-center">
                        <div style="font-size:.72rem;color:#6c6c90;">Meta mensual: <strong style="color:#2d1b5e;">S/ {{ number_format($monthlyGoal ?? 5000, 0,'.',',') }}</strong></div>
                        <div style="font-size:.7rem;color:#9090b0;margin-top:2px;">Logrado: S/ {{ number_format($totalSalesMonth ?? 0, 0,'.',',') }}</div>
                    </div>

                    {{-- Buttons --}}
                    <div class="d-flex gap-2 w-100">
                        <a href="{{ route('reports.index') }}" class="btn btn-success flex-grow-1 py-2" style="font-size:.8rem;">
                            <i class="bi bi-eye me-1"></i>Ver más
                        </a>
                        <a href="{{ route('reports.index') }}" class="btn btn-danger flex-grow-1 py-2" style="font-size:.8rem;">
                            <i class="bi bi-download me-1"></i>Exportar
                        </a>
                    </div>

                    <p style="font-size:.68rem;color:#9090b0;text-align:center;margin:0;">
                        Progreso mensual basado en las ventas registradas en el sistema.
                    </p>
                </div>
            </div>
        </div>

    </div>

    {{-- ============================================================
         FILA 3: Estado de Salones + Más Vendidos
    ============================================================ --}}
    <div class="row g-3 mt-0">

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center py-3 px-4">
                    <h6 class="fw-bold mb-0">Estado de Salones</h6>
                    <div class="d-flex gap-2">
                        <span class="badge" style="background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;font-size:.72rem;">● Disponible</span>
                        <span class="badge" style="background:#fff1f2;color:#e11d48;border:1px solid #fecdd3;font-size:.72rem;">● Ocupada</span>
                    </div>
                </div>
                <div class="card-body px-4 pb-4 pt-2">
                    @if(isset($areas) && count($areas) > 0)
                        <ul class="nav nav-pills mb-4 gap-2" id="pills-tab" role="tablist">
                            @foreach($areas as $index => $area)
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $index == 0 ? 'active' : '' }} rounded-pill px-4 fw-bold border"
                                            id="pills-{{ $area->id }}-tab" data-bs-toggle="pill"
                                            data-bs-target="#pills-{{ $area->id }}" type="button">
                                        {{ $area->name }}
                                    </button>
                                </li>
                            @endforeach
                        </ul>

                        <div class="tab-content">
                            @foreach($areas as $index => $area)
                                <div class="tab-pane fade {{ $index == 0 ? 'show active' : '' }}" id="pills-{{ $area->id }}">
                                    <div class="row row-cols-2 row-cols-sm-3 row-cols-xl-5 g-3">
                                        @foreach($area->tables as $table)
                                            @php $isBusy = $table->orders->count() > 0; @endphp
                                            <div class="col">
                                                <a href="{{ route('pos.order', $table->id) }}" class="text-decoration-none">
                                                    <div class="card border-0 text-center py-3 table-hover-card"
                                                         style="background:{{ $isBusy ? '#fff1f2' : '#f8f5ff' }};">
                                                        <div class="card-body p-2">
                                                            <div class="mb-2">
                                                                <i class="bi {{ $isBusy ? 'bi-person-workspace' : 'bi-check-circle-fill' }} fs-1"
                                                                   style="color:{{ $isBusy ? '#e11d48' : '#22c55e' }};"></i>
                                                            </div>
                                                            <h6 class="fw-bold text-dark mb-1" style="font-size:.82rem;">{{ $table->name }}</h6>
                                                            @if($isBusy)
                                                                <span class="badge rounded-pill" style="background:#e11d48;font-size:.7rem;">
                                                                    S/{{ number_format($table->orders->first()->total, 2) }}
                                                                </span>
                                                            @else
                                                                <small class="text-muted" style="font-size:.7rem;">Libre</small>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </a>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-5 text-muted">No hay áreas configuradas.</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header py-3 px-4">
                    <h6 class="fw-bold mb-0">🏆 Más Vendidos</h6>
                </div>
                <div class="card-body px-0 py-2">
                    <div class="list-group list-group-flush">
                        @forelse($topProducts ?? [] as $product)
                            <div class="list-group-item border-0 d-flex align-items-center px-4 py-2 top-product-item">
                                <div class="me-3 position-relative flex-shrink-0">
                                    @if($product->image)
                                        <img src="{{ route('uploaded-assets.show', ['path' => $product->image]) }}" class="rounded-3 shadow-sm"
                                             width="48" height="48" style="object-fit:cover;">
                                    @else
                                        <div class="rounded-3 d-flex align-items-center justify-content-center"
                                             style="width:48px;height:48px;background:#f0eef8;color:#9b5de5;">
                                            <i class="bi bi-image fs-5"></i>
                                        </div>
                                    @endif
                                    <span class="position-absolute top-0 start-0 translate-middle badge rounded-pill border border-white"
                                          style="background:var(--primary);font-size:.6rem;">
                                        #{{ $loop->iteration }}
                                    </span>
                                </div>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="fw-bold text-dark text-truncate" style="font-size:.84rem;">{{ $product->name }}</div>
                                    <small class="text-muted">{{ $product->total_qty }} unidades</small>
                                </div>
                                <i class="bi bi-trophy-fill text-warning ms-2" style="opacity:.6;"></i>
                            </div>
                        @empty
                            <div class="text-center py-5 text-muted">Sin datos de ventas aún.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>

{{-- Dashboard-specific styles --}}
<style>
    /* ── KPI Panels ───────────────────────────────────────── */
    .kpi-panel {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 20px 22px;
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 2px 14px rgba(45,27,94,0.07);
        position: relative;
        overflow: hidden;
        transition: transform .22s, box-shadow .22s;
        border: 1px solid rgba(224,221,240,0.6);
        height: 100%;
    }
    .kpi-panel:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 28px rgba(45,27,94,0.13);
    }
    .kpi-panel::before {
        content: '';
        position: absolute;
        top: 0; left: 0;
        width: 5px;
        height: 100%;
        border-radius: 16px 0 0 16px;
    }
        .kpi-sales::before  { background: linear-gradient(180deg, var(--primary, #ff8c00), #ffc36b); }
    .kpi-tables::before { background: linear-gradient(180deg,#9b5de5,#c084fc); }
    .kpi-month::before  { background: linear-gradient(180deg,#22c55e,#86efac); }
    .kpi-stock.kpi-alert::before { background: linear-gradient(180deg,#ef4444,#fca5a5); }
    .kpi-stock.kpi-ok::before    { background: linear-gradient(180deg,#06b6d4,#67e8f9); }

    .kpi-icon-wrap {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
    }
        .kpi-sales  .kpi-icon-wrap { background: var(--primary-soft, #fff4e6); color: var(--primary, #ff8c00); }
    .kpi-tables .kpi-icon-wrap { background: #f3eeff; color: #9b5de5; }
    .kpi-month  .kpi-icon-wrap { background: #f0fdf4; color: #22c55e; }
    .kpi-stock.kpi-alert .kpi-icon-wrap { background: #fff1f2; color: #ef4444; }
    .kpi-stock.kpi-ok    .kpi-icon-wrap { background: #ecfeff; color: #06b6d4; }

    .kpi-content { flex: 1; min-width: 0; }
    .kpi-label {
        display: block;
        font-size: .72rem;
        font-weight: 600;
        letter-spacing: .05em;
        text-transform: uppercase;
        color: #9090b0;
        margin-bottom: 4px;
    }
    .kpi-value {
        font-size: 1.55rem;
        font-weight: 800;
        color: #1e1b3a;
        line-height: 1.1;
        margin-bottom: 5px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .kpi-sub {
        font-size: .72rem;
        color: #9090b0;
        display: flex;
        align-items: center;
    }
    .kpi-badge {
        position: absolute;
        top: 14px;
        right: 14px;
        font-size: .6rem;
        font-weight: 700;
        letter-spacing: .08em;
        padding: 3px 8px;
        border-radius: 20px;
        background: rgba(45,27,94,0.06);
        color: #6c6c90;
        text-decoration: none;
    }
    .kpi-badge-link:hover {
        background: var(--primary, #ff8c00);
        color: #fff;
    }

    /* ── Goal Progress Bar ───────────────────────────────── */
    .goal-bar-panel {
        background: #fff;
        border-radius: 16px;
        border: 1px solid rgba(224,221,240,0.6);
        box-shadow: 0 2px 14px rgba(45,27,94,0.06);
        padding: 16px 24px;
        display: flex;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
    }
    .goal-bar-left {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-shrink: 0;
    }
    .goal-bar-left > i {
        font-size: 1.4rem;
        color: var(--primary, #ff8c00);
    }
    .goal-bar-title {
        display: block;
        font-size: .82rem;
        font-weight: 700;
        color: #1e1b3a;
    }
    .goal-bar-sub {
        display: block;
        font-size: .72rem;
        color: #9090b0;
        margin-top: 2px;
    }
    .goal-bar-track-wrap { flex: 1; min-width: 160px; }
    .goal-bar-track {
        width: 100%;
        height: 14px;
        background: #f0eef8;
        border-radius: 20px;
        overflow: hidden;
    }
    .goal-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--primary, #ff8c00), #ffc36b);
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        padding-right: 8px;
        transition: width .6s ease;
        min-width: 36px;
    }
    .goal-bar-pct {
        font-size: .62rem;
        font-weight: 700;
        color: #fff;
        white-space: nowrap;
    }
    .goal-bar-btn {
        flex-shrink: 0;
        background: linear-gradient(135deg,#2d1b5e,#9b5de5);
        color: #fff;
        border-radius: 10px;
        padding: 8px 18px;
        font-size: .8rem;
        font-weight: 600;
        text-decoration: none;
        transition: opacity .2s, transform .2s;
        white-space: nowrap;
    }
    .goal-bar-btn:hover { opacity: .9; transform: translateY(-1px); color:#fff; }

    /* ── Table cards ───────────────────────────────────────── */
    .table-hover-card {
        border-radius: 14px !important;
        transition: transform .2s, box-shadow .2s;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04) !important;
    }
    .table-hover-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 22px rgba(45,27,94,0.12) !important;
    }

    /* Nav pills (override for purple/orange theme) */
    .nav-pills .nav-link { color: #6c6c90; background: white; border-color: #e0ddf0; font-size:.84rem; }
    .nav-pills .nav-link.active { background: var(--primary, #ff8c00); color: white; border-color: var(--primary, #ff8c00); box-shadow: 0 4px 14px rgba(255,140,0,0.30); }

    /* Top product hover */
    .top-product-item { transition: background .15s; border-radius: 10px; }
    .top-product-item:hover { background: #f8f5ff; }

    /* ══ MODO OSCURO (solo aplica con data-theme="dark"; no altera el claro) ══ */
    [data-theme="dark"] .kpi-panel,
    [data-theme="dark"] .goal-bar-panel,
    [data-theme="dark"] .table-hover-card {
        background: var(--card-bg);
        border-color: #2a2b40;
        box-shadow: var(--shadow-card);
    }
    [data-theme="dark"] .kpi-panel:hover,
    [data-theme="dark"] .table-hover-card:hover { box-shadow: 0 10px 28px rgba(0,0,0,0.45); }
    [data-theme="dark"] .kpi-value,
    [data-theme="dark"] .goal-bar-title { color: var(--text-main); }
    [data-theme="dark"] .kpi-label,
    [data-theme="dark"] .kpi-sub,
    [data-theme="dark"] .goal-bar-sub { color: var(--text-muted); }
    [data-theme="dark"] .kpi-badge { background: rgba(255,255,255,0.07); color: var(--text-muted); }
    [data-theme="dark"] .kpi-badge-link:hover { color: #fff; }
    [data-theme="dark"] .nav-pills .nav-link { background: #242539; color: var(--text-muted); border-color: #2a2b40; }
    [data-theme="dark"] .top-product-item:hover { background: #242539; }
</style>

@includeIf('products.create_modal')
@endsection