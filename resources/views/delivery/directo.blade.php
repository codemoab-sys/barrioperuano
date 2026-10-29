@extends('layouts.app')

@section('content')
<div class="dd-directo">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold mb-0"><i class="bi bi-lightning-charge-fill me-2 text-primary"></i>Delivery Directo</h4>
        <a href="{{ route('delivery.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    <form id="directoForm" method="POST" action="{{ route('delivery-direct.store') }}">
        @csrf

        <input type="hidden" name="client_id" id="clientId" value="">
        <input type="hidden" name="delivery_fee" id="deliveryFeeInput" value="0.00">

        {{-- ══════════ PASO 1: DATOS DEL CLIENTE ══════════ --}}
        <div class="card border-0 shadow-sm mb-3" id="stepClient">
            <div class="card-body p-3">
                <h6 class="fw-bold mb-3"><span class="badge bg-primary rounded-circle me-1">1</span> Datos del cliente</h6>

                <div class="mb-3">
                    <label class="form-label small fw-bold" for="clientPhone">Celular *</label>
                    <input type="tel" inputmode="numeric" maxlength="15" name="client_phone" id="clientPhone"
                           class="form-control form-control-lg" placeholder="9XX XXX XXX" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold" for="clientName">Nombre (opcional)</label>
                    <input type="text" name="client_name" id="clientName" class="form-control form-control-lg"
                           placeholder="Nombre del cliente">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold" for="clientAddress">Dirección / Referencia (opcional)</label>
                    <textarea name="address" id="clientAddress" class="form-control" rows="2"
                              placeholder="Av. ..., referencia"></textarea>
                </div>

                <button type="button" class="btn btn-primary w-100 btn-lg fw-bold" id="btnStep1">
                    <i class="bi bi-arrow-right me-1"></i> CONTINUAR
                </button>
            </div>
        </div>

        {{-- ══════════ PASO 2: PRODUCTOS ══════════ --}}
        <div id="stepProducts" class="d-none">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-bottom py-2 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0"><span class="badge bg-primary rounded-circle me-1">2</span> Productos</h6>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnEditClient">
                        <i class="bi bi-pencil"></i> Cliente
                    </button>
                </div>
                <div class="card-body p-3">
                    <div class="mb-2">
                        <input type="text" id="searchProduct" class="form-control" placeholder="Buscar producto...">
                    </div>

                    <div class="d-flex gap-2 overflow-auto pb-2 mb-2" id="categoryTabs">
                        <button type="button" class="btn btn-sm btn-primary rounded-pill flex-shrink-0" data-cat="">Todos</button>
                        @foreach($categories as $category)
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill flex-shrink-0" data-cat="{{ $category->id }}">
                                {{ $category->name }}
                            </button>
                        @endforeach
                    </div>

                    <div class="row g-2" id="productGrid">
                        @foreach($categories as $category)
                            @foreach($category->products as $product)
                                <div class="col-6 col-md-3 product-card" data-name="{{ strtolower($product->name) }}" data-cat="{{ $category->id }}">
                                    <div class="card h-100 border-0 shadow-sm" style="cursor: pointer;"
                                         onclick="addProduct({{ $product->id }}, '{{ addslashes($product->name) }}', {{ $product->price }}, {{ \App\Services\WingSauceMenu::flavorLimit($product) ?? 0 }})">
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
            </div>

            {{-- Barra inferior: carrito --}}
            <div class="sticky-bottom dd-sticky-bar">
                <div class="card border-0 shadow-lg rounded-4">
                    <div class="card-body p-2 d-flex align-items-center gap-2">
                        <div class="flex-grow-1">
                            <div class="small text-muted" id="cartItemCount">0 items</div>
                            <div class="fw-bold fs-5 lh-1">{{ $currency }}<span id="totalAmount">0.00</span></div>
                        </div>
                        <button type="button" class="btn btn-primary fw-bold px-3" id="btnOpenCart">
                            <i class="bi bi-basket me-1"></i> CARRITO
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══════════ CARRITO (offcanvas) ══════════ --}}
        <div class="offcanvas offcanvas-end" tabindex="-1" id="cartCanvas" style="max-width: 480px; width: 100%;">
            <div class="offcanvas-header border-bottom">
                <h6 class="offcanvas-title fw-bold"><i class="bi bi-basket me-2"></i>Carrito</h6>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
            </div>
            <div class="offcanvas-body p-0 d-flex flex-column">
                <div class="flex-grow-1 overflow-auto p-2">
                    <table class="table table-sm table-borderless align-middle mb-0">
                        <tbody id="cartItems">
                            <tr><td colspan="4" class="text-center text-muted py-4 small">Seleccione productos para agregar al pedido.</td></tr>
                        </tbody>
                    </table>

                    {{-- Inputs ocultos del carrito --}}
                    <div id="cartHiddenInputs"></div>

                    {{-- Línea Delivery: siempre presente y editable --}}
                    <div class="border-top p-2 mt-2">
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-bicycle text-primary"></i>
                                <span class="small fw-bold">Delivery</span>
                                <span class="badge bg-primary-subtle text-primary">seleccionado</span>
                            </div>
                            <div class="input-group input-group-sm" style="width: 130px;">
                                <span class="input-group-text">{{ $currency }}</span>
                                <input type="number" step="0.10" min="0" id="deliveryFee" class="form-control text-end fw-bold"
                                       value="0.00" oninput="onDeliveryFeeChange()">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-3 border-top bg-light flex-shrink-0">
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span>Subtotal:</span>
                        <span>{{ $currency }}<span id="subtotalAmount">0.00</span></span>
                    </div>
                    <div class="d-flex justify-content-between small text-muted mb-2">
                        <span>Delivery:</span>
                        <span>{{ $currency }}<span id="deliveryFeeAmount">0.00</span></span>
                    </div>
                    <div class="d-flex justify-content-between fw-bold fs-5 mb-3">
                        <span>TOTAL:</span>
                        <span class="text-primary">{{ $currency }}<span id="cartTotal">0.00</span></span>
                    </div>
                    <button type="button" class="btn btn-success w-100 py-2 fw-bold" id="btnPay">
                        <i class="bi bi-cash-coin me-1"></i> PAGAR
                    </button>
                </div>
            </div>
        </div>

        {{-- ══════════ COBRO (modal) ══════════ --}}
        <div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white py-2">
                        <h6 class="modal-title fw-bold">Cobrar Pedido</h6>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-3">
                        <div class="d-flex justify-content-between align-items-center bg-light rounded-3 p-3 mb-3">
                            <span class="small fw-bold text-muted">TOTAL A COBRAR</span>
                            <span class="fs-4 fw-bold text-primary">{{ $currency }}<span id="payTotal">0.00</span></span>
                        </div>

                        {{-- ¿Cobrar ahora o dejar para después? --}}
                        <div class="mb-3">
                            <label class="form-label small fw-bold">¿Cuándo se cobra?</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="payment_mode" id="payNow" value="now" checked onchange="onPaymentModeChange()">
                                <label class="btn btn-outline-success fw-bold" for="payNow"><i class="bi bi-cash-coin me-1"></i> Cobrar ahora</label>
                                <input type="radio" class="btn-check" name="payment_mode" id="payLater" value="later" onchange="onPaymentModeChange()">
                                <label class="btn btn-outline-warning fw-bold" for="payLater"><i class="bi bi-clock-history me-1"></i> Cobrar después</label>
                            </div>
                        </div>

                        <div id="payNowBlock">
                        <div class="mb-2">
                            <label class="form-label small fw-bold" for="documentType">Tipo de documento *</label>
                            <select name="document_type" id="documentType" class="form-select fw-bold" required onchange="onDocumentTypeChange()">
                                <option value="Ticket" selected>Nota de venta (Ticket)</option>
                                <option value="Boleta" data-serie="{{ $availableSeries['boleta'] ?? '' }}">Boleta</option>
                                <option value="Factura" data-serie="{{ $availableSeries['factura'] ?? '' }}">Factura</option>
                            </select>
                        </div>
                        <div id="selectedSeries" class="form-text mb-3">Este documento no utiliza serie.</div>
                        </div>


                        {{-- Cliente para boleta / factura --}}
                        <div id="clientBlock" class="mb-3 d-none">
                            <label class="form-label small fw-bold text-muted" id="clientLabel">CLIENTE</label>
                            <div class="position-relative">
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                                    <input type="text" class="form-control" id="clientSearchInput"
                                           placeholder="Buscar por nombre, DNI o RUC..." oninput="searchClient(this)"
                                           autocomplete="off" maxlength="11">
                                    <button class="btn btn-outline-success" type="button" title="Registrar nuevo cliente"
                                            onclick="openNewClientModal()"><i class="bi bi-person-plus-fill"></i></button>
                                </div>
                                <div id="clientSuggestions" class="list-group position-absolute w-100 shadow-sm"
                                     style="z-index:1050; display:none; max-height:200px; overflow-y:auto; border-radius:.5rem;"></div>
                                <div id="clientNotFound" class="form-text text-warning d-none"></div>
                            </div>
                            <div class="row g-2 mt-1">
                                <div class="col-8"><input type="text" name="client_document" id="clientDoc" class="form-control bg-light" placeholder="RUC/DNI" readonly></div>
                                <div class="col-4"><span class="d-block small text-muted pt-2" id="clientSelectedHint">Sin cliente</span></div>
                            </div>
                        </div>

                        {{-- Nota de venta: cliente varios automático --}}
                        <div id="variosBlock" class="mb-3">
                            <div class="alert alert-info py-2 small mb-0">
                                <i class="bi bi-person-check me-1"></i>
                                @if($variosData)
                                    Nota de venta: se usará automáticamente el cliente
                                    <strong>{{ $variosData['name'] }}</strong> ({{ $variosData['document'] }}).
                                @else
                                    Nota de venta: se usará el cliente <strong>Cliente - Varios</strong> ({{ \App\Services\DeliveryDirectService::VARIOS_DOCUMENT }}).
                                @endif
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Método de pago *</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="payment_method" id="payCash" value="cash" checked onclick="toggleCashInput(true)">
                                <label class="btn btn-outline-success fw-bold" for="payCash">Efectivo</label>
                                <input type="radio" class="btn-check" name="payment_method" id="payCard" value="card" onclick="toggleCashInput(false)">
                                <label class="btn btn-outline-primary fw-bold" for="payCard">Tarjeta</label>
                                <input type="radio" class="btn-check" name="payment_method" id="payTransfer" value="transfer" onclick="toggleCashInput(false)">
                                <label class="btn btn-outline-info fw-bold" for="payTransfer">Transf.</label>
                                <input type="radio" class="btn-check" name="payment_method" id="payYape" value="yape" onclick="toggleCashInput(false)">
                                <label class="btn btn-outline-dark fw-bold" for="payYape">Yape</label>
                                <input type="radio" class="btn-check" name="payment_method" id="payPlin" value="plin" onclick="toggleCashInput(false)">
                                <label class="btn btn-outline-warning fw-bold" for="payPlin">Plin</label>
                            </div>
                        </div>

                        <div id="cashInputGroup">
                            <div class="mb-2">
                                <label class="form-label small fw-bold" for="receivedAmount">Recibido</label>
                                <input type="number" step="0.01" min="0" name="received_amount" id="receivedAmount"
                                       class="form-control text-center fw-bold fs-4 text-success" oninput="calculateChange()">
                            </div>
                            <div class="d-flex justify-content-between">
                                <small>Cambio:</small>
                                <h6 class="fw-bold mb-0 text-secondary" id="changeAmount">0.00</h6>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer p-2 bg-light">
                        <button type="button" class="btn btn-success w-100 btn-lg fw-bold" id="btnConfirmPay">
                            <i class="bi bi-check-circle me-1"></i> CONFIRMAR PAGO
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Registrar cliente rápido (misma solución del POS) --}}
    </form>

    <div class="modal fade" id="quickClientModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
            <form id="quickClientForm" method="POST" action="{{ route('clients.store') }}">
                @csrf
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h6 class="modal-title fw-bold">Registrar Cliente</h6>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-8">
                                <label class="fw-bold form-label small" for="quick_document">DNI / RUC</label>
                                <div class="input-group">
                                    <input type="text" name="document_number" id="quick_document" class="form-control"
                                           placeholder="Ej: 12345678" inputmode="numeric" maxlength="11" autocomplete="off">
                                    <button type="button" class="btn btn-outline-info" id="quickBtnSearchDoc" title="Buscar cliente">
                                        <i class="bi bi-search"></i> Buscar
                                    </button>
                                </div>
                                <div id="quickDocFeedback" class="form-text"></div>
                            </div>
                            <div class="col-4">
                                <label class="fw-bold form-label small">Teléfono / Celular</label>
                                <input type="tel" name="phone" id="quick_phone" class="form-control" inputmode="numeric">
                            </div>
                            <div class="col-12">
                                <label class="fw-bold form-label small">Nombre Completo *</label>
                                <input type="text" name="name" id="quick_name" class="form-control" required placeholder="Nombre y apellidos">
                            </div>
                            <div class="col-12">
                                <label class="fw-bold form-label small">Email</label>
                                <input type="email" name="email" class="form-control" autocomplete="off">
                            </div>
                            <div class="col-12">
                                <label class="fw-bold form-label small">Dirección</label>
                                <input type="text" name="address" id="quick_address" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary fw-bold">Guardar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal de sabores (combos de alitas) --}}
