@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-0"><i class="bi bi-grid-3x3-gap-fill me-2"></i> Diseño de Salón</h2>
            <p class="text-muted mb-0">Arrastra las mesas y guarda la distribución</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#areaModal">
                <i class="bi bi-plus-circle me-2"></i> Nueva Zona
            </button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tableModal">
                <i class="bi bi-plus-lg me-2"></i> Nueva Mesa
            </button>
            <button class="btn btn-success fw-bold px-4 shadow" onclick="savePositions()" id="btnSave">
                <i class="bi bi-save me-2"></i> Guardar Diseño
            </button>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3" id="areaTabs" role="tablist">
        @foreach($areas as $index => $area)
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $index == 0 ? 'active' : '' }} fw-bold"
                        id="tab-{{ $area->id }}"
                        data-bs-toggle="tab"
                        data-bs-target="#area-{{ $area->id }}"
                        type="button" role="tab">
                    {{ $area->name }}
                </button>
            </li>
        @endforeach
    </ul>

    <div class="tab-content" id="areaTabsContent">
        @foreach($areas as $index => $area)
            <div class="tab-pane fade {{ $index == 0 ? 'show active' : '' }}" id="area-{{ $area->id }}" role="tabpanel">

                <div class="d-flex justify-content-between align-items-center mb-2 bg-white p-2 border rounded">
                    <small class="text-muted"><i class="bi bi-info-circle me-1"></i> Arrastra las mesas y luego presiona <b>Guardar Diseño</b>.</small>
                    <div class="dropdown">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Opciones de la zona {{ $area->name }}" title="Opciones de la zona">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li>
                                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#editAreaModal{{ $area->id }}">
                                    <i class="bi bi-pencil-square text-primary me-2"></i>Editar zona
                                </button>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ $area->is_active ? route('tables.deactivateArea', $area->id) : route('tables.activateArea', $area->id) }}" method="POST" class="{{ $area->is_active ? 'deactivate-area-form' : 'activate-area-form' }}">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="dropdown-item {{ $area->is_active ? 'text-danger' : 'text-success' }}">
                                        <i class="bi {{ $area->is_active ? 'bi-eye-slash' : 'bi-eye' }} me-2"></i>
                                        {{ $area->is_active ? 'Desactivar zona' : 'Reactivar zona' }}
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="salon-canvas bg-white border rounded shadow-sm position-relative" style="height: 600px; background-image: radial-gradient(#dee2e6 1px, transparent 1px); background-size: 20px 20px; overflow: auto; max-width: 100%;">
                    @foreach($area->tables as $table)
                        <div class="draggable-table position-absolute d-flex flex-column align-items-center justify-content-center bg-white border shadow-sm rounded-3"
                             id="table-{{ $table->id }}"
                             data-id="{{ $table->id }}"
                             style="width: 100px; height: 100px;
                                    left: {{ $table->x_pos }}px;
                                    top: {{ $table->y_pos }}px;
                                    cursor: grab; z-index: 10;
                                    transition: box-shadow 0.2s;">

                            <i class="bi bi-display fs-3 {{ $table->status == 'available' ? 'text-success' : 'text-danger' }} mb-1"></i>
                            <span class="fw-bold small text-center text-truncate w-100 px-1">{{ $table->name }}</span>

                            <form action="{{ route('tables.destroyTable', $table->id) }}" method="POST" class="position-absolute top-0 end-0 m-1 delete-table-form">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm p-0 text-danger opacity-25 hover-opacity-100">
                                    <i class="bi bi-x-circle-fill"></i>
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="modal fade" id="editAreaModal{{ $area->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-sm modal-dialog-centered">
                    <form action="{{ route('tables.updateArea', $area->id) }}" method="POST" class="modal-content">
                        @csrf
                        @method('PUT')
                        <div class="modal-header bg-light">
                            <h5 class="modal-title fw-bold">Editar Zona</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <label for="area-name-{{ $area->id }}" class="form-label">Nombre de la zona</label>
                            <input id="area-name-{{ $area->id }}" type="text" name="name" class="form-control" value="{{ $area->name }}" maxlength="100" required autofocus>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Guardar cambios</button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
</div>

