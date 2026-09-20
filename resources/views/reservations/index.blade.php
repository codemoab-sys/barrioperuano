@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-0"><i class="bi bi-calendar-check-fill me-2"></i>Reservas</h2>
            <p class="text-muted mb-0">Agenda y control de visitas futuras</p>
        </div>
        <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#createReservationModal">
            <i class="bi bi-plus-lg me-2"></i> Nueva Reserva
        </button>
    </div>

    <div class="row g-3">
        @forelse($reservations as $res)
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100 {{ $res->status == 'cancelled' ? 'opacity-50' : '' }}">
                    <div class="card-body position-relative">
                        <span class="position-absolute top-0 end-0 m-3 badge {{ $res->status == 'confirmed' ? 'bg-success' : ($res->status == 'cancelled' ? 'bg-danger' : 'bg-warning text-dark') }}">
                            {{ strtoupper($res->status == 'pending' ? 'Pendiente' : ($res->status == 'confirmed' ? 'Confirmada' : 'Cancelada')) }}
                        </span>

                        <h5 class="fw-bold text-primary mb-1">{{ $res->client_name }}</h5>
                        <div class="text-muted small mb-2"><i class="bi bi-telephone me-1"></i> {{ $res->phone ?? 'Sin teléfono' }}</div>
                        
                        <div class="d-flex align-items-center gap-3 mb-3 bg-light p-2 rounded">
                            <div class="text-center">
                                <small class="text-muted d-block" style="font-size: 0.7rem;">FECHA</small>
                                <span class="fw-bold">{{ $res->reservation_time->format('d/m') }}</span>
                            </div>
                            <div class="vr"></div>
                            <div class="text-center">
                                <small class="text-muted d-block" style="font-size: 0.7rem;">HORA</small>
                                <span class="fw-bold text-danger">{{ $res->reservation_time->format('H:i') }}</span>
                            </div>
                            <div class="vr"></div>
                            <div class="text-center">
                                <small class="text-muted d-block" style="font-size: 0.7rem;">PAX</small>
                                <span class="fw-bold">{{ $res->people }}</span>
                            </div>
                            <div class="vr"></div>
                            <div class="text-center">
                                <small class="text-muted d-block" style="font-size: 0.7rem;">MESA</small>
                                <span class="fw-bold text-primary">{{ $res->table->name ?? 'Por asignar' }}</span>
                            </div>
                        </div>

                        @if($res->note)
                            <div class="alert alert-info py-1 px-2 mb-3 small">
                                <i class="bi bi-sticky me-1"></i> {{ $res->note }}
                            </div>
                        @endif

                        <div class="d-flex gap-2 mt-2">
                            @if($res->status == 'pending')
                                <form action="{{ route('reservations.status', $res->id) }}" method="POST" class="flex-grow-1">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="status" value="confirmed">
                                    <button class="btn btn-sm btn-outline-success w-100 fw-bold"><i class="bi bi-check-lg"></i> Confirmar</button>
                                </form>
                                <form action="{{ route('reservations.status', $res->id) }}" method="POST" class="flex-grow-1">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="status" value="cancelled">
                                    <button class="btn btn-sm btn-outline-danger w-100 fw-bold"><i class="bi bi-x-lg"></i> Cancelar</button>
                                </form>
                            @else
                                <form action="{{ route('reservations.destroy', $res->id) }}" method="POST" class="w-100" onsubmit="return confirm('¿Borrar historial de esta reserva?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-light text-muted w-100">Eliminar Historial</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12 text-center py-5">
                <i class="bi bi-calendar-x fs-1 text-muted opacity-50"></i>
                <p class="mt-2 text-muted">No hay reservas próximas.</p>
            </div>
        @endforelse
    </div>
</div>

