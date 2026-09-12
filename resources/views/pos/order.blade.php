@extends('layouts.app')

@section('content')
{{-- Alertas del POS (visible sobre el overlay fijo) --}}
@if(session('error'))
    <div class="pos-alert-fixed">
        <div class="alert alert-danger border-0 shadow rounded-3 d-flex align-items-center mb-0">
            <i class="bi bi-exclamation-triangle-fill fs-4 me-2 text-danger"></i>
            <div class="flex-grow-1"><strong>Error:</strong> {{ session('error') }}</div>
            <button type="button" class="btn-close ms-3" data-bs-dismiss="alert"></button>
        </div>
    </div>
@endif
@if(session('success'))
    @php $printOrderId = session('last_order_id'); @endphp
    <div class="pos-alert-fixed" style="z-index:901;">
        <div class="alert alert-success border-dismiss shadow rounded-3 d-flex align-items-center mb-0">
            <i class="bi bi-check-circle-fill fs-4 me-2 text-success"></i>
            <div class="flex-1 flex-grow-1">{{ session('success') }}</div>
            @if($printOrderId)
                @php
                    $printOrder = \App\Models\Order::find($printOrderId);
                    $printRoute = $printOrder && in_array($printOrder->document_type, ['Boleta','Factura'])
                        ? route('billing.pdf.ticket', $printOrderId)
                        : route('sales.ticket', $printOrderId);
                @endphp
                <a href="{{ $printRoute }}" target="_blank" class="btn btn-sm btn-dark ms-2">
                    <i class="bi bi-printer me-1"></i> Imprimir
                </a>
            @endif
            <button type="button" class="btn-close ms-3" data-bs-dismiss="alert"></button>
        </div>
    </div>
@endif
{{-- POS ocupa 100% del viewport independiente del layout --}}
<div id="pos-wrap" style="
    position: fixed;
    inset: 0;
    left: 220px;  /* ancho del sidebar del sistema */
    display: flex;
    flex-direction: column;
    background: #f0eef8;
    z-index: 900;
">
    {{-- TOPBAR POS --}}
    <div class="pos-topbar">
        <div style="display:flex;align-items:center;gap:12px;min-width:0;">
            <a href="{{ route('pos.index') }}" class="btn btn-outline-secondary btn-sm flex-shrink-0">
                <i class="bi bi-arrow-left"></i> <span class="d-none d-sm-inline">Volver</span>
            </a>
            <div class="min-w-0">
                <div class="fw-bold text-primary" style="font-size:.95rem;line-height:1.2;">Mesa: {{ $table->name }}</div>
                <div class="text-muted" style="font-size:.75rem;">Zona: {{ $table->area->name }}</div>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
            <button type="button" class="btn btn-outline-dark btn-sm fw-bold" data-bs-toggle="offcanvas" data-bs-target="#lastSalesOffcanvas">
                <i class="bi bi-clock-history"></i> <span class="d-none d-md-inline">Últimas Ventas</span>
            </button>
            @if($order)
                <button type="button" class="btn btn-outline-primary btn-sm fw-bold d-none d-sm-inline-flex" data-bs-toggle="modal" data-bs-target="#moveTableModal">
                    <i class="bi bi-arrow-left-right me-1"></i> Mover Mesa
                </button>
            @endif
            <span class="badge bg-light text-dark border px-2 py-1 d-none d-sm-inline-flex" style="font-size:.78rem;">
                <i class="bi bi-person-fill me-1"></i> {{ auth()->user()->name }}
            </span>
        </div>
    </div>

    {{-- CUERPO: tres columnas --}}
    <div style="display:flex; flex:1; min-height:0; overflow:hidden;">

        {{-- Categorías --}}
        <div class="pos-categories-col">
            <div class="list-group list-group-flush">
                <button onclick="filterProducts('all')" class="list-group-item list-group-item-action active text-center py-3 category-btn" id="cat-btn-all">
                    <i class="bi bi-grid-fill d-block fs-4 mb-1"></i> Todo
                </button>
                @foreach($categories as $category)
                    <button onclick="filterProducts('cat-{{ $category->id }}')" class="list-group-item list-group-item-action text-center py-3 category-btn" id="cat-btn-{{ $category->id }}">
                        @if($category->image)
                            <img src="{{ asset('storage/'.$category->image) }}" class="rounded mb-1" width="40" height="40" style="object-fit:cover;">
                        @else
                            <i class="bi bi-tag d-block fs-4 mb-1"></i>
                        @endif
                        <span class="d-block small fw-bold lh-sm">{{ $category->name }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Productos --}}
        <div style="flex:1; min-width:0; background:#fff; overflow-y:auto; padding:12px 14px 20px;" id="products-container">
            {{-- Barra búsqueda --}}
            <div style="position:sticky;top:0;background:#fff;padding-bottom:10px;z-index:10;">
                <div class="input-group input-group-lg shadow-sm">
                    <span class="input-group-text bg-white border-end-0 text-primary"><i class="bi bi-upc-scan"></i></span>
                    <input type="text" id="barcodeInput" class="form-control border-start-0" placeholder="Escanear código..." autofocus autocomplete="off">
                </div>
            </div>

            @php
                // Paleta de colores por categoría (ciclo automático)
                $catPalette = [
                    ['bg'=>'#fff4e6','icon'=>'#ff8c00','border'=>'#ffd08a','iconClass'=>'bi-egg-fried'],
                    ['bg'=>'#f3eeff','icon'=>'#9b5de5','border'=>'#c9a8f5','iconClass'=>'bi-award-fill'],
                    ['bg'=>'#ecfeff','icon'=>'#06b6d4','border'=>'#67e8f9','iconClass'=>'bi-cup-straw'],
                    ['bg'=>'#f0fdf4','icon'=>'#22c55e','border'=>'#86efac','iconClass'=>'bi-cup-hot-fill'],
                    ['bg'=>'#fef2f2','icon'=>'#ef4444','border'=>'#fca5a5','iconClass'=>'bi-fire'],
                    ['bg'=>'#eff6ff','icon'=>'#3b82f6','border'=>'#93c5fd','iconClass'=>'bi-droplet-fill'],
                    ['bg'=>'#fdf4ff','icon'=>'#d946ef','border'=>'#f0abfc','iconClass'=>'bi-flower1'],
                    ['bg'=>'#fff7ed','icon'=>'#f97316','border'=>'#fdba74','iconClass'=>'bi-basket2-fill'],
                ];
                $catColorMap = [];
                $ci = 0;
