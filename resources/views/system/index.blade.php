@extends('layouts.app')

@section('content')
<div class="mb-4">
    <h2 class="fw-bold text-dark mb-1"><i class="bi bi-shield-lock-fill me-2 text-primary"></i>Centro de Mantenimiento</h2>
    <p class="text-muted">Gestiona la integridad de tu información y la salud del sistema.</p>
</div>

<div class="row g-4">
    <!-- COPIA DE SEGURIDAD -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100 transition-hover">
            <div class="card-header bg-primary bg-opacity-10 border-0 pt-4 px-4 pb-0">
                <div class="bg-primary text-white rounded-4 d-flex align-items-center justify-content-center mb-3" style="width: 50px; height: 50px;">
                    <i class="bi bi-cloud-download-fill fs-3"></i>
                </div>
                <h5 class="fw-bold text-primary mb-1">Copia de Seguridad</h5>
                <p class="text-muted small">Respalda toda la base de datos.</p>
            </div>
            <div class="card-body px-4 pb-4">
                <p class="small text-muted mb-4">Genera un archivo .sql descargable que contiene toda la configuración, productos, ventas y movimientos. Recomendado antes de cualquier cambio mayor.</p>

                <form action="{{ route('system.backup') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2 rounded-3">
                        <i class="bi bi-download me-2"></i>Descargar Backup
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- RESTAURAR SISTEMA -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100 transition-hover">
            <div class="card-header bg-warning bg-opacity-10 border-0 pt-4 px-4 pb-0">
                <div class="bg-warning text-dark rounded-4 d-flex align-items-center justify-content-center mb-3" style="width: 50px; height: 50px;">
                    <i class="bi bi-cloud-upload-fill fs-3"></i>
                </div>
                <h5 class="fw-bold text-warning-emphasis mb-1">Restaurar Sistema</h5>
                <p class="text-muted small">Cargar datos desde un respaldo.</p>
            </div>
            <div class="card-body px-4 pb-4">
                <p class="small text-muted mb-4">Sube un archivo .sql generado previamente para volver a un estado anterior. <strong>Advertencia:</strong> Esto reemplazará todos los datos actuales.</p>

                <form action="{{ route('system.restore') }}" method="POST" enctype="multipart/form-data" id="restoreSystemForm">
                    @csrf
                    <div class="mb-3">
                        <input type="file" name="backup_file" class="form-control form-control-sm border-warning border-opacity-25" accept=".sql" required>
                    </div>
                    <div class="mb-3">
                        <input type="password" name="password" class="form-control form-control-sm border-warning border-opacity-25" placeholder="Confirma tu contraseña" required>
                    </div>
                    <button type="submit" class="btn btn-warning w-100 fw-bold py-2 rounded-3 text-dark">
                        <i class="bi bi-arrow-repeat me-2"></i>Ejecutar Restauración
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- REINICIAR SISTEMA -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100 transition-hover">
            <div class="card-header bg-danger bg-opacity-10 border-0 pt-4 px-4 pb-0">
                <div class="bg-danger text-white rounded-4 d-flex align-items-center justify-content-center mb-3" style="width: 50px; height: 50px;">
                    <i class="bi bi-trash3-fill fs-3"></i>
                </div>
                <h5 class="fw-bold text-danger mb-1">Reinicio Maestro</h5>
                <p class="text-muted small">Preparar para producción.</p>
            </div>
            <div class="card-body px-4 pb-4">
                <p class="small text-muted mb-3">Limpia el sistema de datos de prueba. Se borrarán órdenes, gastos, cajas y reservas, manteniendo productos y configuración.</p>

                @if($isProduction)
                    <div class="alert alert-danger py-2 small">
                        <i class="bi bi-shield-lock-fill me-1"></i>
                        El reinicio maestro está deshabilitado en producción.
                    </div>
                @elseif($productionPrepared)
                    <div class="alert alert-success py-2 small">
                        <i class="bi bi-check-circle-fill me-1"></i>
                        El sistema ya está preparado para producción.
                    </div>
                @endif

                <div class="bg-danger bg-opacity-10 rounded-3 p-2 mb-3 border border-danger border-opacity-25">
                    <ul class="mb-0 small text-danger fw-medium list-unstyled">
                        <li><i class="bi bi-check2-circle me-2"></i>{{ $counts['orders'] }} Órdenes</li>
                        <li><i class="bi bi-check2-circle me-2"></i>{{ $counts['reservations'] }} Reservas</li>
                        <li><i class="bi bi-check2-circle me-2"></i>Stock de productos a 0</li>
                    </ul>
                </div>

                <form action="{{ route('system.reset') }}" method="POST" id="resetSystemForm">
                    @csrf
                    <div class="mb-3">
                        <input type="password" name="password" class="form-control form-control-sm border-danger border-opacity-25" placeholder="Confirma tu contraseña" required>
                    </div>
                    <button type="submit" class="btn btn-danger w-100 fw-bold py-2 rounded-3" {{ ($productionPrepared || $isProduction) ? 'disabled' : '' }}>
                        <i class="bi bi-exclamation-octagon me-2"></i>Borrar Datos de Prueba
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .transition-hover {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .transition-hover:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
    }
</style>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const confirmDangerousAction = function (form, options) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();

                Swal.fire({
                    icon: 'warning',
                    title: options.title,
                    text: options.text,
                    showCancelButton: true,
                    confirmButtonColor: options.confirmButtonColor,
                    confirmButtonText: options.confirmButtonText,
                    cancelButtonColor: '#6c757d',
                    cancelButtonText: 'Cancelar',
                    reverseButtons: true
                }).then(function (result) {
                    if (result.isConfirmed) form.submit();
                });
            });
        };

        const restoreForm = document.getElementById('restoreSystemForm');
        if (restoreForm) {
            confirmDangerousAction(restoreForm, {
                title: '¿Restaurar el sistema?',
                text: 'Se reemplazarán todos los datos actuales por el respaldo seleccionado.',
                confirmButtonColor: '#ffc107',
                confirmButtonText: 'Sí, restaurar'
            });
        }

        const resetForm = document.getElementById('resetSystemForm');
        if (resetForm) {
            confirmDangerousAction(resetForm, {
                title: '¿Reiniciar el sistema?',
                text: 'Esta acción es irreversible: se borrarán las ventas, reservas, gastos y stock.',
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Sí, reiniciar'
            });
        }
    });
</script>
@endpush
