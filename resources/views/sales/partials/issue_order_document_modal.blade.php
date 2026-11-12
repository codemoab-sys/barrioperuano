<div class="modal fade" id="issueOrderDocumentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content" id="issueOrderDocumentForm"
              data-action-template="{{ url('/sales/__ORDER_ID__/documents') }}">
            @csrf
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">Emitir comprobante desde nota de venta</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    La nota de venta y sus productos, pago, caja y stock no se modificarán.
                    Total de la NV: <strong id="issueOrderTotal"></strong>
                </p>
                <div class="mb-3">
                    <label class="form-label fw-bold" for="issueDocumentType">Comprobante</label>
                    <select class="form-select" name="document_type" id="issueDocumentType" required>
                        <option value="Boleta" selected>Boleta</option>
                        <option value="Factura">Factura</option>
                    </select>
                </div>
                <div id="issueClientPicker">
                    <label class="form-label fw-bold" for="issueClientSearch">Cliente</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="issueClientSearch"
                               placeholder="Buscar por nombre, DNI o RUC" autocomplete="off">
                        <button class="btn btn-outline-secondary" type="button" id="issueClientVarious">
                            Cliente Varios
                        </button>
                        <button class="btn btn-outline-primary" type="button" id="issueOpenNewClient"
                                title="Registrar nuevo cliente">
                            <i class="bi bi-person-plus-fill"></i>
                        </button>
                    </div>
                    <div id="issueClientSuggestions" class="list-group shadow-sm mt-1"
                         style="display:none; max-height:220px; overflow-y:auto"></div>
                    <div id="issueClientSelected" class="form-text text-success">Cliente Varios</div>
                    <input type="hidden" name="client_id" id="issueClientId">
                </div>
                <div id="issueInvoiceHint" class="alert alert-info small mt-3 mb-0 d-none">
                    Para factura seleccione un cliente con RUC, razón social y dirección registrados.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary fw-bold" id="issueDocumentSubmit">
                    Emitir en NubeFact
                </button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="issueQuickClientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form id="issueQuickClientForm" method="POST" action="{{ route('clients.store') }}" class="modal-content">
            @csrf
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">Registrar cliente</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="issueQuickDocument">DNI / RUC</label>
                    <div class="input-group">
                        <input type="text" name="document_number" id="issueQuickDocument" class="form-control"
                               inputmode="numeric" maxlength="11" autocomplete="off">
                        <button type="button" class="btn btn-outline-info" id="issueQuickLookup">
                            <i class="bi bi-search"></i> Buscar
                        </button>
                    </div>
                    <div id="issueQuickLookupFeedback" class="form-text"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="issueQuickName">Nombre / razón social *</label>
                    <input type="text" name="name" id="issueQuickName" class="form-control" required maxlength="255">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="issueQuickAddress">Dirección</label>
                    <input type="text" name="address" id="issueQuickAddress" class="form-control" maxlength="255">
                </div>
                <div>
                    <label class="form-label" for="issueQuickEmail">Correo</label>
                    <input type="email" name="email" id="issueQuickEmail" class="form-control" maxlength="255">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar cliente</button>
            </div>
        </form>
    </div>
</div>

