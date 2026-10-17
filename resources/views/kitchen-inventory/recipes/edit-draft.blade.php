@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="mb-4">
        <a href="{{ route('kitchen_inventory.recipes.show', $version->recipe_id) }}" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left"></i> Volver a la receta</a>
        <h2 class="fw-bold text-dark mb-0"><i class="bi bi-pencil me-2"></i>Borrador Versión #{{ $version->version }}</h2>
        <p class="text-muted mb-0">{{ $version->recipe->name }} — {{ $version->recipe->product->name ?? '' }}</p>
    </div>

    <div class="card border-0 shadow-sm" style="max-width: 860px;">
        <div class="card-body">
            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0 ps-3">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('kitchen_inventory.versions.update', $version) }}" id="draftForm">
                @csrf @method('PUT')
                <div id="itemsContainer" class="d-none"></div>

                <div class="table-responsive mb-3" id="itemsTableWrap">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Insumo</th>
                                <th class="text-end">Cantidad por plato</th>
                                <th class="text-end pe-3" style="width: 130px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody"></tbody>
                    </table>
                </div>
                <div id="itemsEmpty" class="text-muted text-center py-4 border rounded mb-3">
                    <i class="bi bi-info-circle me-1"></i> Sin insumos agregados todavía. Use "Agregar insumo".
                </div>

                <button type="button" id="addItemBtn" class="btn btn-outline-primary fw-bold"><i class="bi bi-plus-lg me-1"></i> Agregar insumo</button>

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary fw-bold"><i class="bi bi-check-lg me-1"></i> Guardar Borrador</button>
                    <a href="{{ route('kitchen_inventory.recipes.show', $version->recipe_id) }}" class="btn btn-outline-secondary fw-bold">Cancelar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="itemModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="itemModalTitle"><i class="bi bi-plus-lg me-2"></i>Agregar insumo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Insumo <span class="text-danger">*</span></label>
                        <select id="modalSupply" class="form-select">
                            <option value="">Seleccione insumo...</option>
                            @foreach($supplies as $supply)
                                <option value="{{ $supply->id }}">{{ $supply->name }} ({{ $supply->unit }})</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback" id="modalSupplyError">Debe seleccionar un insumo.</div>
                    </div>
                    <div class="mb-1">
                        <label class="form-label fw-bold">Cantidad por plato <span class="text-danger">*</span></label>
                        <input type="number" id="modalQuantity" class="form-control" step="0.0001" min="0.0001" placeholder="0.0000">
                        <div class="invalid-feedback" id="modalQuantityError">Ingrese una cantidad mayor a 0.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary fw-bold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary fw-bold" id="modalConfirm"><i class="bi bi-check-lg me-1"></i> <span id="modalConfirmText">Agregar</span></button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    @php $rows = old('items', $version->items->map(fn ($i) => ['kitchen_supply_id' => $i->kitchen_supply_id, 'quantity' => $i->quantity])->toArray()); @endphp
    const supplies = @php echo json_encode($supplies->map(fn ($s) => ['id' => (int) $s->id, 'name' => $s->name, 'unit' => $s->unit])->values()); @endphp;
    const initialRows = @php echo json_encode(array_values($rows)); @endphp;

    const items = [];
    initialRows.forEach(function (row) {
        const supplyId = parseInt(row.kitchen_supply_id, 10) || 0;
        const supply = supplies.find(function (s) { return s.id === supplyId; });
        items.push({ supply_id: supplyId, quantity: row.quantity || '', name: supply ? supply.name : '', unit: supply ? supply.unit : '' });
    });

    const container = document.getElementById('itemsContainer');
    const body = document.getElementById('itemsBody');
    const empty = document.getElementById('itemsEmpty');
    const tableWrap = document.getElementById('itemsTableWrap');
    const modalEl = document.getElementById('itemModal');
    function getModal() {
        return bootstrap.Modal.getOrCreateInstance(modalEl);
    }
    const modalTitle = document.getElementById('itemModalTitle');
    const modalSupply = document.getElementById('modalSupply');
    const modalQuantity = document.getElementById('modalQuantity');
    const modalSupplyError = document.getElementById('modalSupplyError');
    const modalQuantityError = document.getElementById('modalQuantityError');
    const confirmText = document.getElementById('modalConfirmText');
    let editIndex = -1;

    function formatQty(value) {
        const str = String(value);
        if (str.indexOf('.') === -1) return str;
        return str.replace(/0+$/, '').replace(/\.$/, '');
    }

    function render() {
        body.innerHTML = '';
        container.innerHTML = '';

        items.forEach(function (item, index) {
            const hiddenSupply = document.createElement('input');
            hiddenSupply.type = 'hidden';
            hiddenSupply.name = 'items[' + index + '][kitchen_supply_id]';
            hiddenSupply.value = item.supply_id;
            container.appendChild(hiddenSupply);

            const hiddenQty = document.createElement('input');
            hiddenQty.type = 'hidden';
            hiddenQty.name = 'items[' + index + '][quantity]';
            hiddenQty.value = item.quantity;
            container.appendChild(hiddenQty);

            const tr = document.createElement('tr');

            const tdName = document.createElement('td');
            tdName.className = 'ps-3';
            tdName.textContent = item.name + (item.unit ? ' (' + item.unit + ')' : '');
            tr.appendChild(tdName);

            const tdQty = document.createElement('td');
            tdQty.className = 'text-end';
            tdQty.textContent = formatQty(item.quantity);
            tr.appendChild(tdQty);

            const tdActions = document.createElement('td');
            tdActions.className = 'text-end pe-3';

            const editBtn = document.createElement('button');
            editBtn.type = 'button';
            editBtn.className = 'btn btn-sm btn-outline-primary me-1';
            editBtn.title = 'Editar insumo';
            editBtn.dataset.action = 'edit';
            editBtn.dataset.index = String(index);
            const editIcon = document.createElement('i');
            editIcon.className = 'bi bi-pencil';
            editBtn.appendChild(editIcon);

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn btn-sm btn-outline-danger';
            removeBtn.title = 'Quitar insumo';
            removeBtn.dataset.action = 'remove';
            removeBtn.dataset.index = String(index);
            const removeIcon = document.createElement('i');
            removeIcon.className = 'bi bi-trash';
            removeBtn.appendChild(removeIcon);

            tdActions.appendChild(editBtn);
            tdActions.appendChild(removeBtn);
            tr.appendChild(tdActions);

            body.appendChild(tr);
        });

        const isEmpty = items.length === 0;
        empty.style.display = isEmpty ? 'block' : 'none';
        tableWrap.style.display = isEmpty ? 'none' : 'block';
    }

    function clearInvalid() {
        modalSupply.classList.remove('is-invalid');
        modalQuantity.classList.remove('is-invalid');
        modalSupplyError.style.display = 'none';
        modalQuantityError.style.display = 'none';
    }

    function openModal(index) {
        clearInvalid();
        editIndex = index;
        if (index >= 0) {
            modalTitle.innerHTML = '<i class="bi bi-pencil me-2"></i>Editar insumo';
            confirmText.textContent = 'Guardar';
            modalSupply.value = String(items[index].supply_id);
            modalQuantity.value = items[index].quantity;
        } else {
            modalTitle.innerHTML = '<i class="bi bi-plus-lg me-2"></i>Agregar insumo';
            confirmText.textContent = 'Agregar';
            modalSupply.value = '';
            modalQuantity.value = '';
        }
        getModal().show();
    }

    document.getElementById('addItemBtn').addEventListener('click', function () {
        openModal(-1);
    });

    body.addEventListener('click', function (event) {
        const button = event.target.closest('button[data-action]');
        if (!button) return;
        const index = parseInt(button.dataset.index, 10);
        if (button.dataset.action === 'edit') {
            openModal(index);
        } else {
            items.splice(index, 1);
            render();
        }
    });

    document.getElementById('modalConfirm').addEventListener('click', function () {
        clearInvalid();

        const supplyId = parseInt(modalSupply.value, 10) || 0;
        const quantity = String(modalQuantity.value).trim();
        let valid = true;

        if (!supplyId) {
            modalSupply.classList.add('is-invalid');
            modalSupplyError.style.display = 'block';
            valid = false;
        }
        if (!quantity || isNaN(parseFloat(quantity)) || parseFloat(quantity) <= 0) {
            modalQuantity.classList.add('is-invalid');
            modalQuantityError.style.display = 'block';
            valid = false;
        }
        if (!valid) return;

        const duplicated = items.some(function (item, index) {
            return item.supply_id === supplyId && index !== editIndex;
        });
        if (duplicated) {
            modalSupply.classList.add('is-invalid');
            modalSupplyError.textContent = 'Este insumo ya está en la lista.';
            modalSupplyError.style.display = 'block';
            return;
        }

        const supply = supplies.find(function (s) { return s.id === supplyId; });
        const entry = {
            supply_id: supplyId,
            quantity: quantity,
            name: supply ? supply.name : '',
            unit: supply ? supply.unit : '',
        };

        if (editIndex >= 0) {
            items[editIndex] = entry;
        } else {
            items.push(entry);
        }

        render();
        getModal().hide();
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
        modalSupplyError.textContent = 'Debe seleccionar un insumo.';
        clearInvalid();
    });

    render();
})();
</script>
@endsection