<div class="modal fade" id="sauceModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <div>
                    <h6 class="modal-title fw-bold">Elige los sabores</h6>
                    <div id="sauceProduct" class="small text-dark"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p id="sauceHint" class="small text-muted mb-2"></p>
                <div id="sauceOptions" class="row g-2"></div>
                <div id="sauceCount" class="small fw-bold text-end mt-2">0 seleccionados</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning fw-bold" onclick="confirmSauces()">Agregar al pedido</button>
            </div>
        </div>
    </div>
</div>

<style>
    .dd-directo {
        max-width: 900px;
        margin: 0 auto;
    }

    .dd-sticky-bar {
        bottom: 0;
        z-index: 1030;
        padding-top: .5rem;
    }

    .dd-directo .product-card .card {
        transition: transform .15s ease, box-shadow .15s ease;
    }

    .dd-directo .product-card .card:hover {
        transform: translateY(-2px);
    }

    .dd-directo .offcanvas-body .table td {
        vertical-align: middle;
    }

    @media (max-width: 575.98px) {
        .dd-directo .card-body {
            padding: 1rem !important;
        }

        .dd-directo .form-control-lg {
            font-size: 1.05rem;
        }
    }
</style>

<script>
    let cart = [];
    let selectedClient = null;
    let pendingSauceProduct = null;

    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const currency = @json($currency);
    const igvFactor = @json($igvFactor);
    const clientsData = @json($clientsData);
    const variosClient = @json($variosData);
    const wingSauceGroups = @json(\App\Services\WingSauceMenu::groups());

    document.addEventListener('DOMContentLoaded', function () {
        const fee = document.getElementById('deliveryFee');
        if (fee) fee.value = '0.00';
        onPaymentModeChange();
    });

    /* ───────── PASO 1 ───────── */
    document.getElementById('btnStep1').addEventListener('click', function () {
        const phone = document.getElementById('clientPhone');
        if (!phone.value.trim()) {
            phone.classList.add('is-invalid');
            phone.focus();
            return;
        }
        phone.classList.remove('is-invalid');

        document.getElementById('stepClient').classList.add('d-none');
        document.getElementById('stepProducts').classList.remove('d-none');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    document.getElementById('btnEditClient').addEventListener('click', function () {
        document.getElementById('stepClient').classList.remove('d-none');
        document.getElementById('stepProducts').classList.add('d-none');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    /* ───────── BÚSQUEDA Y CATEGORÍAS ───────── */
    document.getElementById('searchProduct').addEventListener('keyup', function () {
        filterProducts(this.value.trim().toLowerCase(), currentCategory);
    });

    let currentCategory = '';
    document.getElementById('categoryTabs').addEventListener('click', function (event) {
        const button = event.target.closest('button[data-cat]');
        if (!button) return;

        currentCategory = button.getAttribute('data-cat');
        this.querySelectorAll('button').forEach(function (btn) {
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-outline-primary');
        });
        button.classList.remove('btn-outline-primary');
        button.classList.add('btn-primary');

        filterProducts(document.getElementById('searchProduct').value.trim().toLowerCase(), currentCategory);
    });

    function filterProducts(text, category) {
        document.querySelectorAll('#productGrid .product-card').forEach(function (el) {
            const name = el.getAttribute('data-name') || '';
            const cat = el.getAttribute('data-cat') || '';
            const matchesText = name.indexOf(text) !== -1;
            const matchesCat = category === '' || cat === category;
            el.style.display = (matchesText && matchesCat) ? '' : 'none';
        });
    }

    /* ───────── CARRITO ───────── */
    function addProduct(id, name, price, maxSauces, sauceChoices) {
        if (maxSauces > 0 && (sauceChoices === undefined || sauceChoices === null)) {
            showSauceSelector({ id: id, name: name, price: price, maxSauces: maxSauces });
            return;
        }

        const selected = sauceChoices || [];
        const existing = cart.find(function (item) {
            return item.id === id && JSON.stringify(item.sauce_choices) === JSON.stringify(selected);
        });

        if (existing) {
            existing.qty++;
        } else {
            cart.push({ id: id, name: name, price: price, qty: 1, sauce_choices: selected });
        }
        renderCart();
    }

    function removeItem(index) {
        cart.splice(index, 1);
        renderCart();
    }

    function updateQty(index, delta) {
        cart[index].qty += delta;
        if (cart[index].qty < 1) cart[index].qty = 1;
        renderCart();
    }

    function onDeliveryFeeChange() {
        const value = parseFloat(document.getElementById('deliveryFee').value) || 0;
        document.getElementById('deliveryFeeInput').value = value.toFixed(2);
        renderTotals();
    }

    function cartSubtotal() {
        return cart.reduce(function (acc, item) { return acc + (item.price * item.qty); }, 0);
    }

    function deliveryFeeValue() {
        return parseFloat(document.getElementById('deliveryFee').value) || 0;
    }

    // Surcharge de tarjeta (5% sobre la base gravada), igual que en el POS.
    function cardSurcharge(total) {
        return Math.round((total / (1 + igvFactor) * 0.05) * 100) / 100;
    }

    function paymentTotal() {
        const base = cartSubtotal() + deliveryFeeValue();
        const method = document.querySelector('input[name="payment_method"]:checked');

        if (method && method.value === 'card') {
            return Math.round((base + cardSurcharge(base)) * 100) / 100;
        }

        return base;
    }

    function renderTotals() {
        const subtotal = cartSubtotal();
        const fee = deliveryFeeValue();
        const total = subtotal + fee;

        document.getElementById('subtotalAmount').innerText = subtotal.toFixed(2);
        document.getElementById('deliveryFeeAmount').innerText = fee.toFixed(2);
        document.getElementById('cartTotal').innerText = total.toFixed(2);
        document.getElementById('totalAmount').innerText = total.toFixed(2);
        document.getElementById('cartItemCount').innerText =
            cart.reduce(function (acc, item) { return acc + item.qty; }, 0) + ' items';
    }

    function renderCart() {
        const tbody = document.getElementById('cartItems');
        const hidden = document.getElementById('cartHiddenInputs');
        if (hidden) hidden.innerHTML = '';

        if (cart.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4 small">Seleccione productos para agregar al pedido.</td></tr>';
            renderTotals();
            return;
        }

        tbody.innerHTML = '';
        cart.forEach(function (item, index) {
            const lineTotal = item.price * item.qty;

            tbody.innerHTML += `
                <tr class="border-bottom">
                    <td style="width:25px;" class="px-0">
                        <button type="button" class="btn btn-sm text-danger p-0" onclick="removeItem(${index})"><i class="bi bi-x-circle-fill"></i></button>
                    </td>
                    <td class="small fw-bold lh-sm text-truncate px-1" title="${item.name}">
                        ${item.name}
                        ${item.sauce_choices.length ? '<br><span class="text-danger">Sabores: ' + item.sauce_choices.join(', ') + '</span>' : ''}
                        <br><span class="text-muted fw-normal" style="font-size:0.7rem;">${currency}${item.price.toFixed(2)}</span>
                    </td>
                    <td style="width:80px;" class="px-0">
                        <div class="input-group input-group-sm flex-nowrap">
                            <button type="button" class="btn btn-outline-secondary px-1 py-0" onclick="updateQty(${index}, -1)">-</button>
                            <input type="text" class="form-control text-center px-0 py-0 fw-bold bg-white" value="${item.qty}" readonly>
                            <button type="button" class="btn btn-outline-primary px-1 py-0" onclick="updateQty(${index}, 1)">+</button>
                        </div>
                    </td>
                    <td style="width:60px;" class="text-end fw-bold px-0 small">${lineTotal.toFixed(2)}</td>
                </tr>
            `;

            if (hidden) {
                hidden.innerHTML += `<input type="hidden" name="products[${index}][id]" value="${item.id}">`;
                hidden.innerHTML += `<input type="hidden" name="products[${index}][qty]" value="${item.qty}">`;
                item.sauce_choices.forEach(function (sauce) {
                    hidden.innerHTML += `<input type="hidden" name="products[${index}][sauce_choices][]" value="${sauce}">`;
                });
            }
        });

        renderTotals();
    }

    /* ───────── SABORES ───────── */
    function showSauceSelector(product) {
        pendingSauceProduct = product;
        document.getElementById('sauceProduct').textContent = product.name;
        document.getElementById('sauceHint').textContent = 'Selecciona entre 1 y ' + product.maxSauces + ' sabores.';
        const options = document.getElementById('sauceOptions');
        options.innerHTML = '';

        Object.entries(wingSauceGroups).forEach(function (entry) {
            const group = entry[0];
            const sauces = entry[1];
            const column = document.createElement('div');
            column.className = 'col-6';
            const section = document.createElement('div');
            section.className = 'border rounded p-2 h-100';
            const heading = document.createElement('div');
            heading.className = 'small fw-bold mb-1';
            heading.textContent = group;
            section.appendChild(heading);

            sauces.forEach(function (sauce) {
                const label = document.createElement('label');
                label.className = 'form-check small mb-1';
                const checkbox = document.createElement('input');
                checkbox.className = 'form-check-input sauce-choice';
                checkbox.type = 'checkbox';
                checkbox.value = sauce;
                checkbox.addEventListener('change', updateSauceCount);
                const text = document.createElement('span');
                text.className = 'form-check-label';
                text.textContent = sauce;
                label.appendChild(checkbox, text);
                section.appendChild(label);
            });

            column.appendChild(section);
            options.appendChild(column);
        });

        updateSauceCount();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('sauceModal')).show();
    }

    function updateSauceCount() {
        const checkboxes = Array.from(document.querySelectorAll('.sauce-choice'));
        const selected = checkboxes.filter(function (checkbox) { return checkbox.checked; }).length;

        checkboxes.forEach(function (checkbox) {
            checkbox.disabled = !checkbox.checked && selected >= pendingSauceProduct.maxSauces;
        });

        document.getElementById('sauceCount').textContent = selected + ' de ' + pendingSauceProduct.maxSauces + ' seleccionados';
    }

    function confirmSauces() {
        const checkboxes = Array.from(document.querySelectorAll('.sauce-choice:checked'));
        if (checkboxes.length < 1) {
            document.getElementById('sauceHint').textContent = 'Selecciona al menos un sabor para continuar.';
            return;
        }

        const product = pendingSauceProduct;
        bootstrap.Modal.getInstance(document.getElementById('sauceModal')).hide();
        pendingSauceProduct = null;
        addProduct(product.id, product.name, product.price, product.maxSauces, checkboxes.map(function (c) { return c.value; }));
    }

    /* ───────── COBRO ───────── */
    document.getElementById('btnOpenCart').addEventListener('click', function () {
        bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('cartCanvas')).show();
    });

    document.getElementById('btnPay').addEventListener('click', function () {
        if (cart.length === 0) {
            Swal.fire({ icon: 'warning', title: 'Carrito vacío', text: 'Agrega al menos un producto.' });
            return;
        }

        const total = paymentTotal();
        document.getElementById('payTotal').innerText = total.toFixed(2);

        const received = document.getElementById('receivedAmount');
        if (received) received.value = total.toFixed(2);
        calculateChange();

        const canvas = bootstrap.Offcanvas.getInstance(document.getElementById('cartCanvas'));
        if (canvas) canvas.hide();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('paymentModal')).show();
    });

    function showSelectedSeries(select) {
        const label = document.getElementById('selectedSeries');
        if (!label || !select.value) return;

        if (select.value === 'Ticket') {
            label.innerHTML = '<span class="text-muted">Este documento no utiliza serie.</span>';
            return;
        }

        const series = select.options[select.selectedIndex].dataset.serie || 'Sin serie activa';
        label.innerHTML = '<span class="text-muted">Serie asignada:</span> <span class="badge bg-light text-dark border fw-semibold">' + series + '</span>';
    }

    /**
     * "Cobrar ahora" muestra el comprobante y el cobro; "Cobrar después" deja el
     * pedido pendiente (el comprobante se elige más tarde, en el cobro del delivery).
     */
    function onPaymentModeChange() {
        const isNow = document.getElementById('payNow').checked;

        document.getElementById('payNowBlock').classList.toggle('d-none', !isNow);
        document.getElementById('clientLabel').innerText = isNow ? 'CLIENTE' : 'CLIENTE (opcional)';

        const button = document.getElementById('btnConfirmPay');
        button.innerHTML = isNow
            ? '<i class="bi bi-check-circle me-1"></i> CONFIRMAR PAGO'
            : '<i class="bi bi-save me-1"></i> GUARDAR PARA COBRAR DESPUÉS';

        if (isNow) {
            onDocumentTypeChange();
        } else {
            // Sin comprobante todavía: el cliente es opcional (sirve para una
            // posterior boleta/factura) y no se aplica el cliente "varios".
            document.getElementById('variosBlock').classList.add('d-none');
            document.getElementById('clientBlock').classList.remove('d-none');
            document.getElementById('clientId').value = selectedClient ? selectedClient.id : '';
            document.getElementById('clientSelectedHint').innerText = selectedClient ? selectedClient.name : 'Sin cliente';
        }

        toggleCashInput(document.getElementById('payCash').checked);
    }

    function onDocumentTypeChange() {
        const select = document.getElementById('documentType');
        const isTicket = select.value === 'Ticket';

        document.getElementById('variosBlock').classList.toggle('d-none', !isTicket);
        document.getElementById('clientBlock').classList.toggle('d-none', isTicket);

        const clientId = document.getElementById('clientId');
        const search = document.getElementById('clientSearchInput');
        const doc = document.getElementById('clientDoc');
        const hint = document.getElementById('clientSelectedHint');

        if (isTicket) {
            clientId.value = variosClient ? variosClient.id : '';
            if (search) search.value = variosClient ? variosClient.name : '';
            if (doc) doc.value = variosClient ? variosClient.document : '';
            if (hint) hint.innerText = 'Cliente - Varios';
        } else {
            clientId.value = selectedClient ? selectedClient.id : '';
            if (search) search.value = selectedClient ? selectedClient.name : '';
            if (doc) doc.value = selectedClient ? selectedClient.document : '';
            if (hint) hint.innerText = selectedClient ? selectedClient.name : 'Sin cliente';
        }

        showSelectedSeries(select);
    }

    function toggleCashInput(show) {
        const isNow = document.getElementById('payNow').checked;
        const visible = show && isNow;

        document.getElementById('cashInputGroup').style.display = visible ? 'block' : 'none';
        if (!visible) {
            document.getElementById('receivedAmount').value = paymentTotal().toFixed(2);
        }
        calculateChange();
    }

    function calculateChange() {
        const total = paymentTotal();
        const received = parseFloat(document.getElementById('receivedAmount').value) || 0;
        const el = document.getElementById('changeAmount');
        if (el) el.innerText = (received - total).toFixed(2);
    }

    /* ───────── BÚSQUEDA DE CLIENTE (misma solución del POS) ───────── */
    function applyClient(client) {
        selectedClient = client;
        document.getElementById('clientId').value = client.id || '';
        document.getElementById('clientSearchInput').value = client.name || '';
        document.getElementById('clientDoc').value = client.document || '';
        document.getElementById('clientSelectedHint').innerText = client.name || 'Sin cliente';
        document.getElementById('clientNotFound').classList.add('d-none');
        document.getElementById('clientSuggestions').style.display = 'none';
    }

    function showClientNotFound() {
        const hint = document.getElementById('clientNotFound');
        if (!hint) return;
        hint.classList.remove('d-none');
        hint.innerHTML = 'El cliente no existe. <a href="#" onclick="event.preventDefault(); openNewClientModal();">Registrarlo nuevo</a> para usar en esta venta.';
    }

    function searchClient(input) {
        if (!input) return;

        const value = (input.value || '').trim();
        const box = document.getElementById('clientSuggestions');
        const hint = document.getElementById('clientNotFound');

        if (value === '') {
            selectedClient = null;
            document.getElementById('clientId').value = '';
            document.getElementById('clientDoc').value = '';
            document.getElementById('clientSelectedHint').innerText = 'Sin cliente';
            hint.classList.add('d-none');
            box.style.display = 'none';
            return;
        }

        const exact = clientsData.filter(function (c) { return c.document === value; });
        if (exact.length > 0) { applyClient(exact[0]); hint.classList.add('d-none'); return; }

        const exactName = clientsData.filter(function (c) { return c.name === value; });
        if (exactName.length > 0) { applyClient(exactName[0]); hint.classList.add('d-none'); return; }

        const query = value.toLowerCase();
        const matches = clientsData.filter(function (c) {
            return c.name.toLowerCase().indexOf(query) !== -1 || c.document.indexOf(value) !== -1;
        }).slice(0, 8);

        selectedClient = null;
        document.getElementById('clientId').value = '';
        document.getElementById('clientDoc').value = '';
        document.getElementById('clientSelectedHint').innerText = 'Sin cliente';

        if (matches.length === 0) {
            box.style.display = 'none';
            if (/^\d{8}$/.test(value) || /^\d{11}$/.test(value)) {
                showClientNotFound();
            } else {
                hint.classList.add('d-none');
            }
            return;
        }

        hint.classList.add('d-none');
        box.innerHTML = '';
        matches.forEach(function (c) {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
            item.innerHTML = '<span class="small text-truncate">' + c.name + '</span>' +
                (c.document ? '<span class="badge bg-light text-secondary border ms-2 flex-shrink-0">' + c.document + '</span>' : '');
            item.onclick = function () { applyClient(c); };
            box.appendChild(item);
        });
        box.style.display = 'block';
    }

    document.addEventListener('click', function (event) {
        const box = document.getElementById('clientSuggestions');
        if (box && !event.target.closest('#clientSearchInput') && !event.target.closest('#clientSuggestions')) {
            box.style.display = 'none';
        }
    });

    /* ───────── CLIENTE RÁPIDO (misma solución del POS) ───────── */
    function openNewClientModal() {
        document.getElementById('quickClientForm').reset();
        document.getElementById('quickDocFeedback').textContent = '';
        document.getElementById('quickDocFeedback').className = 'form-text';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('quickClientModal')).show();
    }

    document.getElementById('quickBtnSearchDoc').addEventListener('click', function () {
        const button = this;
        const feedback = document.getElementById('quickDocFeedback');
        const icon = button.querySelector('i');
        const doc = document.getElementById('quick_document').value.trim();

        if (!doc) {
            feedback.textContent = 'Ingresa un DNI o RUC.';
            feedback.className = 'form-text text-warning';
            return;
        }

        button.disabled = true;
        icon.className = 'bi bi-arrow-repeat spinner-border spinner-border-sm';
        feedback.textContent = 'Consultando en SUNAT...';
        feedback.className = 'form-text text-muted';

        window.lookupPeruvianDocument("{{ route('clients.searchDocument') }}", doc)
            .then(function (data) {
                button.disabled = false;
                icon.className = 'bi bi-search';
                feedback.textContent = 'Datos obtenidos de ' + (data.document_type || 'RENIEC/SUNAT').toUpperCase();
                feedback.className = 'form-text text-success';
                document.getElementById('quick_name').value = data.name || '';
                document.getElementById('quick_address').value = data.address || '';
            })
            .catch(function (error) {
                button.disabled = false;
                icon.className = 'bi bi-search';
                const data = error.data || {};
                const link = data.link ? ' <a href="' + data.link + '" target="_blank" rel="noopener">Consultar en SUNAT</a>' : '';
                feedback.innerHTML = data.message ? data.message + link : 'Error al consultar. Inténtalo de nuevo.';
                feedback.className = 'form-text text-warning';
            });
    });

    document.getElementById('quickClientForm').addEventListener('submit', function (event) {
        event.preventDefault();

        const form = this;
        const data = new FormData(form);

        fetch(form.action, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: data
        })
            .then(function (response) { return response.json().then(function (payload) { return { ok: response.ok, payload: payload }; }); })
            .then(function (result) {
                if (!result.ok) {
                    const message = result.payload && result.payload.message
                        ? result.payload.message
                        : 'No se pudo registrar el cliente.';
                    Swal.fire({ icon: 'warning', title: 'Cliente', text: message });
                    return;
                }

                const client = {
                    id: result.payload.id,
                    name: result.payload.name,
                    document: result.payload.document_number || ''
                };
                clientsData.push(client);
                applyClient(client);
                bootstrap.Modal.getInstance(document.getElementById('quickClientModal')).hide();
                Swal.fire({ icon: 'success', title: 'Cliente registrado', text: client.name, timer: 1500, showConfirmButton: false });
            })
            .catch(function () {
                Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo registrar el cliente.' });
            });
    });

    /* ───────── CONFIRMAR PAGO ───────── */
    document.getElementById('btnConfirmPay').addEventListener('click', function () {
        const button = this;

        if (cart.length === 0) {
            Swal.fire({ icon: 'warning', title: 'Carrito vacío', text: 'Agrega al menos un producto.' });
            return;
        }

        const isNow = document.getElementById('payNow').checked;
        const documentType = document.getElementById('documentType').value;
        const clientId = document.getElementById('clientId').value;

        if (isNow && documentType !== 'Ticket' && !clientId) {
            Swal.fire({
                icon: 'warning',
                title: 'Falta el cliente',
                text: documentType === 'Factura'
                    ? 'Para emitir Factura debes seleccionar el cliente con RUC.'
                    : 'Selecciona el cliente para la boleta.'
            });
            return;
        }

        const method = document.querySelector('input[name="payment_method"]:checked');
        if (!method) {
            Swal.fire({ icon: 'warning', title: 'Método de pago', text: 'Selecciona un método de pago.' });
            return;
        }

        const total = paymentTotal();
        if (isNow && method.value === 'cash') {
            const received = parseFloat(document.getElementById('receivedAmount').value) || 0;
            if (received < total) {
                Swal.fire({ icon: 'warning', title: 'Monto insuficiente', text: 'El monto recibido es menor al total a cobrar.' });
                return;
            }
        }

        const form = document.getElementById('directoForm');
        const payload = new FormData(form);

        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';

        fetch(form.action, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: payload
        })
            .then(function (response) { return response.json().then(function (data) { return { ok: response.ok, data: data }; }); })
            .then(function (result) {
                button.disabled = false;
                onPaymentModeChange();

                if (!result.ok) {
                    const payloadData = result.data || {};
                    let message = payloadData.message || 'No se pudo registrar el pedido.';
                    if (payloadData.errors) {
                        const first = Object.values(payloadData.errors)[0];
                        if (first && first.length) message = first[0];
                    }
                    Swal.fire({ icon: 'error', title: 'No se pudo cobrar', text: message });
                    return;
                }

                bootstrap.Modal.getInstance(document.getElementById('paymentModal')).hide();

                Swal.fire({
                    icon: 'success',
                    title: isNow ? '¡Pedido cobrado!' : '¡Pedido guardado!',
                    text: result.data.message || 'El pedido de delivery directo fue creado correctamente.',
                    showCancelButton: true,
                    confirmButtonText: '<i class="bi bi-printer me-1"></i> Imprimir pre-orden',
                    cancelButtonText: 'Ir al kanban',
                    reverseButtons: true,
                    allowOutsideClick: false
                }).then(function (choice) {
                    if (choice.isConfirmed && result.data.pre_order_url) {
                        window.open(result.data.pre_order_url, '_blank');
                    }
                    window.location.href = "{{ route('delivery.index') }}";
                });
            })
            .catch(function () {
                button.disabled = false;
                onPaymentModeChange();
                Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'Inténtalo de nuevo.' });
            });
    });
</script>
@endsection