<div class="modal fade" id="areaModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form action="{{ route('tables.storeArea') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header bg-light"><h5 class="modal-title fw-bold">Nueva Zona</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><label>Nombre</label><input type="text" name="name" class="form-control" required></div>
            <div class="modal-footer"><button class="btn btn-primary w-100">Crear</button></div>
        </form>
    </div>
</div>
<div class="modal fade" id="tableModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form action="{{ route('tables.storeTable') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header bg-light"><h5 class="modal-title fw-bold">Nueva Mesa</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label>Nombre</label><input type="text" name="name" class="form-control" placeholder="Mesa 1" required></div>
                <div class="mb-3"><label>Zona</label><select name="area_id" class="form-select">@foreach($areas->where('is_active', true) as $area) <option value="{{ $area->id }}">{{ $area->name }}</option> @endforeach</select></div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary w-100">Crear</button></div>
        </form>
    </div>
</div>

<style>
    .draggable-table {
        touch-action: none;
        user-select: none;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const draggables = document.querySelectorAll('.draggable-table');
        let activeDrag = null;
        let initialX, initialY, currentX, currentY;

        draggables.forEach(el => el.addEventListener('pointerdown', dragStart));
        document.addEventListener('pointerup', dragEnd);
        document.addEventListener('pointermove', drag);

        document.querySelectorAll('.deactivate-area-form, .activate-area-form, .delete-table-form').forEach(form => {
            form.addEventListener('submit', function(event) {
                event.preventDefault();
                const isArea = form.classList.contains('deactivate-area-form') || form.classList.contains('activate-area-form');
                const isActivation = form.classList.contains('activate-area-form');

                Swal.fire({
                    icon: isActivation ? 'question' : 'warning',
                    title: isActivation ? '¿Reactivar esta zona?' : (isArea ? '¿Desactivar esta zona?' : '¿Borrar esta mesa?'),
                    text: isActivation ? 'La zona y sus mesas volverán a estar disponibles.' : (isArea ? 'La zona y sus mesas se conservarán, pero dejarán de aparecer en el POS.' : 'Esta acción no se puede deshacer.'),
                    showCancelButton: true,
                    confirmButtonText: isActivation ? 'Sí, reactivar' : (isArea ? 'Sí, desactivar' : 'Sí, eliminar'),
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: isActivation ? '#198754' : '#dc3545',
                    cancelButtonColor: '#6c757d'
                }).then(result => {
                    if (result.isConfirmed) form.submit();
                });
            });
        });

        @if(session('success'))
            Swal.fire({
                icon: 'success',
                title: 'Mesas',
                text: @json(session('success')),
                timer: 2200,
                showConfirmButton: false
            });
        @endif

        @if(session('error'))
            Swal.fire({
                icon: 'error',
                title: 'No se pudo completar la operación',
                text: @json(session('error'))
            });
        @endif

        function dragStart(e) {
            if (e.target.closest('form')) return; // No arrastrar si clickea en borrar
            activeDrag = e.currentTarget;

            // Obtener posición actual real
            let rect = activeDrag.getBoundingClientRect();
            let parentRect = activeDrag.parentElement.getBoundingClientRect();

            // Calculamos la posición relativa al contenedor
            let styleLeft = activeDrag.offsetLeft;
            let styleTop = activeDrag.offsetTop;

            initialX = e.clientX - styleLeft;
            initialY = e.clientY - styleTop;

            activeDrag.style.cursor = 'grabbing';
            activeDrag.style.zIndex = 100;
            activeDrag.classList.add('shadow-lg');
        }

        function dragEnd() {
            if(!activeDrag) return;
            activeDrag.style.cursor = 'grab';
            activeDrag.style.zIndex = 10;
            activeDrag.classList.remove('shadow-lg');
            activeDrag = null;
        }

        function drag(e) {
            if (activeDrag) {
                e.preventDefault();
                currentX = e.clientX - initialX;
                currentY = e.clientY - initialY;

                // Límites simples (evitar que salga mucho)
                if(currentX < 0) currentX = 0;
                if(currentY < 0) currentY = 0;

                activeDrag.style.left = currentX + "px";
                activeDrag.style.top = currentY + "px";
            }
        }
    });

    function savePositions() {
        let btn = document.getElementById('btnSave');
        let originalText = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Guardando...';
        btn.disabled = true;

        let positions = [];
        document.querySelectorAll('.draggable-table').forEach(el => {
            positions.push({
                id: el.getAttribute('data-id'),
                x: parseInt(el.style.left.replace('px', '') || 0),
                y: parseInt(el.style.top.replace('px', '') || 0)
            });
        });

        fetch("{{ route('tables.updatePositions') }}", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": "{{ csrf_token() }}"
            },
            body: JSON.stringify({ positions: positions })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en el servidor: ' + response.statusText);
            }
            return response.json();
        })
        .then(data => {
            if(data.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Diseño guardado',
                    text: data.message || '¡Diseño guardado con éxito!',
                    timer: 1800,
                    showConfirmButton: false
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al guardar',
                    text: data.message || 'No se pudo guardar el diseño.'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error al guardar',
                text: 'No se pudo guardar el diseño. Detalle: ' + error.message
            });
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    }
</script>
@endsection