foreach($categories as $cat) {
                    $catColorMap[$cat->id] = $catPalette[$ci % count($catPalette)];
                    $ci++;
                }
            @endphp
            <div class="row row-cols-2 row-cols-lg-3 row-cols-xl-4 g-3 pb-5">
                @foreach($categories as $category)
                    @php $pal = $catColorMap[$category->id]; @endphp
                    @foreach($category->products as $product)
                        <div class="col product-item cat-{{ $category->id }}">
                            <div class="pos-product-card" onclick="addToOrder({{ $product->id }})" style="--pal-bg:{{ $pal['bg'] }};--pal-icon:{{ $pal['icon'] }};--pal-border:{{ $pal['border'] }};">

                                {{-- Imagen o bloque de color con ícono --}}
                                <div class="pos-product-img-wrap">
                                    @if($product->image)
                                        <img src="{{ asset('storage/'.$product->image) }}" class="pos-product-img" alt="{{ $product->name }}">
                                    @else
                                        <div class="pos-product-icon-block">
                                            <i class="bi {{ $pal['iconClass'] }} pos-product-icon"></i>
                                        </div>
                                    @endif

                                    {{-- Badge Precio --}}
                                    <div class="pos-price-badge">
                                        {{ $currency ?? 'S/' }}{{ number_format($product->price, 2) }}
                                    </div>

                                    {{-- Badge Stock --}}
                                    @if(!is_null($product->stock))
                                        @php $availableStock = $product->stock - ($reservedMap[$product->id] ?? 0); @endphp
                                        <div class="pos-stock-badge {{ $availableStock <= 5 ? 'pos-stock-low' : 'pos-stock-ok' }}">
                                            <i class="bi bi-box-seam me-1"></i>{{ $availableStock }}
                                        </div>
                                    @endif
                                </div>

                                {{-- Nombre --}}
                                <div class="pos-product-footer">
                                    <div class="pos-product-name">{{ $product->name }}</div>
                                    <div class="pos-product-cat">{{ $category->name }}</div>
                                </div>

                                {{-- Overlay al hover --}}
                                <div class="pos-add-overlay">
                                    <i class="bi bi-plus-circle-fill"></i>
                                    <span>Agregar</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>

        {{-- CARRITO --}}
        <div class="pos-cart-col">
            <div style="padding: 14px 16px; background: #f8f7ff; border-bottom: 1px solid #e5e7eb; flex-shrink: 0; display:flex; align-items:center; justify-content:space-between;">
                <h6 class="fw-bold mb-0"><i class="bi bi-cart"></i> Cuenta Actual</h6>
                <button type="button" class="btn btn-sm btn-outline-primary d-md-none fw-bold" onclick="toggleMobileCart()">
                    <i class="bi bi-eye-fill"></i> Ver
                </button>
            </div>
            <div id="cart-container" style="flex: 1; display: flex; flex-direction: column; min-height: 0; overflow-y: auto;">
                @include('pos.partials.cart', ['order' => $order])
            </div>
        </div>
    </div>
</div>


{{-- Últimas Ventas: panel deslizante --}}
<div class="offcanvas offcanvas-end" tabindex="-1" id="lastSalesOffcanvas" aria-labelledby="lastSalesLabel" style="width: 380px;">
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
                        <span class="fw-bold fs-6 text-success">{{ $currency }} {{ number_format($sale->total, 2) }}</span>
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