<div class="modal fade" id="createReservationModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('reservations.store') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">Nueva Reserva</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-bold">Cliente</label>
                        <div class="position-relative">
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                                <input type="text" id="resClientSearch" class="form-control" placeholder="Buscar por nombre, DNI o RUC..." oninput="resSearchClient(this)" autocomplete="off" maxlength="11">
                                <button type="button" class="btn btn-outline-success" onclick="resOpenNewClientModal()" title="Registrar nuevo cliente">
                                    <i class="bi bi-person-plus-fill"></i>
                                </button>
                            </div>
                            <div id="resClientSuggestions" class="list-group position-absolute w-100 shadow-sm" style="z-index:1050; display:none; max-height:200px; overflow-y:auto; border-radius:.5rem;"></div>
                        </div>
                        <div class="form-text text-muted" id="resClientHint">Busca un cliente conocido o regístralo nuevo si no existe.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Nombre Cliente</label>
                        <input type="text" name="client_name" id="resName" class="form-control" required placeholder="Ej: Familia Gómez">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Teléfono</label>
                        <input type="text" name="phone" id="resPhone" class="form-control" placeholder="Opcional">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Fecha y Hora</label>
                        <input type="datetime-local" name="reservation_time" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Personas</label>
                        <input type="number" name="people" class="form-control" value="2" min="1" required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-bold">Mesa (Opcional)</label>
                        <select name="table_id" class="form-select">
                            <option value="">-- Asignar al llegar --</option>
                            @foreach($tables as $table)
                                <option value="{{ $table->id }}">{{ $table->name }} (Zona: {{ $table->area->name ?? 'Gral' }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Notas / Pedidos Especiales</label>
                        <textarea name="note" class="form-control" rows="2" placeholder="Ej: Necesitan silla de bebé"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary fw-bold">Agendar</button>
            </div>
        </form>
    </div>
</div>

{{-- Registro rápido de nuevo cliente --}}
<div class="modal fade" id="resNewClientModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="resNewClientForm" action="{{ route('clients.store') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">Registrar Cliente</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-8">
                        <label class="fw-bold form-label" for="res_new_document">DNI / RUC</label>
                        <div class="input-group">
                            <input type="text" name="document_number" id="res_new_document" class="form-control" placeholder="Ej: 12345678" inputmode="numeric" maxlength="11" autocomplete="off">
                            <button type="button" class="btn btn-outline-info" id="resBtnSearchDoc" title="Buscar en SUNAT">
                                <i class="bi bi-search"></i> Buscar
                            </button>
                        </div>
                        <div id="resDocFeedback" class="form-text"></div>
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

<script>
    window.resClientsData = @json($clientsData);

    function resHideSuggestions() {
        var box = document.getElementById('resClientSuggestions');
        if(box) box.style.display = 'none';
    }

    function resApplyClient(c) {
        document.getElementById('resClientSearch').value = c.name;
        document.getElementById('resName').value = c.name;
        document.getElementById('resPhone').value = c.phone;
        document.getElementById('resClientHint').innerHTML = 'Cliente encontrado: <strong>' + c.name + '</strong>';
        resHideSuggestions();
    }

    window.resSearchClient = function(input) {
        var v = input.value.trim();
        var box = document.getElementById('resClientSuggestions');
        if(v === '') {
            document.getElementById('resClientHint').innerHTML = 'Busca un cliente conocido o regístralo nuevo si no existe.';
            if(box) box.style.display = 'none';
            return;
        }
        var exact = window.resClientsData.filter(function(c){ return c.document === v || c.name === v; });
        if(exact.length > 0) { resApplyClient(exact[0]); return; }

        var q = v.toLowerCase();
        var matches = window.resClientsData.filter(function(c){
            return c.name.toLowerCase().indexOf(q) !== -1 || c.document.indexOf(v) !== -1;
        }).slice(0, 8);

        if(matches.length === 0) {
            if(box) box.style.display = 'none';
            document.getElementById('resClientHint').innerHTML = 'No existe. <a href="#" onclick="event.preventDefault(); resOpenNewClientModal();">Registrarlo nuevo</a> o escríbelo manualmente.';
            return;
        }
        document.getElementById('resClientHint').innerHTML = '';
        box.innerHTML = '';
        matches.forEach(function(c) {
            var it = document.createElement('button');
            it.type = 'button';
            it.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
            it.innerHTML = '<span class="small text-truncate">' + c.name + '</span>' +
                (c.document ? '<span class="badge bg-light text-secondary border ms-2 flex-shrink-0">' + c.document + '</span>' : '');
            it.onclick = function() { resApplyClient(c); };
            box.appendChild(it);
        });
        box.style.display = 'block';
    };

    window.resOpenNewClientModal = function() {
        document.getElementById('resNewClientForm').reset();
        document.getElementById('resDocFeedback').textContent = '';
        document.getElementById('resDocFeedback').className = 'form-text';
        new bootstrap.Modal(document.getElementById('resNewClientModal')).show();
    };

    // Búsqueda RUC/DNI en SUNAT (misma lógica del módulo Clientes)
    function resSearchDocument() {
        var btn = document.getElementById('resBtnSearchDoc');
        var feedback = document.getElementById('resDocFeedback');
        var icon = btn.querySelector('i');
        var doc = document.getElementById('res_new_document').value.trim();

        if(!doc) { feedback.textContent = 'Ingresa un DNI o RUC.'; feedback.className = 'form-text text-warning'; return; }

        btn.disabled = true;
        icon.className = 'bi bi-arrow-repeat spinner-border spinner-border-sm';
        feedback.textContent = 'Consultando en SUNAT...';
        feedback.className = 'form-text text-muted';

        window.lookupPeruvianDocument("{{ route('clients.searchDocument') }}", doc)
            .then(data => {
                btn.disabled = false;
                icon.className = 'bi bi-search';
                feedback.textContent = 'Datos obtenidos de ' + (data.document_type || 'RENIEC/SUNAT').toUpperCase();
                feedback.className = 'form-text text-success';
                document.querySelector('#resNewClientForm input[name="name"]').value = data.name || '';
                document.querySelector('#resNewClientForm input[name="address"]').value = data.address || '';
            })
            .catch(error => {
                btn.disabled = false;
                icon.className = 'bi bi-search';
                var data = error.data || {};
                var link = data.link ? ' <a href="' + data.link + '" target="_blank" rel="noopener">Consultar en SUNAT</a>' : '';
                feedback.innerHTML = data.message ? data.message + link : 'Error al consultar. Inténtalo de nuevo.';
                feedback.className = 'form-text text-warning';
            });
    }

    document.getElementById('resBtnSearchDoc').addEventListener('click', resSearchDocument);
    document.getElementById('res_new_document').addEventListener('keydown', function(e) {
        if(e.key === 'Enter') { e.preventDefault(); resSearchDocument(); }
    });

    // Guardar el nuevo cliente vía AJAX y volcarlo a la reserva
    document.getElementById('resNewClientForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var form = e.target;
        var submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';

        fetch(form.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
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
                document.getElementById('resDocFeedback').textContent = msg;
                document.getElementById('resDocFeedback').className = 'form-text text-danger';
                return;
            }
            bootstrap.Modal.getInstance(document.getElementById('resNewClientModal')).hide();
            document.getElementById('resClientSearch').value = data.name;
            document.getElementById('resName').value = data.name;
            document.getElementById('resClientHint').innerHTML = 'Cliente creado: <strong>' + data.name + '</strong>';
            var phoneInput = form.querySelector('input[name="phone"]');
            if(phoneInput && phoneInput.value) document.getElementById('resPhone').value = phoneInput.value;
        })
        .catch(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Guardar';
            alert('Error al guardar el cliente.');
        });
    });

    document.addEventListener('click', function(e) {
        if(!e.target.closest('#resClientSearch') && !e.target.closest('#resClientSuggestions')) {
            resHideSuggestions();
        }
    });
</script>
@endsection