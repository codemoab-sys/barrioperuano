@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="{{ route('kitchen_inventory.recipes.index') }}" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left"></i> Volver a Recetas</a>
            <h2 class="fw-bold text-dark mb-0"><i class="bi bi-journal-bookmark me-2"></i>{{ $recipe->name }}</h2>
            <p class="text-muted mb-0">Plato: <strong>{{ $recipe->product->name ?? '---' }}</strong></p>
        </div>
        <div>
            @if($recipe->is_active)
                <form action="{{ route('kitchen_inventory.recipes.deactivate', $recipe) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('¿Desactivar la receta? Dejará de validar y consumir stock de cocina en los próximos checkouts.');">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger fw-bold shadow-sm me-2">
                        <i class="bi bi-pause-circle me-1"></i> Desactivar
                    </button>
                </form>
            @else
                <form action="{{ route('kitchen_inventory.recipes.activate', $recipe) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-success fw-bold shadow-sm me-2">
                        <i class="bi bi-play-circle me-1"></i> Activar
                    </button>
                </form>
            @endif
            <form action="{{ route('kitchen_inventory.recipes.versions.store', $recipe) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-primary fw-bold shadow-sm">
                    <i class="bi bi-plus-lg me-1"></i> Nueva Versión
                </button>
            </form>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @if($pending > 0)
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <strong>{{ $pending }} pedido(s) pendiente(s)</strong> usan actualmente este plato. Mientras existan, la receta <strong>no puede desactivarse</strong> (los pedidos pendientes la consumirán al cobrarse).
        </div>
    @endif

    @if(! $recipe->is_active)
        <div class="alert alert-secondary">
            <i class="bi bi-pause-circle me-1"></i>
            Receta <strong>inactiva</strong>: el plato deja de validar y consumir stock de cocina en los próximos checkouts. Puede preparar borradores; no tendrán efecto hasta reactivar.
        </div>
    @endif

    <div class="alert alert-light border">
        <i class="bi bi-info-circle me-1"></i>
        Al desactivar, el plato dejará de validar y consumir stock de cocina en el próximo checkout. La desactivación está bloqueada mientras haya pedidos pendientes con este plato (la protección reduce el riesgo, pero no elimina la carrera residual con pedidos creados al mismo instante).
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-bold"><i class="bi bi-layers me-1"></i> Versiones</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Versión</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">Insumos</th>
                            <th>Publicada</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recipe->versions as $version)
                            <tr data-version-row="{{ $version->id }}">
                                <td class="ps-4 fw-bold">#{{ $version->version }}</td>
                                <td class="text-center">
                                    @if($version->status === 'published')
                                        <span class="badge bg-success">Publicada</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Borrador</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @php
                                        $itemsJson = json_encode($version->items->map(fn ($i) => [
                                            'name' => $i->supply->name ?? '---',
                                            'qty' => rtrim(rtrim(number_format($i->quantity, 4, '.', ''), '0'), '.'),
                                            'unit' => $i->supply->unit ?? '',
                                        ])->values());
                                    @endphp
                                    <button type="button" class="btn btn-sm btn-outline-secondary" title="Ver insumos"
                                            data-bs-toggle="modal" data-bs-target="#versionItemsModal"
                                            data-version="{{ $version->version }}"
                                            data-items="{{ $itemsJson }}">
                                        <span class="items-count">{{ $version->items_count }}</span> <i class="bi bi-eye"></i>
                                    </button>
                                </td>
                                <td class="small text-muted">{{ $version->published_at?->format('d/m/Y H:i') ?? '---' }}</td>
                                <td class="text-end pe-4">
                                    @if($version->status === 'draft')
                                        <a href="{{ route('kitchen_inventory.versions.edit', $version) }}" class="btn btn-sm btn-outline-primary me-1" title="Editar borrador" data-edit-draft>
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form action="{{ route('kitchen_inventory.versions.publish', $version) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('¿Publicar la versión #{{ $version->version }}? Quedará inmutable y será la que consuma el sistema.');">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Publicar">
                                                <i class="bi bi-upload"></i>
                                            </button>
                                        </form>
                                        <form action="{{ route('kitchen_inventory.versions.destroy', $version) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('¿Eliminar el borrador #{{ $version->version }}? Se borrarán sus insumos. Esta acción no se puede deshacer.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar borrador">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-muted small me-1">Inmutable</span>
                                        <form action="{{ route('kitchen_inventory.versions.destroy', $version) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('¿Eliminar la versión publicada #{{ $version->version }}? Solo es posible si nunca se usó en consumos de pedidos. Esta acción no se puede deshacer.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar versión">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">Sin versiones. Cree la primera versión borrador.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="draftEditorModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="editorTitle">Borrador</h5>
                        <small class="text-muted" id="editorSubtitle"></small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div id="editorErrors" class="alert alert-danger" style="display: none;"></div>
                    <div id="editorSuccess" class="alert alert-success" style="display: none;"></div>
                    <div class="table-responsive mb-3" id="editorTableWrap">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Insumo</th>
                                    <th class="text-end">Cantidad por plato</th>
                                    <th class="text-end" style="width: 110px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="editorBody"></tbody>
                        </table>
                    </div>
                    <div id="editorEmpty" class="text-muted text-center py-4 border rounded mb-3">
                        <i class="bi bi-info-circle me-1"></i> Sin insumos agregados todavía. Use "Agregar insumo".
                    </div>
                    <button type="button" id="editorAddItem" class="btn btn-outline-primary fw-bold">
                        <i class="bi bi-plus-lg me-1"></i> Agregar insumo
                    </button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary fw-bold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary fw-bold" id="editorSave">
                        <i class="bi bi-check-lg me-1"></i> Guardar Borrador
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="itemModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="itemModalTitle">Agregar insumo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="modalSupply">Insumo <span class="text-danger">*</span></label>
                        <select id="modalSupply" class="form-select"></select>
                        <div class="text-danger small mt-1" id="modalSupplyError"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="modalQuantity">Cantidad por plato <span class="text-danger">*</span></label>
                        <input type="number" id="modalQuantity" class="form-control" min="0.0001" step="any" placeholder="Ej. 0.150">
                        <div class="text-danger small mt-1" id="modalQuantityError"></div>
                    </div>
                    <div class="alert alert-warning py-2 small mb-0">
                        <i class="bi bi-exclamation-triangle me-1"></i> Si el insumo ya está en la receta se pedirá confirmación para reemplazar su cantidad.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary fw-bold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary fw-bold" id="modalConfirm">
                        <i class="bi bi-check-lg me-1"></i> <span id="modalConfirmText">Agregar</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="versionItemsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-list-ul me-2"></i>Insumos de la versión #<span id="vItemsVersion"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Insumo</th>
                                <th class="text-end pe-4">Cantidad</th>
                            </tr>
                        </thead>
                        <tbody id="vItemsBody"></tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary fw-bold" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modalEl = document.getElementById('versionItemsModal');
            if (!modalEl) {
                return;
            }
            modalEl.addEventListener('show.bs.modal', function (event) {
                var btn = event.relatedTarget;
                document.getElementById('vItemsVersion').textContent = btn.getAttribute('data-version');
                var body = document.getElementById('vItemsBody');
                body.innerHTML = '';
                var items = [];
                try {
                    items = JSON.parse(btn.getAttribute('data-items') || '[]');
                } catch (e) {
                    items = [];
                }
                if (!items.length) {
                    var empty = document.createElement('tr');
                    var emptyTd = document.createElement('td');
                    emptyTd.colSpan = 2;
                    emptyTd.className = 'text-center text-muted py-3';
                    emptyTd.textContent = 'Sin insumos registrados en esta versión.';
                    empty.appendChild(emptyTd);
                    body.appendChild(empty);
                    return;
                }
                items.forEach(function (item) {
                    var tr = document.createElement('tr');
                    var tdName = document.createElement('td');
                    tdName.textContent = item.name;
                    var tdQty = document.createElement('td');
                    tdQty.className = 'text-end pe-4';
                    tdQty.textContent = item.qty + ' ' + item.unit;
                    tr.appendChild(tdName);
                    tr.appendChild(tdQty);
                    body.appendChild(tr);
                });
            });
        });

        (function () {
            var csrfToken = document.querySelector('meta[name="csrf-token"]')
                ? document.querySelector('meta[name="csrf-token"]').content
                : (document.querySelector('input[name="_token"]') || {}).value || '';
            var versionDataUrl = '{{ route('kitchen_inventory.versions.data', ['version' => '__VERSION__']) }}';

            var editorModalEl = document.getElementById('draftEditorModal');
            var itemModalEl = document.getElementById('itemModal');

            function editorModal() {
                return window.bootstrap.Modal.getOrCreateInstance(editorModalEl);
            }

            function itemModal() {
                return window.bootstrap.Modal.getOrCreateInstance(itemModalEl);
            }
            var editorTitle = document.getElementById('editorTitle');
            var editorSubtitle = document.getElementById('editorSubtitle');
            var editorBody = document.getElementById('editorBody');
            var editorEmpty = document.getElementById('editorEmpty');
            var editorErrors = document.getElementById('editorErrors');
            var editorSuccess = document.getElementById('editorSuccess');
            var modalSupply = document.getElementById('modalSupply');
            var modalQuantity = document.getElementById('modalQuantity');
            var modalSupplyError = document.getElementById('modalSupplyError');
            var modalQuantityError = document.getElementById('modalQuantityError');
            var modalConfirm = document.getElementById('modalConfirm');
            var modalConfirmText = document.getElementById('modalConfirmText');
            var itemModalTitle = document.getElementById('itemModalTitle');

            var editorState = { versionId: null, updateUrl: null, supplies: [], items: [], editIndex: -1, itemEditIndex: -1 };

            function formatQty(value) {
                var num = Number(value);
                if (!isFinite(num)) {
                    return String(value);
                }
                var str = num.toFixed(4);
                return str.replace(/\.?0+$/, '');
            }

            function showEditorErrors(messages) {
                editorErrors.innerHTML = '';
                messages.forEach(function (message) {
                    var div = document.createElement('div');
                    div.textContent = message;
                    editorErrors.appendChild(div);
                });
                editorErrors.style.display = 'block';
                editorSuccess.style.display = 'none';
            }

            function showEditorSuccess(message) {
                editorSuccess.textContent = message;
                editorSuccess.style.display = 'block';
                editorErrors.style.display = 'none';
            }

            function hideEditorAlerts() {
                editorErrors.style.display = 'none';
                editorSuccess.style.display = 'none';
            }

            function pageError(message) {
                var container = document.querySelector('.container-fluid');
                if (!container) {
                    alert(message);
                    return;
                }
                var alert = document.createElement('div');
                alert.className = 'alert alert-danger alert-dismissible fade show';
                alert.textContent = message;
                container.insertBefore(alert, container.firstChild);
                setTimeout(function () { alert.remove(); }, 6000);
            }

            function rebuildSupplyOptions() {
                modalSupply.innerHTML = '';
                var placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = 'Seleccione insumo...';
                modalSupply.appendChild(placeholder);
                editorState.supplies.forEach(function (supply) {
                    var option = document.createElement('option');
                    option.value = String(supply.id);
                    option.textContent = supply.name + (supply.unit ? ' (' + supply.unit + ')' : '');
                    modalSupply.appendChild(option);
                });
            }

            function renderEditor() {
                editorBody.innerHTML = '';
                if (!editorState.items.length) {
                    editorEmpty.style.display = 'block';
                    document.getElementById('editorTableWrap').style.display = 'none';
                    return;
                }
                editorEmpty.style.display = 'none';
                document.getElementById('editorTableWrap').style.display = 'block';
                editorState.items.forEach(function (item, index) {
                    var tr = document.createElement('tr');

                    var tdName = document.createElement('td');
                    tdName.textContent = item.name;
                    tr.appendChild(tdName);

                    var tdQty = document.createElement('td');
                    tdQty.className = 'text-end';
                    tdQty.textContent = formatQty(item.quantity) + (item.unit ? ' ' + item.unit : '');
                    tr.appendChild(tdQty);

                    var tdActions = document.createElement('td');
                    tdActions.className = 'text-end';

                    var editBtn = document.createElement('button');
                    editBtn.type = 'button';
                    editBtn.className = 'btn btn-sm btn-outline-primary me-1';
                    editBtn.title = 'Editar';
                    var editIcon = document.createElement('i');
                    editIcon.className = 'bi bi-pencil';
                    editBtn.appendChild(editIcon);
                    editBtn.addEventListener('click', function () { openItemModal(index); });

                    var removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.className = 'btn btn-sm btn-outline-danger';
                    removeBtn.title = 'Quitar';
                    var removeIcon = document.createElement('i');
                    removeIcon.className = 'bi bi-trash';
                    removeBtn.appendChild(removeIcon);
                    removeBtn.addEventListener('click', function () {
                        editorState.items.splice(index, 1);
                        renderEditor();
                    });

                    tdActions.appendChild(editBtn);
                    tdActions.appendChild(removeBtn);
                    tr.appendChild(tdActions);
                    editorBody.appendChild(tr);
                });
            }

            function openItemModal(index) {
                editorState.itemEditIndex = index;
                modalSupplyError.textContent = '';
                modalQuantityError.textContent = '';
                modalSupply.value = '';
                modalQuantity.value = '';
                if (index >= 0) {
                    var item = editorState.items[index];
                    itemModalTitle.textContent = 'Editar insumo';
                    modalConfirmText.textContent = 'Guardar';
                    modalSupply.value = String(item.supply_id);
                    modalQuantity.value = String(item.quantity);
                } else {
                    itemModalTitle.textContent = 'Agregar insumo';
                    modalConfirmText.textContent = 'Agregar';
                }
                itemModal().show();
            }

            async function openDraftEditor(versionId) {
                hideEditorAlerts();
                var response;
                try {
                    response = await fetch(versionDataUrl.replace('__VERSION__', String(versionId)), {
                        headers: { 'Accept': 'application/json' },
                    });
                } catch (e) {
                    pageError('Error de conexión al cargar el borrador.');
                    return;
                }
                if (!response.ok) {
                    var message = 'No se pudo cargar el borrador.';
                    try {
                        var errorData = await response.json();
                        message = errorData.message
                            || (errorData.errors ? Object.values(errorData.errors).flat()[0] : message);
                    } catch (e) {}
                    pageError(message);
                    return;
                }
                var data = await response.json();
                editorState.versionId = data.id;
                editorState.updateUrl = data.update_url;
                editorState.supplies = data.supplies;
                editorState.items = data.items.map(function (item) {
                    return { supply_id: item.supply_id, quantity: item.quantity, name: item.name, unit: item.unit };
                });
                editorTitle.textContent = 'Borrador Versión #' + data.version;
                editorSubtitle.textContent = data.recipe_name + (data.product_name ? ' — ' + data.product_name : '');
                rebuildSupplyOptions();
                renderEditor();
                editorModal().show();
            }

            modalConfirm.addEventListener('click', function () {
                modalSupplyError.textContent = '';
                modalQuantityError.textContent = '';
                var supplyId = parseInt(modalSupply.value, 10);
                var quantity = parseFloat(modalQuantity.value);
                var valid = true;
                if (!supplyId) {
                    modalSupplyError.textContent = 'Seleccione un insumo.';
                    valid = false;
                }
                if (!isFinite(quantity) || quantity <= 0) {
                    modalQuantityError.textContent = 'Ingrese una cantidad mayor a 0.';
                    valid = false;
                }
                if (!valid) {
                    return;
                }
                var supply = editorState.supplies.find(function (s) { return s.id === supplyId; });
                var duplicateIndex = -1;
                for (var i = 0; i < editorState.items.length; i++) {
                    if (editorState.items[i].supply_id === supplyId && i !== editorState.itemEditIndex) {
                        duplicateIndex = i;
                        break;
                    }
                }
                var proceed = true;
                if (duplicateIndex >= 0 && editorState.itemEditIndex < 0) {
                    proceed = window.confirm('"' + (supply ? supply.name : '') + '" ya está en la receta con ' + formatQty(editorState.items[duplicateIndex].quantity) + '. ¿Reemplazar su cantidad?');
                    if (proceed) {
                        editorState.items.splice(duplicateIndex, 1);
                    }
                } else if (duplicateIndex >= 0) {
                    proceed = window.confirm('Otro insumo "' + (supply ? supply.name : '') + '" ya está en la receta. ¿Reemplazarlo por este?');
                    if (proceed) {
                        editorState.items.splice(duplicateIndex, 1);
                        if (duplicateIndex < editorState.itemEditIndex) {
                            editorState.itemEditIndex -= 1;
                        }
                    }
                }
                if (!proceed) {
                    return;
                }
                var entry = {
                    supply_id: supplyId,
                    quantity: quantity,
                    name: supply ? supply.name : ('Insumo #' + supplyId),
                    unit: supply ? supply.unit : '',
                };
                if (editorState.itemEditIndex >= 0) {
                    editorState.items[editorState.itemEditIndex] = entry;
                } else {
                    editorState.items.push(entry);
                }
                renderEditor();
                itemModal().hide();
            });

            document.getElementById('editorAddItem').addEventListener('click', function () { openItemModal(-1); });

            document.getElementById('editorSave').addEventListener('click', async function () {
                hideEditorAlerts();
                var body = new URLSearchParams();
                editorState.items.forEach(function (item, index) {
                    body.set('items[' + index + '][kitchen_supply_id]', String(item.supply_id));
                    body.set('items[' + index + '][quantity]', String(item.quantity));
                });
                var response;
                try {
                    response = await fetch(editorState.updateUrl, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: body,
                    });
                } catch (e) {
                    showEditorErrors(['Error de conexión. No se pudo guardar.']);
                    return;
                }
                if (response.status === 422) {
                    var errorData = {};
                    try { errorData = await response.json(); } catch (e) {}
                    var messages = [];
                    if (errorData.message) {
                        messages.push(errorData.message);
                    }
                    Object.values(errorData.errors || {}).flat().forEach(function (message) { messages.push(message); });
                    showEditorErrors(messages.length ? messages : ['No se pudo guardar el borrador.']);
                    return;
                }
                if (!response.ok) {
                    showEditorErrors(['No se pudo guardar el borrador.']);
                    return;
                }
                var countEl = document.querySelector('[data-version-row="' + editorState.versionId + '"] .items-count');
                if (countEl) {
                    countEl.textContent = String(editorState.items.length);
                }
                showEditorSuccess('Borrador guardado. Publíquelo para que surta efecto en el consumo.');
            });

            document.querySelectorAll('a[data-edit-draft]').forEach(function (link) {
                link.addEventListener('click', function (event) {
                    event.preventDefault();
                    var parts = link.getAttribute('href').split('/').filter(Boolean);
                    openDraftEditor(parts[parts.length - 2]);
                });
            });

            var params = new URLSearchParams(window.location.search);
            var openDraft = params.get('open_draft');
            if (openDraft) {
                window.history.replaceState(null, '', window.location.pathname);
                openDraftEditor(openDraft);
            }
        })();
    </script>
</div>
@endsection