<div class="modal fade" id="noteModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2 bg-warning">
                <h6 class="modal-title fw-bold text-dark">Nota Cocina</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="noteDetailId">
                <textarea id="noteText" class="form-control" rows="3"></textarea>
            </div>
            <div class="modal-footer p-1">
                <button type="button" class="btn btn-warning w-100 btn-sm text-dark fw-bold" onclick="saveNote()">Guardar Nota</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="moveTableModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2 bg-info text-white">
                <h6 class="modal-title fw-bold">Mover Mesa</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            @if($order)
                <form action="{{ route('pos.move', $order->id) }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <label class="form-label small text-muted">Destino:</label>
                        <select name="target_table_id" class="form-select" required>
                            <option value="" selected disabled>-- Elegir Mesa --</option>
                            @foreach($freeTables as $ft)
                                <option value="{{ $ft->id }}">{{ $ft->name }} ({{ $ft->area->name }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="modal-footer p-1">
                        <button type="submit" class="btn btn-info w-100 btn-sm text-white fw-bold">Confirmar</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>

<div class="modal fade" id="optionsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-2">
                <h6 class="modal-title fw-bold">Ajustes</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">Descuento Global</label>
                    <input type="number" step="0.01" id="inputDiscount" class="form-control" value="{{ $order ? $order->discount : 0 }}" onclick="this.select()">
                </div>
            </div>
            <div class="modal-footer p-1">
                <button type="button" class="btn btn-primary w-100 btn-sm fw-bold" onclick="applyOptions()">Aplicar Cambios</button>
            </div>
        </div>
    </div>
</div>

{{-- Selección cuando hay varias coincidencias al buscar --}}
<div class="modal fade" id="productPickModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title fw-bold">Varias coincidencias</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-2">
                <div id="productPickList" class="list-group"></div>
            </div>
        </div>
    </div>
</div>

<div id="checkout-modal-container">
@if($order)
<div class="modal fade" id="checkoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('pos.checkout', $order->id) }}" method="POST" id="checkoutForm" class="modal-content border-0 shadow-lg">
            @csrf
            <div class="modal-header bg-success text-white py-2">
                <h6 class="modal-title fw-bold">Cobrar Venta</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted">CLIENTE</label>
                    <div class="position-relative">
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" id="clientSearchInput" placeholder="Buscar por nombre, DNI o RUC..." oninput="searchClient(this)" autocomplete="off" maxlength="11">
                            <button class="btn btn-light border" type="button" onclick="document.getElementById('clientSearchInput').value=''; searchClient({value:''})"><i class="bi bi-x"></i></button>
                            <button class="btn btn-outline-success" type="button" title="Registrar nuevo cliente" onclick="openNewClientModal()"><i class="bi bi-person-plus-fill"></i></button>
                        </div>
                        <div id="clientSuggestions" class="list-group position-absolute w-100 shadow-sm" style="z-index:1050; display:none; max-height:230px; overflow-y:auto; border-radius:.5rem;"></div>
                        <div id="clientNotFound" class="form-text text-warning d-none"></div>
                        <input type="hidden" name="client_id" id="clientId">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-8"><input type="text" name="client_document" id="clientDoc" class="form-control bg-light" placeholder="RUC/DNI" readonly></div>
                    <div class="col-4"><select name="document_type" class="form-select fw-bold" required><option value="" selected disabled>Selecciona documento</option><option value="Ticket">Ticket</option><option value="Boleta">Boleta</option><option value="Factura">Factura</option></select></div>
                </div>
                <div class="mb-3 text-center">
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="payment_method" id="payCash" value="cash" checked onclick="toggleCashInput(true)">
                        <label class="btn btn-outline-success fw-bold" for="payCash">Efectivo</label>
                        <input type="radio" class="btn-check" name="payment_method" id="payCard" value="card" onclick="toggleCashInput(false)">
                        <label class="btn btn-outline-primary fw-bold" for="payCard">Tarjeta</label>
                        <input type="radio" class="btn-check" name="payment_method" id="payTransfer" value="transfer" onclick="toggleCashInput(false)">
                        <label class="btn btn-outline-info fw-bold" for="payTransfer">Transferencia</label>
                    </div>
                </div>
                <div id="cashInputGroup">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Recibido</label>
                        <input type="number" step="0.01" name="received_amount" id="receivedAmount" class="form-control text-center fw-bold fs-4 text-success"
                               value="{{ number_format($order->total, 2, '.', '') }}"
                               oninput="calculateChange()" onclick="this.select()">
                    </div>
                    <div class="d-flex justify-content-between">
                        <small>Cambio:</small>
                        <h4 class="fw-bold mb-0 text-secondary" id="changeAmount">0.00</h4>
                    </div>
                </div>
                <input type="hidden" id="hiddenTotal" value="{{ number_format($order->total, 2, '.', '') }}">
            </div>
            <div class="modal-footer p-2 bg-light">
                <button type="button" id="confirmPayBtn" class="btn btn-success w-100 btn-lg fw-bold">CONFIRMAR PAGO</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="quickClientModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="quickClientForm" method="POST" action="{{ route('clients.store') }}" class="modal-content">
            @csrf
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">Registrar Cliente</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-8">
                        <label class="fw-bold form-label" for="quick_document">DNI / RUC</label>
                        <div class="input-group">
                            <input type="text" name="document_number" id="quick_document" class="form-control" placeholder="Ej: 12345678" inputmode="numeric" maxlength="11" autocomplete="off">
                            <button type="button" class="btn btn-outline-info" id="quickBtnSearchDoc" title="Buscar en SUNAT">
                                <i class="bi bi-search"></i> Buscar
                            </button>
                        </div>
                        <div id="quickDocFeedback" class="form-text"></div>
                    </div>
                    <div class="col-4">
                        <label class="fw-bold form-label">Teléfono / Celular</label>
                        <input type="tel" name="phone" class="form-control" inputmode="numeric">
                    </div>
                    <div class="col-12">
                        <label class="fw-bold form-label">Nombre Completo *</label>
                        <input type="text" name="name" class="form-control" required placeholder="Nombre y apellidos">
                    </div>
                    <div class="col-12">
                        <label class="fw-bold form-label">Email</label>
                        <input type="email" name="email" class="form-control" autocomplete="off">
                    </div>
                    <div class="col-12">
                        <label class="fw-bold form-label">Dirección</label>
                        <input type="text" name="address" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary fw-bold">Guardar</button>
            </div>
        </form>
    </div>
</div>
@endif
</div>

<script>
    const tableId = {{ $table->id }};
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const currencySymbol = "{{ $currency }}";

    // Escáner
    const barcodeInput = document.getElementById('barcodeInput');
    if(barcodeInput) {
        setTimeout(() => {
            const activeElement = document.activeElement;
            if (!activeElement || !activeElement.matches('input, textarea, select')) {
                barcodeInput.focus();
            }
        }, 150);

        barcodeInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                let code = barcodeInput.value.trim();
                if(code.length > 0) addByBarcode(code);
            }
        });
    }

    // AJAX
    window.addByBarcode = function(code) {
        fetch(`{{ url('/pos/order') }}/${tableId}/barcode`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ barcode: code })
        }).then(r => r.text()).then(html => {
            // Respuesta JSON: múltiples coincidencias o error (no toca el carrito)
            if(html.trim().startsWith('{')) {
                try {
                    var data = JSON.parse(html);
                    if(data.matches) {
                        showProductPick(data.matches);
                    } else if(data.error) {
                        Swal.fire({ icon: 'warning', title: 'Stock insuficiente', text: data.error, confirmButtonColor: '#0d6efd' });
                    }
                    barcodeInput.value = ''; barcodeInput.focus();
                    return;
                } catch(e) {}
            }
            document.getElementById('cart-container').innerHTML = html;
            barcodeInput.value = ''; barcodeInput.focus();
            updateCheckoutTotal();
        });
    };

    window.addByProductId = function(productId) {
        fetch(`{{ url('/pos/order') }}/${tableId}/add`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ product_id: productId })
        }).then(r => r.text()).then(html => {
            if(html.trim().startsWith('{')) {
                try {
                    var data = JSON.parse(html);
                    if(data.error) {
                        Swal.fire({ icon: 'warning', title: 'Stock insuficiente', text: data.error, confirmButtonColor: '#0d6efd' });
                        return;
                    }
                } catch(e) {}
            }
            document.getElementById('cart-container').innerHTML = html;
            updateCheckoutTotal();
        });
    };

    window.showProductPick = function(matches) {
        var list = document.getElementById('productPickList');
        list.innerHTML = '';
        matches.forEach(function(p) {
            var it = document.createElement('button');
            it.type = 'button';
            it.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2';
            it.innerHTML = '<span class="fw-bold text-start">' + p.name + '</span>' +
                '<span class="text-success fw-bold ms-2">' + currencySymbol + ' ' + Number(p.price).toFixed(2) + '</span>';
            it.onclick = function() {
                bootstrap.Modal.getInstance(document.getElementById('productPickModal')).hide();
                addByProductId(p.id);
            };
            list.appendChild(it);
        });
        new bootstrap.Modal(document.getElementById('productPickModal')).show();
    };

    window.addToOrder = function(productId) {
        fetch(`{{ url('/pos/order') }}/${tableId}/add`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ product_id: productId })
        }).then(r => r.text()).then(html => {
            if(html.trim().startsWith('{')) {
                try {
                    var data = JSON.parse(html);
                    if(data.error) {
                        Swal.fire({ icon: 'warning', title: 'Stock insuficiente', text: data.error, confirmButtonColor: '#0d6efd' });
                        return;
                    }
                } catch(e) {}
            }
            document.getElementById('cart-container').innerHTML = html;
            updateCheckoutTotal();
            ensureCheckoutModal();
        });
    };

    window.ensureCheckoutModal = function() {
        if (document.getElementById('checkoutModal')) return;
        var cartOrder = document.querySelector('#cart-container [data-order-id]');
        var orderId = cartOrder ? cartOrder.getAttribute('data-order-id') : '';
        if (!orderId) return;

        fetch(`{{ url('/pos/order') }}/${orderId}/checkout-modal`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(response => response.text())
            .then(html => {
                document.getElementById('checkout-modal-container').insertAdjacentHTML('beforeend', html);
                initializeCheckoutForm();
            });
    };

    window.updateQty = function(id, qty) {
        if(qty < 1 && !confirm('¿Eliminar producto?')) return;
        fetch(`{{ url('/pos/detail') }}/${id}/update`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ quantity: qty })
        }).then(r => r.text()).then(html => {
            if(html.trim().startsWith('{')) {
                try {
                    var data = JSON.parse(html);
                    if(data.error) {
                        Swal.fire({ icon: 'warning', title: 'Stock insuficiente', text: data.error, confirmButtonColor: '#0d6efd' });
                        return;
                    }
                } catch(e) {}
            }
            document.getElementById('cart-container').innerHTML = html;
            updateCheckoutTotal();
        });
    };

    window.removeItem = function(id) {
        fetch(`{{ url('/pos/detail') }}/${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken }
        }).then(r => r.text()).then(html => {
            document.getElementById('cart-container').innerHTML = html;
            updateCheckoutTotal();
        });
    };

    window.applyOptions = function() {
        var discount = document.getElementById('inputDiscount').value;
        var modal = bootstrap.Modal.getInstance(document.getElementById('optionsModal'));
        modal.hide();

        fetch(`{{ url('/pos/order') }}/{{ $order ? $order->id : 0 }}/discount`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ discount: discount })
        }).then(r => r.text()).then(html => {
            document.getElementById('cart-container').innerHTML = html;
            updateCheckoutTotal();
        });
    };

    // Utils
    window.filterProducts = function(cat) {
        document.querySelectorAll('.category-btn').forEach(btn => btn.classList.remove('active'));
        document.getElementById(cat === 'all' ? 'cat-btn-all' : 'cat-btn-' + cat.replace('cat-', '')).classList.add('active');
        document.querySelectorAll('.product-item').forEach(item => {
            item.style.display = (cat === 'all' || item.classList.contains(cat)) ? 'block' : 'none';
        });
    };

    window.updateCheckoutTotal = function() {
        setTimeout(() => {
            var newTotal = document.getElementById('cartTotalValue') ? document.getElementById('cartTotalValue').value : 0;
            var hiddenInput = document.getElementById('hiddenTotal');
            var receivedInput = document.getElementById('receivedAmount');
            if(hiddenInput) hiddenInput.value = newTotal;
            if(receivedInput) receivedInput.value = newTotal;
        }, 500);
    };

    // Modal Notas
    var noteModalEl = document.getElementById('noteModal');
    if(noteModalEl){
        noteModalEl.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            document.getElementById('noteDetailId').value = button.getAttribute('data-detail-id');
            document.getElementById('noteText').value = button.getAttribute('data-note-content') || '';
            setTimeout(() => document.getElementById('noteText').focus(), 500);
        });
    }
    window.saveNote = function() {
        var detailId = document.getElementById('noteDetailId').value;
        var note = document.getElementById('noteText').value;
        var modal = bootstrap.Modal.getInstance(document.getElementById('noteModal'));
        modal.hide();
        fetch(`{{ url('/pos/detail') }}/${detailId}/note`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ note: note })
        }).then(r => r.text()).then(html => document.getElementById('cart-container').innerHTML = html);
    };

    // Cobro
    window.toggleCashInput = function(show) { document.getElementById('cashInputGroup').style.display = show ? 'block' : 'none'; }
    window.calculateChange = function() {
        var total = parseFloat(document.getElementById('hiddenTotal').value) || 0;
        var received = parseFloat(document.getElementById('receivedAmount').value) || 0;
        var el = document.getElementById('changeAmount');
        if(el) el.innerText = (received - total).toFixed(2);
    }
    window.clientsData = @json($clientsData);

    window.applyClient = function(c) {
        var clientSearchInput = document.getElementById('clientSearchInput');
        var clientId = document.getElementById('clientId');
        var clientDoc = document.getElementById('clientDoc');
        var clientNotFound = document.getElementById('clientNotFound');
        var clientSuggestions = document.getElementById('clientSuggestions');

        if (clientSearchInput) clientSearchInput.value = c.name || '';
        if (clientId) clientId.value = c.id || '';
        if (clientDoc) clientDoc.value = c.document || '';
        if (clientNotFound) clientNotFound.classList.add('d-none');
        if (clientSuggestions) clientSuggestions.style.display = 'none';
    };

    window.showClientNotFound = function() {
        var hint = document.getElementById('clientNotFound');
        if (!hint) return;
        hint.classList.remove('d-none');
        hint.innerHTML = 'El cliente no existe. <a href="#" onclick="event.preventDefault(); openNewClientModal();">Registrarlo nuevo</a> para usar en esta venta.';
    };

    window.searchClient = function(input) {
        if (!input || !window.clientsData) return;

        var v = (input.value || '').trim();
        var box = document.getElementById('clientSuggestions');
        var hint = document.getElementById('clientNotFound');
        var clientId = document.getElementById('clientId');
        var clientDoc = document.getElementById('clientDoc');

        if(v === '') {
            if (clientId) clientId.value='';
            if (clientDoc) clientDoc.value='';
            if(hint) hint.classList.add('d-none');
            if(box) box.style.display='none';
            return;
        }
        // Coincidencia exacta por documento
        var exact = window.clientsData.filter(function(c){ return c.document === v; });
        if(exact.length > 0) { window.applyClient(exact[0]); if(hint) hint.classList.add('d-none'); return; }
        // Coincidencia exacta por nombre
        var exactName = window.clientsData.filter(function(c){ return c.name === v; });
        if(exactName.length > 0) { window.applyClient(exactName[0]); if(hint) hint.classList.add('d-none'); return; }

        var q = v.toLowerCase();
        var matches = window.clientsData.filter(function(c){
            return c.name.toLowerCase().indexOf(q) !== -1 || c.document.indexOf(v) !== -1;
        }).slice(0, 8);

        if (clientId) clientId.value='';
        if (clientDoc) clientDoc.value='';

        if(matches.length === 0) {
            if(box) box.style.display='none';
            // Solo avisa "registrarlo nuevo" si es un DNI (8) o RUC (11) y no está en la tabla
            if(/^\d{8}$/.test(v) || /^\d{11}$/.test(v)) {
                window.showClientNotFound();
            } else if(hint) {
                hint.classList.add('d-none');
            }
            return;
        }
        if(hint) hint.classList.add('d-none');
        if (box) {
            box.innerHTML = '';
            matches.forEach(function(c) {
                var it = document.createElement('button');
                it.type = 'button';
                it.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
                it.innerHTML = '<span class="small text-truncate">' + c.name + '</span>' +
                    (c.document ? '<span class="badge bg-light text-secondary border ms-2 flex-shrink-0">' + c.document + '</span>' : '');
                it.onclick = function() { window.applyClient(c); };
                box.appendChild(it);
            });
            box.style.display = 'block';
        }
    };

    var clientSearchInputEl = document.getElementById('clientSearchInput');
    if (clientSearchInputEl) {
        window.searchClient({ value: clientSearchInputEl.value });
    }

    document.addEventListener('click', function(e) {
        var clientSuggestions = document.getElementById('clientSuggestions');
        if(clientSuggestions && !e.target.closest('#clientSearchInput') && !e.target.closest('#clientSuggestions')) {
            clientSuggestions.style.display = 'none';
        }
    });

    // ── Nuevo cliente rápido (misma lógica Módulo Clientes) ──
    window.openNewClientModal = function() {
        document.getElementById('quickClientForm').reset();
        document.getElementById('quickDocFeedback').textContent = '';
        document.getElementById('quickDocFeedback').className = 'form-text';
        new bootstrap.Modal(document.getElementById('quickClientModal')).show();
    };

    window.quickSearchDocument = function() {
        var btn = document.getElementById('quickBtnSearchDoc');
        var feedback = document.getElementById('quickDocFeedback');
        var icon = btn.querySelector('i');
        var doc = document.getElementById('quick_document').value.trim();

        if(!doc) { feedback.textContent = 'Ingresa un DNI o RUC.'; feedback.className = 'form-text text-warning'; return; }

        btn.disabled = true;
        icon.className = 'bi bi-arrow-repeat spinner-border spinner-border-sm';
        feedback.textContent = 'Consultando en SUNAT...';
        feedback.className = 'form-text text-muted';

        fetch("{{ route('clients.searchDocument') }}?document=" + encodeURIComponent(doc))
            .then(res => res.json().then(data => ({ ok: res.ok, data })))
            .then(({ ok, data }) => {
                btn.disabled = false;
                icon.className = 'bi bi-search';
                if(!ok) {
                    var link = data.link ? ' <a href="' + data.link + '" target="_blank" rel="noopener">Consultar en SUNAT</a>' : '';
                    feedback.innerHTML = data.message + link;
                    feedback.className = 'form-text text-warning';
                    return;
                }
                feedback.textContent = 'Datos obtenidos de ' + (data.document_type || 'RENIEC/SUNAT').toUpperCase();
                feedback.className = 'form-text text-success';
                document.querySelector('#quickClientForm input[name="name"]').value = data.name || '';
                document.querySelector('#quickClientForm input[name="address"]').value = data.address || '';
            })
            .catch(() => {
                btn.disabled = false;
                icon.className = 'bi bi-search';
                feedback.innerHTML = 'Error al consultar. Inténtalo de nuevo.';
                feedback.className = 'form-text text-warning';
            });
    }

    var quickBtnSearchDoc = document.getElementById('quickBtnSearchDoc');
    var quickDocumentInput = document.getElementById('quick_document');
    var quickClientForm = document.getElementById('quickClientForm');

    if (quickBtnSearchDoc) {
        quickBtnSearchDoc.addEventListener('click', window.quickSearchDocument);
    }
    if (quickDocumentInput) {
        quickDocumentInput.addEventListener('keydown', function(e) {
            if(e.key === 'Enter') { e.preventDefault(); window.quickSearchDocument(); }
        });
    }
    if (quickClientForm) {
        quickClientForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var form = e.target;
            var submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';

            fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new FormData(form)
            })
            .then(res => res.json().then(data => ({ ok: res.ok, data })))
            .then(({ ok, data }) => {
                if(!ok) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Guardar';
                    var msg = data.message || 'No se pudo registrar el cliente.';
                    var quickDocFeedback = document.getElementById('quickDocFeedback');
                    if (quickDocFeedback) {
                        quickDocFeedback.textContent = msg;
                        quickDocFeedback.className = 'form-text text-danger';
                    }
                    return;
                }
                bootstrap.Modal.getInstance(document.getElementById('quickClientModal')).hide();
                var clientSearchInput = document.getElementById('clientSearchInput');
                if (clientSearchInput) clientSearchInput.value = data.name;
                var clientId = document.getElementById('clientId');
                if (clientId) clientId.value = data.id;
                var clientDoc = document.getElementById('clientDoc');
                if (clientDoc) clientDoc.value = data.document_number || '';
                var clientNotFound = document.getElementById('clientNotFound');
                if (clientNotFound) clientNotFound.classList.add('d-none');
            })
            .catch(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Guardar';
                alert('Error al guardar el cliente.');
            });
        });
    }

    // Confirmar pago: evitar doble envío, mostrar spinner y alerta con SweetAlert2
    window.initializeCheckoutForm = function() {
    var checkoutForm = document.getElementById('checkoutForm');
    if(checkoutForm) {
        var submittingPayment = false;
        checkoutForm.addEventListener('keydown', function(e) {
            if(e.key === 'Enter') {
                e.preventDefault();
            }
        });

        checkoutForm.addEventListener('submit', function(e) {
            if(submittingPayment) {
                e.preventDefault();
                return;
            }
            submittingPayment = true;

            var btn = document.getElementById('confirmPayBtn');
            if(btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Procesando...';
            }

            e.preventDefault();

            fetch(checkoutForm.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new FormData(checkoutForm)
            })
            .then(res => res.json().then(data => ({ ok: res.ok, data })))
            .then(({ ok, data }) => {
                if(!ok) {
                    submittingPayment = false;
                    if(btn) {
                        btn.disabled = false;
                        btn.innerHTML = 'CONFIRMAR PAGO';
                    }
                    Swal.fire({
                        icon: 'error',
                        title: 'No se pudo cobrar',
                        text: data.message || 'Verifica los datos e inténtalo de nuevo.',
                        confirmButtonText: 'Entendido'
                    });
                    return;
                }

                Swal.fire({
                    icon: 'success',
                    title: '¡Venta registrada!',
                    text: data.message || 'El pago se procesó correctamente.',
                    showDenyButton: Boolean(data.print_url),
                    denyButtonText: '<i class="bi bi-printer me-1"></i> Imprimir',
                    denyButtonColor: '#212529',
                    confirmButtonText: 'Aceptar',
                    allowOutsideClick: false
                }).then((result) => {
                    if (result.isDenied && data.print_url) {
                        window.open(data.print_url, '_blank', 'noopener');
                    }
                    window.location.href = data.redirect;
                });
            })
            .catch(() => {
                submittingPayment = false;
                if(btn) {
                    btn.disabled = false;
                    btn.innerHTML = 'CONFIRMAR PAGO';
                }
                Swal.fire({
                    icon: 'error',
                    title: 'Error de conexión',
                    text: 'No se pudo procesar el pago. Inténtalo de nuevo.',
                    confirmButtonText: 'Entendido'
                });
            });
        });

        document.getElementById('confirmPayBtn').addEventListener('click', function() {
            if (checkoutForm.reportValidity()) {
                checkoutForm.requestSubmit();
            }
        });
    }
    };

    initializeCheckoutForm();
</script>

<style>
    /* ── POS Alerts ──────────────────────────────────────────────── */
    .pos-alert-fixed {
        position: fixed;
        top: 12px;
        right: 20px;
        left: 240px;
        z-index: 1500;
    }

    /* ── POS Topbar ──────────────────────────────────────────────── */
    .pos-topbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #fff;
        border-bottom: 1px solid #e5e7eb;
        padding: 10px 20px;
        flex-shrink: 0;
        box-shadow: 0 2px 8px rgba(45,27,94,0.06);
    }

    /* ── POS Categories Column ───────────────────────────────────── */
    .pos-categories-col {
        width: 155px;
        flex-shrink: 0;
        background: #f8f7ff;
        border-right: 1px solid #e5e7eb;
        overflow-y: auto;
    }

    /* ── POS Cart Column ─────────────────────────────────────────── */
    .pos-cart-col {
        width: 280px;
        flex-shrink: 0;
        background: #fff;
        border-left: 1px solid #e5e7eb;
        display: flex;
        flex-direction: column;
        height: 100%;
        box-shadow: -4px 0 20px rgba(45,27,94,0.07);
    }

    /* ── POS Product Cards ─────────────────────────────────────── */
    .pos-product-card {
        position: relative;
        border-radius: 16px;
        border: 2px solid var(--pal-border);
        background: #fff;
        cursor: pointer;
        overflow: hidden;
        transition: transform .18s, box-shadow .18s, border-color .18s;
        box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        height: 100%;
        display: flex;
        flex-direction: column;
        user-select: none;
    }
    .pos-product-card:hover {
        transform: translateY(-4px) scale(1.02);
        box-shadow: 0 10px 28px rgba(0,0,0,0.14);
        border-color: var(--pal-icon);
    }
    .pos-product-card:active {
        transform: scale(0.97);
        box-shadow: 0 2px 8px rgba(0,0,0,0.10);
    }

    /* Image / icon block */
    .pos-product-img-wrap {
        position: relative;
        width: 100%;
        height: 115px;
        flex-shrink: 0;
    }
    .pos-product-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .pos-product-icon-block {
        width: 100%;
        height: 100%;
        background: var(--pal-bg);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .pos-product-icon {
        font-size: 2.6rem;
        color: var(--pal-icon);
        opacity: .85;
    }

    /* Price badge */
    .pos-price-badge {
        position: absolute;
        top: 8px;
        right: 8px;
        background: var(--pal-icon);
        color: #fff;
        font-size: .72rem;
        font-weight: 700;
        padding: 3px 9px;
        border-radius: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.18);
        letter-spacing: .02em;
    }

    /* Stock badge */
    .pos-stock-badge {
        position: absolute;
        bottom: 8px;
        left: 8px;
        font-size: .67rem;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 20px;
        border: 2px solid #fff;
        box-shadow: 0 1px 6px rgba(0,0,0,0.15);
    }
    .pos-stock-ok  { background: #22c55e; color: #fff; }
    .pos-stock-low { background: #ef4444; color: #fff; animation: pulse-red 1.2s infinite; }
    @keyframes pulse-red {
        0%,100% { opacity: 1; } 50% { opacity: .65; }
    }

    /* Footer */
    .pos-product-footer {
        padding: 8px 10px 10px;
        text-align: center;
        border-top: 1.5px solid var(--pal-border);
        background: var(--pal-bg);
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .pos-product-name {
        font-size: .82rem;
        font-weight: 700;
        color: #1e1b3a;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.2;
    }
    .pos-product-cat {
        font-size: .65rem;
        color: var(--pal-icon);
        font-weight: 600;
        margin-top: 2px;
        opacity: .8;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Hover overlay */
    .pos-add-overlay {
        position: absolute;
        inset: 0;
        background: rgba(0,0,0,0.38);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 4px;
        opacity: 0;
        transition: opacity .18s;
        color: #fff;
        pointer-events: none;
        border-radius: 14px;
    }
    .pos-add-overlay i { font-size: 2rem; }
    .pos-add-overlay span { font-size: .8rem; font-weight: 700; letter-spacing: .06em; }
    .pos-product-card:hover .pos-add-overlay { opacity: 1; }

    /* ── MOBILE: ≤900px ──────────────────────────────────────────── */
    @media (max-width: 900px) {
        .pos-alert-fixed {
            left: 12px !important;
            right: 12px !important;
        }

        #pos-wrap {
            position: absolute !important;
            inset: 0 !important;
            left: 0 !important;
            width: 100% !important;
            min-height: 100vh;
        }

        #pos-wrap > div:nth-child(2) {
            flex-direction: column !important;
            overflow-y: auto !important;
        }

        .pos-topbar {
            flex-wrap: wrap;
            gap: 8px;
            padding: 8px 12px;
        }

        .pos-categories-col {
            width: 100% !important;
            max-width: 100% !important;
            max-height: 90px;
            border-right: 0 !important;
            border-bottom: 1px solid #e5e7eb;
            overflow-x: auto;
            overflow-y: hidden;
            flex-shrink: 0;
        }
        .pos-categories-col .list-group {
            flex-direction: row !important;
            flex-wrap: nowrap !important;
        }
        .pos-categories-col .list-group-item {
            flex: 0 0 auto !important;
            width: auto !important;
            padding: 6px 14px !important;
        }
        .pos-categories-col .list-group-item i.d-block {
            display: inline !important;
        }
        .pos-categories-col .list-group-item small,
        .pos-categories-col .list-group-item span.d-block {
            font-size: .7rem !important;
        }

        #products-container {
            width: 100%;
            flex: 1 1 auto !important;
            min-height: 0;
            overflow-y: auto !important;
            padding: 8px 10px 80px !important;
        }

        .row-cols-2 .col {
            flex: 0 0 50% !important;
            max-width: 50% !important;
        }

        .pos-product-img-wrap {
            height: 90px;
        }

        .pos-cart-col {
            position: fixed !important;
            bottom: 0;
            left: 0;
            right: 0;
            width: 100% !important;
            height: 55vh;
            max-height: 55vh;
            border-left: 0 !important;
            border-top: 2px solid #e5e7eb;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.15);
            z-index: 1000;
            transform: translateY(100%);
            transition: transform .3s ease;
        }
        .pos-cart-col.mobile-open {
            transform: translateY(0);
        }

        .pos-cart-col > div:first-child .btn {
            display: inline-flex !important;
        }

        #mobileCartFab {
            display: flex !important;
        }
    }

    /* ── TABLET: 901–1200px ─────────────────────────────────────── */
    @media (min-width: 901px) and (max-width: 1200px) {
        #pos-wrap {
            left: 0 !important;
            width: 100% !important;
        }

        .pos-categories-col {
            width: 130px !important;
        }

        .pos-cart-col {
            width: 240px !important;
        }
    }
</style>

{{-- Mobile FAB: Ver Carrito --}}
<div id="mobileCartFab" style="display:none; position:fixed; bottom:20px; right:20px; z-index:1100;">
    <button type="button" class="btn btn-primary shadow-lg fw-bold d-flex align-items-center gap-2 rounded-pill px-4 py-3"
            onclick="toggleMobileCart()" id="fabCartBtn" style="font-size:1rem;">
        <i class="bi bi-cart3 fs-5"></i>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="fabCartCount" style="font-size:.7rem; display:none;">
            0
        </span>
        Ver Carrito
    </button>
</div>

<script>
    // ── Mobile cart toggle ──
    window.toggleMobileCart = function() {
        var cart = document.querySelector('.pos-cart-col');
        if (!cart) return;
        cart.classList.toggle('mobile-open');
        var fab = document.getElementById('fabCartBtn');
        if (fab) {
            if (cart.classList.contains('mobile-open')) {
                fab.innerHTML = '<i class="bi bi-x-lg fs-5"></i> Cerrar';
                fab.classList.remove('btn-primary');
                fab.classList.add('btn-danger');
            } else {
                fab.innerHTML = '<i class="bi bi-cart3 fs-5"></i> Ver Carrito <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="fabCartCount" style="font-size:.7rem;"></span>';
                fab.classList.remove('btn-danger');
                fab.classList.add('btn-primary');
                updateFabCount();
            }
        }
    };

    function updateFabCount() {
        var badge = document.getElementById('fabCartCount');
        if (!badge) return;
        var rows = document.querySelectorAll('#cart-container .table tbody tr');
        var total = 0;
        rows.forEach(function(r) {
            var input = r.querySelector('input[type="number"]');
            if (input) total += parseInt(input.value) || 0;
        });
        badge.textContent = total;
        badge.style.display = total > 0 ? 'inline' : 'none';
    }

    document.addEventListener('DOMContentLoaded', function() {
        var observer = new MutationObserver(function() { updateFabCount(); });
        var cartEl = document.getElementById('cart-container');
        if (cartEl) observer.observe(cartEl, { childList: true, subtree: true });
        updateFabCount();
    });
</script>
@endsection
