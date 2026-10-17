@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-0"><i class="bi bi-box-seam me-2"></i>Insumos de Cocina</h2>
            <p class="text-muted mb-0">Existencias del inventario de cocina</p>
        </div>
        <div>
            @if(auth()->user()->role === 'admin')
                <a href="{{ route('kitchen_inventory.movements.index') }}" class="btn btn-outline-secondary fw-bold shadow-sm me-2">
                    <i class="bi bi-clock-history me-1"></i> Kardex
                </a>
                <a href="{{ route('kitchen_inventory.categories.index') }}" class="btn btn-outline-secondary fw-bold shadow-sm me-2">
                    <i class="bi bi-tags me-1"></i> Categorías
                </a>
            @endif
            <a href="{{ route('kitchen_inventory.recipes.index') }}" class="btn btn-outline-secondary fw-bold shadow-sm me-2">
                <i class="bi bi-journal-bookmark me-1"></i> Recetas
            </a>
            @if(auth()->user()->role === 'admin')
                <a href="{{ route('kitchen_inventory.supplies.create') }}" class="btn btn-primary fw-bold shadow-sm">
                    <i class="bi bi-plus-lg me-1"></i> Nuevo Insumo
                </a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('kitchen_inventory.supplies.index') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Buscar</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Nombre del insumo">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Categoría</label>
                    <select name="category_id" class="form-select">
                        <option value="">Todas</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Estado</label>
                    <select name="status" class="form-select">
                        <option value="active" @selected(request('status', 'active') === 'active')>Activos</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Inactivos</option>
                        <option value="all" @selected(request('status') === 'all')>Todos</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-primary fw-bold w-100"><i class="bi bi-funnel me-1"></i> Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Insumo</th>
                            <th>Categoría</th>
                            <th class="text-center">Unidad</th>
                            <th class="text-center">Stock Actual</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($supplies as $supply)
                            <tr data-supply-id="{{ $supply->id }}"
                                data-name="{{ $supply->name }}"
                                data-unit="{{ $supply->unit }}"
                                data-stock="{{ $supply->stock_current }}">
                                <td class="ps-4 fw-bold">{{ $supply->name }}</td>
                                <td>{{ $supply->category->name ?? '---' }}</td>
                                <td class="text-center">{{ $supply->unit }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $supply->stock_current > 0 ? 'bg-success' : 'bg-secondary' }} rounded-pill" data-stock-badge>
                                        {{ rtrim(rtrim(number_format($supply->stock_current, 4, '.', ''), '0'), '.') }} {{ $supply->unit }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    @if($supply->is_active)
                                        <span class="badge bg-success">Activo</span>
                                    @else
                                        <span class="badge bg-secondary">Inactivo</span>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    @if(auth()->user()->role === 'admin')
                                        <a href="{{ route('kitchen_inventory.supplies.adjust.form', $supply) }}" class="btn btn-sm btn-outline-success me-1" title="Ajustar stock" data-adjust-stock>
                                            <i class="bi bi-sliders"></i>
                                        </a>
                                        <a href="{{ route('kitchen_inventory.supplies.edit', $supply) }}" class="btn btn-sm btn-outline-primary me-1" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form action="{{ route('kitchen_inventory.supplies.destroy', $supply) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar el insumo {{ $supply->name }}? Solo es posible si no tiene historial.')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @else
                                        <span class="text-muted small">Solo lectura</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No hay insumos registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white">
            {{ $supplies->links() }}
        </div>
    </div>

    @if(auth()->user()->role === 'admin')
        <div class="modal fade" id="adjustStockModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">
                            <i class="bi bi-sliders me-2"></i>Ajustar Stock: <span id="adjustSupplyName"></span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-light border mb-3">
                            Stock actual: <strong class="fs-5" id="adjustStockCurrent"></strong>
                        </div>
                        <div id="adjustErrors" class="alert alert-danger" style="display: none;"></div>
                        <div id="adjustSuccess" class="alert alert-success" style="display: none;">
                            <i class="bi bi-check-circle me-1"></i>Movimiento registrado en el kardex.
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold" for="adjustType">Tipo de movimiento <span class="text-danger">*</span></label>
                            <select id="adjustType" class="form-select">
                                <option value="entry">Entrada (compra / recepción / stock inicial)</option>
                                <option value="adjustment_in">Ajuste + (corrección al alza)</option>
                                <option value="adjustment_out">Ajuste - (corrección a la baja)</option>
                                <option value="waste">Merma (descarte / vencimiento)</option>
                            </select>
                            <div class="text-danger small mt-1" id="adjustTypeError"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold" for="adjustQuantity">Cantidad (<span id="adjustUnit"></span>) <span class="text-danger">*</span></label>
                            <input type="number" id="adjustQuantity" class="form-control" step="0.0001" min="0.0001" placeholder="Ej. 5">
                            <div class="text-danger small mt-1" id="adjustQuantityError"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold" for="adjustNote">Motivo <span class="text-danger">*</span></label>
                            <textarea id="adjustNote" rows="3" class="form-control" maxlength="500"
                                      placeholder="Ej.: Stock inicial del insumo / Recepción de compra N° 001 / Merma por vencimiento"></textarea>
                            <div class="text-danger small mt-1" id="adjustNoteError"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary fw-bold" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-success fw-bold" id="adjustSubmit">
                            <i class="bi bi-check-lg me-1"></i> Registrar Movimiento
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            (function () {
                var modalEl = document.getElementById('adjustStockModal');
                if (!modalEl) {
                    return;
                }
                var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                var adjustUrlTemplate = '{{ route('kitchen_inventory.supplies.adjust', ['supply' => '__SUPPLY__']) }}';
                var csrfToken = document.querySelector('meta[name="csrf-token"]').content;

                var elName = document.getElementById('adjustSupplyName');
                var elStock = document.getElementById('adjustStockCurrent');
                var elUnit = document.getElementById('adjustUnit');
                var elType = document.getElementById('adjustType');
                var elQuantity = document.getElementById('adjustQuantity');
                var elNote = document.getElementById('adjustNote');
                var elErrors = document.getElementById('adjustErrors');
                var elSuccess = document.getElementById('adjustSuccess');
                var elTypeError = document.getElementById('adjustTypeError');
                var elQuantityError = document.getElementById('adjustQuantityError');
                var elNoteError = document.getElementById('adjustNoteError');

                var currentRow = null;
                var currentUuid = null;

                function newUuid() {
                    if (window.crypto && typeof crypto.randomUUID === 'function') {
                        return crypto.randomUUID();
                    }
                    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                        var r = Math.random() * 16 | 0;
                        var v = c === 'x' ? r : (r & 0x3 | 0x8);
                        return v.toString(16);
                    });
                }

                function formatQty(value) {
                    var num = Number(value);
                    if (!isFinite(num)) {
                        return String(value);
                    }
                    var str = num.toFixed(4);
                    return str.replace(/\.?0+$/, '');
                }

                function clearFeedback() {
                    elErrors.style.display = 'none';
                    elErrors.innerHTML = '';
                    elSuccess.style.display = 'none';
                    elTypeError.textContent = '';
                    elQuantityError.textContent = '';
                    elNoteError.textContent = '';
                }

                document.querySelectorAll('a[data-adjust-stock]').forEach(function (link) {
                    link.addEventListener('click', function (event) {
                        event.preventDefault();
                        currentRow = link.closest('tr');
                        currentUuid = newUuid();
                        clearFeedback();
                        elName.textContent = currentRow.dataset.name;
                        elUnit.textContent = currentRow.dataset.unit;
                        elStock.textContent = formatQty(currentRow.dataset.stock) + ' ' + currentRow.dataset.unit;
                        elType.value = 'entry';
                        elQuantity.value = '';
                        elNote.value = '';
                        modal.show();
                    });
                });

                document.getElementById('adjustSubmit').addEventListener('click', async function () {
                    if (!currentRow) {
                        return;
                    }
                    clearFeedback();

                    var body = new URLSearchParams();
                    body.set('form_uuid', currentUuid);
                    body.set('type', elType.value);
                    body.set('quantity', elQuantity.value);
                    body.set('note', elNote.value);

                    var response;
                    try {
                        response = await fetch(adjustUrlTemplate.replace('__SUPPLY__', currentRow.dataset.supplyId), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: body,
                        });
                    } catch (e) {
                        elErrors.textContent = 'Error de conexión. No se pudo registrar el movimiento.';
                        elErrors.style.display = 'block';
                        return;
                    }

                    if (response.status === 422) {
                        var data = {};
                        try {
                            data = await response.json();
                        } catch (e) {}
                        var fieldMap = { type: elTypeError, quantity: elQuantityError, note: elNoteError };
                        var messages = [];
                        if (data.message) {
                            messages.push(data.message);
                        }
                        Object.entries(data.errors || {}).forEach(function (entry) {
                            var key = entry[0];
                            var list = entry[1] || [];
                            if (fieldMap[key]) {
                                fieldMap[key].textContent = list[0] || '';
                            }
                            list.forEach(function (message) { messages.push(message); });
                        });
                        if (messages.length) {
                            elErrors.innerHTML = '';
                            messages.forEach(function (message) {
                                var div = document.createElement('div');
                                div.textContent = message;
                                elErrors.appendChild(div);
                            });
                            elErrors.style.display = 'block';
                        }
                        if (data.errors && data.errors.form_uuid) {
                            currentUuid = newUuid();
                        }
                        return;
                    }

                    if (!response.ok) {
                        elErrors.textContent = 'No se pudo registrar el movimiento.';
                        elErrors.style.display = 'block';
                        return;
                    }

                    var qty = parseFloat(elQuantity.value) || 0;
                    var stock = parseFloat(currentRow.dataset.stock) || 0;
                    var next = (elType.value === 'entry' || elType.value === 'adjustment_in') ? stock + qty : stock - qty;
                    if (next < 0) {
                        next = 0;
                    }
                    currentRow.dataset.stock = String(next);
                    var badge = currentRow.querySelector('[data-stock-badge]');
                    if (badge) {
                        badge.textContent = formatQty(next) + ' ' + currentRow.dataset.unit;
                        badge.className = 'badge ' + (next > 0 ? 'bg-success' : 'bg-secondary') + ' rounded-pill';
                    }

                    currentUuid = newUuid();
                    elQuantity.value = '';
                    elNote.value = '';
                    elSuccess.style.display = 'block';
                });
            })();
        </script>
    @endif
</div>
@endsection
