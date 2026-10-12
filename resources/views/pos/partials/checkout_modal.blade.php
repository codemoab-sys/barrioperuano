<div class="modal fade" id="checkoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
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
                    <div class="col-4"><select name="document_type" id="documentType" class="form-select fw-bold" required onchange="showSelectedSeries(this)"><option value="" selected disabled>Selecciona documento</option><option value="Ticket">Ticket Nota V.</option><option value="Boleta" data-serie="{{ $availableSeries['boleta'] ?? '' }}">Boleta</option><option value="Factura" data-serie="{{ $availableSeries['factura'] ?? '' }}">Factura</option></select></div>
                </div>
                <div id="selectedSeries" class="form-text mb-3">Selecciona el documento para ver la serie.</div>
                <div class="mb-3 text-center">
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="payment_method" id="payCash" value="cash" checked onclick="toggleCashInput(true)">
                        <label class="btn btn-outline-success fw-bold" for="payCash">Efectivo</label>
                        <input type="radio" class="btn-check" name="payment_method" id="payCard" value="card" onclick="toggleCashInput(false)">
                        <label class="btn btn-outline-primary fw-bold" for="payCard">Tarjeta</label>
                        <input type="radio" class="btn-check" name="payment_method" id="payTransfer" value="transfer" onclick="toggleCashInput(false)">
                        <label class="btn btn-outline-info fw-bold" for="payTransfer">Transferencia</label>
                        <input type="radio" class="btn-check" name="payment_method" id="payYape" value="yape" onclick="toggleCashInput(false)">
                        <label class="btn btn-outline-dark fw-bold" for="payYape">Yape</label>
                        <input type="radio" class="btn-check" name="payment_method" id="payPlin" value="plin" onclick="toggleCashInput(false)">
                        <label class="btn btn-outline-warning fw-bold" for="payPlin">Plin</label>
                    </div>
                </div>
                <div id="cashInputGroup">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Recibido</label>
                        <input type="number" step="0.01" name="received_amount" id="receivedAmount" class="form-control text-center fw-bold fs-4 text-success" value="{{ number_format($order->total, 2, '.', '') }}" oninput="calculateChange()" onclick="this.select()">
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
                    <div class="col-8"><label class="fw-bold form-label" for="quick_document">DNI / RUC</label><div class="input-group"><input type="text" name="document_number" id="quick_document" class="form-control" placeholder="Ej: 12345678" inputmode="numeric" maxlength="11" autocomplete="off"><button type="button" class="btn btn-outline-info" id="quickBtnSearchDoc" title="Buscar cliente"><i class="bi bi-search"></i> Buscar</button></div><div id="quickDocFeedback" class="form-text"></div></div>
                    <div class="col-4"><label class="fw-bold form-label">Teléfono / Celular</label><input type="tel" name="phone" class="form-control" inputmode="numeric"></div>
                    <div class="col-12"><label class="fw-bold form-label">Nombre Completo *</label><input type="text" name="name" class="form-control" required placeholder="Nombre y apellidos"></div>
                    <div class="col-12"><label class="fw-bold form-label">Email</label><input type="email" name="email" class="form-control" autocomplete="off"></div>
                    <div class="col-12"><label class="fw-bold form-label">Dirección</label><input type="text" name="address" class="form-control"></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary fw-bold">Guardar</button></div>
        </form>
    </div>
</div>