<script>
    (function () {
        const clients = @json($clientsData);
        const issueForm = document.getElementById('issueOrderDocumentForm');
        const issueModalElement = document.getElementById('issueOrderDocumentModal');
        const clientSearch = document.getElementById('issueClientSearch');
        const clientId = document.getElementById('issueClientId');
        const selectedLabel = document.getElementById('issueClientSelected');
        const suggestions = document.getElementById('issueClientSuggestions');
        const documentType = document.getElementById('issueDocumentType');
        const variousButton = document.getElementById('issueClientVarious');
        let isSubmitting = false;

        function selectVarious() {
            clientId.value = '';
            clientSearch.value = '';
            selectedLabel.textContent = 'Cliente Varios';
            suggestions.style.display = 'none';
        }

        function selectClient(client) {
            clientId.value = client.id;
            clientSearch.value = client.name;
            selectedLabel.textContent = client.name + (client.document ? ' · ' + client.document : '');
            suggestions.style.display = 'none';
        }

        function renderSuggestions(query) {
            suggestions.replaceChildren();
            const normalized = query.trim().toLowerCase();
            const matches = clients.filter(function (client) {
                return client.name.toLowerCase().includes(normalized)
                    || client.document.includes(normalized);
            }).slice(0, 8);

            if (!normalized || matches.length === 0) {
                suggestions.style.display = 'none';
                return;
            }

            matches.forEach(function (client) {
                const option = document.createElement('button');
                option.type = 'button';
                option.className = 'list-group-item list-group-item-action d-flex justify-content-between';
                const name = document.createElement('span');
                name.textContent = client.name;
                const doc = document.createElement('small');
                doc.className = 'text-muted ms-2';
                doc.textContent = client.document;
                option.append(name, doc);
                option.addEventListener('click', function () {
                    selectClient(client);
                });
                suggestions.appendChild(option);
            });
            suggestions.style.display = 'block';
        }

        function setDocumentType() {
            const isFactura = documentType.value === 'Factura';
            document.getElementById('issueInvoiceHint').classList.toggle('d-none', !isFactura);
            variousButton.disabled = isFactura;
            if (isFactura && !clientId.value) {
                selectedLabel.textContent = 'Seleccione un cliente con RUC';
            } else if (!isFactura && !clientId.value) {
                selectedLabel.textContent = 'Cliente Varios';
            }
        }

        document.querySelectorAll('[data-issue-order]').forEach(function (button) {
            button.addEventListener('click', function () {
                issueForm.action = issueForm.dataset.actionTemplate.replace('__ORDER_ID__', button.dataset.issueOrder);
                document.getElementById('issueOrderTotal').textContent = button.dataset.issueTotal;
                documentType.value = 'Boleta';
                selectVarious();
                setDocumentType();
            });
        });

        clientSearch.addEventListener('input', function () {
            clientId.value = '';
            selectedLabel.textContent = this.value.trim() ? 'Seleccione un cliente' : 'Cliente Varios';
            renderSuggestions(this.value);
        });
        variousButton.addEventListener('click', selectVarious);
        documentType.addEventListener('change', setDocumentType);
        document.addEventListener('click', function (event) {
            if (!event.target.closest('#issueClientPicker')) {
                suggestions.style.display = 'none';
            }
        });

        document.getElementById('issueOpenNewClient').addEventListener('click', function () {
            document.getElementById('issueQuickClientForm').reset();
            document.getElementById('issueQuickLookupFeedback').textContent = '';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('issueQuickClientModal')).show();
        });

        document.getElementById('issueQuickLookup').addEventListener('click', function () {
            const feedback = document.getElementById('issueQuickLookupFeedback');
            const doc = document.getElementById('issueQuickDocument').value.trim();
            feedback.textContent = 'Consultando...';
            feedback.className = 'form-text text-muted';

            window.lookupPeruvianDocument(@json(route('clients.searchDocument')), doc)
                .then(function (data) {
                    document.getElementById('issueQuickName').value = data.name || '';
                    document.getElementById('issueQuickAddress').value = data.address || '';
                    feedback.textContent = 'Datos encontrados.';
                    feedback.className = 'form-text text-success';
                })
                .catch(function (error) {
                    feedback.textContent = error.message || 'No se pudo consultar el documento.';
                    feedback.className = 'form-text text-warning';
                });
        });

        document.getElementById('issueQuickClientForm').addEventListener('submit', function (event) {
            event.preventDefault();
            const form = event.currentTarget;
            const submitButton = form.querySelector('[type="submit"]');
            submitButton.disabled = true;

            fetch(form.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form)
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        if (!response.ok) {
                            const error = new Error(data.message || Object.values(data.errors || {}).flat().join(' '));
                            error.validationErrors = data.errors || {};
                            throw error;
                        }
                        return data;
                    });
                })
                .then(function (data) {
                    const client = {
                        id: data.id,
                        name: data.name,
                        document: data.document_number || ''
                    };
                    clients.push(client);
                    selectClient(client);
                    bootstrap.Modal.getInstance(document.getElementById('issueQuickClientModal')).hide();
                })
                .catch(function (error) {
                    const documentNumber = document.getElementById('issueQuickDocument').value.replace(/\D/g, '');
                    const duplicate = (error.validationErrors?.document_number || [])
                        .some(function (message) {
                            return message.includes('Ya existe un cliente registrado');
                        });

                    if (duplicate) {
                        const existingClient = clients.find(function (client) {
                            return client.document.replace(/\D/g, '') === documentNumber;
                        });

                        if (existingClient) {
                            Swal.fire({
                                icon: 'info',
                                title: 'El cliente ya existe',
                                text: 'Al aceptar, se seleccionará para emitir el comprobante.',
                                confirmButtonText: 'Aceptar',
                                confirmButtonColor: '#0d6efd'
                            }).then(function (result) {
                                if (result.isConfirmed) {
                                    selectClient(existingClient);
                                    bootstrap.Modal.getInstance(document.getElementById('issueQuickClientModal')).hide();
                                }
                            });
                            return;
                        }
                    }

                    Swal.fire({
                        icon: 'error',
                        title: 'No se pudo registrar el cliente',
                        text: error.message || 'Inténtalo nuevamente.',
                        confirmButtonColor: '#0d6efd'
                    });
                })
                .finally(function () {
                    submitButton.disabled = false;
                });
        });

        issueForm.addEventListener('submit', function (event) {
            if (isSubmitting) {
                event.preventDefault();
                return;
            }
            if (documentType.value === 'Factura' && !clientId.value) {
                event.preventDefault();
                window.alert('Seleccione un cliente con RUC para emitir la factura.');
                return;
            }
            if (clientSearch.value.trim() && !clientId.value) {
                event.preventDefault();
                window.alert('Seleccione el cliente de la lista o pulse Cliente Varios.');
                return;
            }
            isSubmitting = true;
            document.getElementById('issueDocumentSubmit').disabled = true;
        });

        issueModalElement.addEventListener('hidden.bs.modal', function () {
            isSubmitting = false;
            document.getElementById('issueDocumentSubmit').disabled = false;
        });
    })();
</script>
