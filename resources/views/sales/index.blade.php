@extends('layouts.app')

@section('content')
<div class="container-fluid">
    
    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="fw-bold text-dark mb-0"><i class="bi bi-cash-coin me-2"></i>Caja y Movimientos</h2>
                <p class="text-muted mb-0">Control de Ingresos y Egresos</p>
            </div>
        </div>

        <div class="bg-white p-2 rounded shadow-sm border mb-3">
            <form action="{{ route('sales.index') }}" method="GET" class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-bold mb-1">Desde</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-bold mb-1">Hasta</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}">
                </div>
                <div class="col-12 col-md-auto">
                    <button type="submit" class="btn btn-primary btn-sm px-3 fw-bold w-100"><i class="bi bi-search me-1"></i> Buscar</button>
                </div>
                <div class="col-6 col-md-auto">
                    <a href="{{ route('sales.daily.report', ['start_date' => $startDate, 'end_date' => $endDate]) }}" target="_blank" class="btn btn-dark fw-bold d-flex align-items-center justify-content-center">
                        <i class="bi bi-printer me-2"></i> Corte Z
                    </a>
                </div>
                <div class="col-6 col-md-auto">
                    <button class="btn btn-danger fw-bold d-flex align-items-center justify-content-center w-100" data-bs-toggle="modal" data-bs-target="#expenseModal">
                        <i class="bi bi-dash-circle me-2"></i> Salida
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-primary text-white h-100">
                <div class="card-body">
                    <small class="opacity-75 text-uppercase fw-bold">Venta Total</small>
                    <h3 class="fw-bold mb-0 mt-1">{{ number_format($totalSales, 2) }}</h3>
                    <small>{{ $orders->count() }} operaciones</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-success text-white h-100">
                <div class="card-body">
                    <small class="opacity-75 text-uppercase fw-bold">Entrada Efectivo</small>
                    <h3 class="fw-bold mb-0 mt-1">{{ number_format($totalCash, 2) }}</h3>
                    <small>Dinero Físico Ingresado</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-danger text-white h-100">
                <div class="card-body">
                    <small class="opacity-75 text-uppercase fw-bold">Gastos / Salidas</small>
                    <h3 class="fw-bold mb-0 mt-1">{{ number_format($totalExpenses, 2) }}</h3>
                    <small>{{ $expenses->count() }} movimientos</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100 {{ $balance >= 0 ? 'bg-dark text-white' : 'bg-warning text-dark' }}">
                <div class="card-body">
                    <small class="opacity-75 text-uppercase fw-bold">Dinero en Caja (Balance)</small>
                    <h3 class="fw-bold mb-0 mt-1">{{ number_format($balance, 2) }}</h3>
                    <small>Efectivo - Gastos</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white p-0 border-bottom-0">
            <ul class="nav nav-tabs ps-3 pt-3" id="salesTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active fw-bold" id="sales-tab" data-bs-toggle="tab" data-bs-target="#sales" type="button">
                        <i class="bi bi-receipt me-2"></i> Historial de Ventas
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-bold text-danger" id="expenses-tab" data-bs-toggle="tab" data-bs-target="#expenses" type="button">
                        <i class="bi bi-journal-minus me-2"></i> Historial de Gastos
                    </button>
                </li>
            </ul>
        </div>
        
        <div class="card-body p-0">
            <div class="tab-content" id="salesTabsContent">
                
                <div class="tab-pane fade show active" id="sales" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Hora</th>
                                    <th>Folio</th>
                                    <th>Cliente</th>
                                    <th>Mesa</th>
                                    <th>Método</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-center">Comprobante</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($orders as $order)
                                <tr>
                                    <td class="ps-4 text-muted">{{ $order->created_at->format('H:i') }}</td>
                                    <td class="fw-bold">#{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</td>
                                    <td>{{ $order->client_name }} <br><small class="text-muted">{{ $order->document_type }}</small></td>
                                    <td><span class="badge bg-light text-dark border">{{ $order->table->name ?? 'Barra' }}</span></td>
                                    <td>
                                        <span class="badge {{ $order->payment_method == 'cash' ? 'bg-success text-success' : 'bg-primary text-primary' }} bg-opacity-10 border {{ $order->payment_method == 'cash' ? 'border-success' : 'border-primary' }}">
                                            {{ $order->payment_method == 'cash' ? 'Efectivo' : 'Tarjeta' }}
                                        </span>
                                    </td>
                                    <td class="text-end fw-bold">{{ number_format($order->total, 2) }}</td>
                                    <td class="text-center">
                                        @if($order->isElectronic())
                                            <div class="btn-group btn-group-sm" role="group" aria-label="Acciones del comprobante electrónico">
                                                <a href="{{ route('billing.show', $order) }}" class="btn btn-outline-secondary" title="Ver {{ $order->document_type }}">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="{{ route('billing.pdf', $order) }}" target="_blank" rel="noopener" class="btn btn-outline-dark" title="Abrir URL PDF de {{ $order->document_type }}">
                                                    <i class="bi bi-file-earmark-pdf"></i>
                                                </a>
                                            </div>
                                        @else
                                            <a href="{{ route('sales.ticket', $order) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-dark" title="Imprimir ticket">
                                                <i class="bi bi-printer"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="7" class="text-center py-5 text-muted">No hay ventas en este rango.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade" id="expenses" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Hora</th>
                                    <th>Descripción / Motivo</th>
                                    <th>Registrado Por</th>
                                    <th class="text-end text-danger">Monto</th>
                                    <th class="text-end pe-4">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($expenses as $expense)
                                <tr>
                                    <td class="ps-4 text-muted">{{ $expense->created_at->format('d/m H:i') }}</td>
                                    <td class="fw-bold">{{ $expense->description }}</td>
                                    <td><small class="text-muted"><i class="bi bi-person"></i> {{ $expense->user->name }}</small></td>
                                    <td class="text-end fw-bold text-danger">-{{ number_format($expense->amount, 2) }}</td>
                                    <td class="text-end pe-4">
                                        <form action="{{ route('expenses.destroy', $expense->id) }}" method="POST" onsubmit="return confirm('¿Eliminar este gasto?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm text-danger p-0"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="5" class="text-center py-5 text-muted">No hay gastos registrados.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="expenseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered expense-modal-dialog">
        <form action="{{ route('expenses.store') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold">Registrar Salida de Dinero</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <small>Esta acción restará dinero del efectivo en caja.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Descripción del Gasto</label>
                    <input type="text" name="description" class="form-control" placeholder="Ej: Compra de hielo, Pago proveedor..." required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Monto a Retirar</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" step="0.01" name="amount" class="form-control fs-4 fw-bold text-danger" placeholder="0.00" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-danger fw-bold">Registrar Salida</button>
            </div>
        </form>
    </div>
</div>

<style>
    /* Fallback local: mantiene el modal funcional si el CDN de Bootstrap no carga. */
    #expenseModal .expense-modal-dialog {
        position: relative;
        width: auto;
        max-width: 500px;
        margin: 1.75rem auto;
        pointer-events: none;
    }

    #expenseModal .modal-content {
        position: relative;
        display: flex;
        flex-direction: column;
        width: 100%;
        pointer-events: auto;
        background-color: #fff;
        background-clip: padding-box;
        border: 1px solid rgba(0, 0, 0, .2);
        border-radius: .5rem;
        outline: 0;
        overflow: hidden;
    }

    #expenseModal .modal-header,
    #expenseModal .modal-body,
    #expenseModal .modal-footer {
        position: relative;
        display: block;
        width: 100%;
    }

    #expenseModal .modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem;
    }

    #expenseModal .modal-body { padding: 1rem; }
    #expenseModal .modal-footer {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: .5rem;
        padding: .75rem 1rem;
    }

    @media (max-width: 575.98px) {
        #expenseModal .expense-modal-dialog { max-width: calc(100% - 1rem); margin: .5rem auto; }
    }
</style>
@endsection