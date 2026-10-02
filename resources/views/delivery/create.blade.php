@extends('layouts.app')

@section('content')
<div class="container-fluid py-3" style="height: calc(100vh - 60px); display: flex; flex-direction: column;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-0 text-gray-800"><i class="bi bi-bicycle me-2 text-primary"></i>Nuevo Pedido Delivery</h4>
        </div>
        <a href="{{ route('delivery.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    <form action="{{ route('delivery.store') }}" method="POST" id="deliveryForm" class="row g-3 flex-grow-1 overflow-hidden" style="min-height: 0;">
        @csrf
        
        {{-- PANEL IZQUIERDO: Datos del Cliente --}}
        <div class="col-12 col-md-4 h-100 d-flex flex-column">
            <div class="card shadow-sm border-0 h-100 flex-column d-flex">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-person-lines-fill me-2"></i>Datos de Entrega</h6>
                </div>
                <div class="card-body overflow-auto">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Cliente</label>
                        <select name="client_id" id="client_id" class="form-select searchable-select">
                            <option value="">Consumidor Final / Nuevo</option>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}" data-name="{{ $client->name }}" data-phone="{{ $client->phone }}" data-address="{{ $client->address }}">{{ $client->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nombre *</label>
                        <input type="text" name="client_name" id="client_name" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Teléfono *</label>
                        <input type="text" name="client_phone" id="client_phone" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Dirección *</label>
                        <textarea name="address" id="address" class="form-control" rows="2" required></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Referencia (Opcional)</label>
                        <input type="text" name="reference" class="form-control" placeholder="Ej. Casa verde, puerta blanca">
                    </div>
                    
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-bold">Método Pago *</label>
                            <select name="payment_method" class="form-select" required>
                                <option value="cash">Efectivo</option>
                                <option value="card">Tarjeta</option>
                                <option value="transfer">Transferencia / Yape</option>
                            </select>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-bold">Costo Envío ({{ $currency }})</label>
                            <input type="number" step="0.10" min="0" name="delivery_fee" id="delivery_fee" class="form-control" value="0.00">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Repartidor (Opcional)</label>
                        <select name="driver_id" class="form-select searchable-select-driver">
                            <option value="">Sin asignar por ahora</option>
                            @foreach($drivers as $driver)
                                <option value="{{ $driver->id }}">{{ $driver->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Notas adicionales</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Ej. Llevar vuelto de 50"></textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- PANEL DERECHO: Productos y Carrito --}}
        <div class="col-12 col-md-8 h-100 d-flex flex-column">
            <div class="card shadow-sm border-0 h-100 flex-column d-flex">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0"><i class="bi bi-box-seam me-2"></i>Productos</h6>
                    <span class="badge bg-primary rounded-pill" id="cartItemCount">0 items</span>
                </div>
                
                <div class="card-body p-0 d-flex flex-row overflow-hidden" style="flex: 1;">
                    {{-- Lista de Productos --}}
                    <div class="w-50 bg-light border-end overflow-auto p-3" id="productGrid">
                        {{-- Buscador --}}
                        <div class="mb-3">
                            <input type="text" id="searchProduct" class="form-control" placeholder="Buscar producto...">
                        </div>
                        
                        <div class="row g-2">
                            @foreach($categories as $category)
                                @foreach($category->products as $product)
                                    <div class="col-6 product-card" data-name="{{ strtolower($product->name) }}" data-cat="{{ $category->id }}">
                                        <div class="card h-100 border-0 shadow-sm" style="cursor: pointer;" onclick="addDeliveryProduct({{ $product->id }}, '{{ addslashes($product->name) }}', {{ $product->price }}, {{ \\App\\Services\\WingSauceMenu::flavorLimit($product) ?? 0 }})">
                                            <div class="card-body p-2 text-center">
                                                <h6 class="small fw-bold mb-1 text-truncate" title="{{ $product->name }}">{{ $product->name }}</h6>
                                                <div class="text-primary fw-bold small">{{ $currency }}{{ number_format($product->price, 2) }}</div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            @endforeach
                        </div>
                    </div>
                    
                    {{-- Carrito --}}
                    <div class="w-50 d-flex flex-column bg-white">
                        <div class="flex-grow-1 overflow-auto p-2" id="deliveryCartContainer">
                            <table class="table table-sm table-borderless align-middle mb-0" style="width: 100%; table-layout: fixed;">
                                <tbody id="deliveryCartItems">
                                    <tr><td colspan="4" class="text-center text-muted py-4 small">Seleccione productos para agregar a la orden.</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="p-3 border-top bg-light flex-shrink-0">
                            <div class="d-flex justify-content-between fw-bold mb-2 small text-muted">
                                <span>Subtotal:</span>
                                <span>{{ $currency }}<span id="subtotalAmount">0.00</span></span>
                            </div>
                            <div class="d-flex justify-content-between fw-bold mb-3 fs-5">
                                <span>TOTAL:</span>
                                <span class="text-primary">{{ $currency }}<span id="totalAmount">0.00</span></span>
                            </div>
                            <button type="submit" class="btn btn-success w-100 py-2 fw-bold shadow-sm" id="btnSubmitDelivery" disabled>
                                <i class="bi bi-check-circle me-1"></i> CONFIRMAR PEDIDO
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<div class="modal fade" id="deliverySauceModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <div>
                    <h5 class="modal-title fw-bold">Elige los sabores</h5>
                    <div id="deliverySauceProduct" class="small text-dark"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p id="deliverySauceHint" class="small text-muted mb-2"></p>
                <div id="deliverySauceOptions" class="row g-2"></div>
                <div id="deliverySauceCount" class="small fw-bold text-end mt-2">0 seleccionados</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning fw-bold" onclick="confirmDeliverySauces()">Agregar al pedido</button>
            </div>
        </div>
    </div>
</div>

<script>
    let deliveryCart = [];
    const wingSauceGroups = @json(\\App\\Services\\WingSauceMenu::groups());
    let pendingSauceProduct = null;

    document.addEventListener('DOMContentLoaded', function() {
        if (typeof TomSelect !== 'undefined') {
            new TomSelect('#client_id', {
                plugins: ['clear_button'],
                persist: false,
                create: false,
                sortField: { field: 'text', direction: 'asc' },
                placeholder: 'Buscar cliente...',
                hideSelected: false,
                closeAfterSelect: true,
                onInitialize: function() {
                    this.wrapper.classList.add('searchable-select-wrapper');
                }
            });

            new TomSelect('.searchable-select-driver', {
                plugins: ['clear_button'],
                persist: false,
                create: false,
                sortField: { field: 'text', direction: 'asc' },
                placeholder: 'Buscar repartidor...',
                hideSelected: false,
                closeAfterSelect: true,
            });
        }
    });
    
    document.getElementById('client_id').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.value) {
            document.getElementById('client_name').value = selectedOption.getAttribute('data-name');
            document.getElementById('client_phone').value = selectedOption.getAttribute('data-phone');
            document.getElementById('address').value = selectedOption.getAttribute('data-address');
        } else {
            document.getElementById('client_name').value = '';
            document.getElementById('client_phone').value = '';
            document.getElementById('address').value = '';
        }
    });

    document.getElementById('searchProduct').addEventListener('keyup', function() {
        const val = this.value.toLowerCase();
        document.querySelectorAll('.product-card').forEach(el => {
            el.style.display = el.getAttribute('data-name').includes(val) ? 'block' : 'none';
        });
    });

    document.getElementById('delivery_fee').addEventListener('input', renderDeliveryCart);

    function addDeliveryProduct(id, name, price, maxSauces = 0, sauceChoices = null) {
        if (maxSauces > 0 && sauceChoices === null) {
            showDeliverySauceSelector({ id, name, price, maxSauces });
            return;
        }

        const selectedSauces = sauceChoices || [];
        const existing = deliveryCart.find(i => i.id === id && JSON.stringify(i.sauce_choices) === JSON.stringify(selectedSauces));
        if(existing) existing.qty++;
        else deliveryCart.push({ id, name, price, qty: 1, sauce_choices: selectedSauces });
        renderDeliveryCart();
    }

    function showDeliverySauceSelector(product) {
        pendingSauceProduct = product;
        document.getElementById('deliverySauceProduct').textContent = product.name;
        document.getElementById('deliverySauceHint').textContent = `Selecciona entre 1 y ${product.maxSauces} sabores.`;
        const options = document.getElementById('deliverySauceOptions');
        options.innerHTML = '';

        Object.entries(wingSauceGroups).forEach(([group, sauces]) => {
            const column = document.createElement('div');
            column.className = 'col-6';
            const section = document.createElement('div');
            section.className = 'border rounded p-2 h-100';
            const heading = document.createElement('div');
            heading.className = 'small fw-bold mb-1';
            heading.textContent = group;
            section.appendChild(heading);
            sauces.forEach(sauce => {
                const label = document.createElement('label');
                label.className = 'form-check small mb-1';
                const checkbox = document.createElement('input');
                checkbox.className = 'form-check-input delivery-sauce-choice';
                checkbox.type = 'checkbox';
                checkbox.value = sauce;
                checkbox.addEventListener('change', updateDeliverySauceCount);
                const text = document.createElement('span');
                text.className = 'form-check-label';
                text.textContent = sauce;
                label.append(checkbox, text);
                section.appendChild(label);
            });
            column.appendChild(section);
            options.appendChild(column);
        });

        updateDeliverySauceCount();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('deliverySauceModal')).show();
    }

    function updateDeliverySauceCount() {
        const checkboxes = Array.from(document.querySelectorAll('.delivery-sauce-choice'));
        const selected = checkboxes.filter(checkbox => checkbox.checked).length;
        checkboxes.forEach(checkbox => {
            checkbox.disabled = !checkbox.checked && selected >= pendingSauceProduct.maxSauces;
        });
        document.getElementById('deliverySauceCount').textContent = `${selected} de ${pendingSauceProduct.maxSauces} seleccionados`;
    }

    function confirmDeliverySauces() {
        const sauceChoices = Array.from(document.querySelectorAll('.delivery-sauce-choice:checked')).map(checkbox => checkbox.value);
        if (sauceChoices.length < 1) {
            document.getElementById('deliverySauceHint').textContent = 'Selecciona al menos un sabor para continuar.';
            return;
        }

        const product = pendingSauceProduct;
        bootstrap.Modal.getInstance(document.getElementById('deliverySauceModal')).hide();
        pendingSauceProduct = null;
        addDeliveryProduct(product.id, product.name, product.price, product.maxSauces, sauceChoices);
    }

    function removeDeliveryProduct(index) {
        deliveryCart.splice(index, 1);
        renderDeliveryCart();
    }
    
    function updateDeliveryQty(index, delta) {
        deliveryCart[index].qty += delta;
        if(deliveryCart[index].qty < 1) deliveryCart[index].qty = 1;
        renderDeliveryCart();
    }

    function renderDeliveryCart() {
        const tbody = document.getElementById('deliveryCartItems');
        let subtotal = 0;
        
        if (deliveryCart.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4 small">Seleccione productos para agregar a la orden.</td></tr>';
            document.getElementById('subtotalAmount').innerText = '0.00';
            document.getElementById('totalAmount').innerText = '0.00';
            document.getElementById('cartItemCount').innerText = '0 items';
            document.getElementById('btnSubmitDelivery').disabled = true;
            return;
        }

        tbody.innerHTML = '';
        deliveryCart.forEach((item, index) => {
            const lineTotal = item.price * item.qty;
            subtotal += lineTotal;
            
            tbody.innerHTML += `
                <tr class="border-bottom">
                    <td style="width:25px;" class="px-0">
                        <button type="button" class="btn btn-sm text-danger p-0" onclick="removeDeliveryProduct(${index})"><i class="bi bi-x-circle-fill"></i></button>
                    </td>
                    <td class="small fw-bold lh-sm text-truncate px-1" title="${item.name}">${item.name}${item.sauce_choices.length ? `<br><span class="text-danger">Sabores: ${item.sauce_choices.join(', ')}</span>` : ''}<br><span class="text-muted fw-normal" style="font-size:0.7rem;">S/${item.price.toFixed(2)}</span></td>
                    <td style="width:80px;" class="px-0">
                        <div class="input-group input-group-sm flex-nowrap">
                            <button type="button" class="btn btn-outline-secondary px-1 py-0" onclick="updateDeliveryQty(${index}, -1)">-</button>
                            <input type="text" class="form-control text-center px-0 py-0 fw-bold bg-white" value="${item.qty}" readonly>
                            <button type="button" class="btn btn-outline-primary px-1 py-0" onclick="updateDeliveryQty(${index}, 1)">+</button>
                        </div>
                        <input type="hidden" name="products[${index}][id]" value="${item.id}">
                        <input type="hidden" name="products[${index}][qty]" value="${item.qty}">
                        ${item.sauce_choices.map(sauce => `<input type="hidden" name="products[${index}][sauce_choices][]" value="${sauce}">`).join('')}
                    </td>
                    <td style="width:60px;" class="text-end fw-bold px-0 small">
                        ${lineTotal.toFixed(2)}
                    </td>
                </tr>
            `;
        });

        const fee = parseFloat(document.getElementById('delivery_fee').value) || 0;
        const total = subtotal + fee;

        document.getElementById('subtotalAmount').innerText = subtotal.toFixed(2);
        document.getElementById('totalAmount').innerText = total.toFixed(2);
        document.getElementById('cartItemCount').innerText = deliveryCart.reduce((acc, curr) => acc + curr.qty, 0) + ' items';
        document.getElementById('btnSubmitDelivery').disabled = false;
    }
</script>
@endsection
