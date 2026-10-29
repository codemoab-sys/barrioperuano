@extends('layouts.app')

@section('content')
<div class="mb-4">
    <h2 class="fw-bold text-dark mb-1"><i class="bi bi-shield-lock-fill me-2 text-primary"></i>Centro de Mantenimiento</h2>
    <p class="text-muted">Gestiona la integridad de tu información y la salud del sistema.</p>
</div>

<div class="row g-4">
    <!-- COPIA DE SEGURIDAD -->
    <div class="col-lg-6">
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

    <!-- REINICIAR SISTEMA -->
    <div class="col-lg-6">
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

                <div class="alert alert-danger py-2 small">
                    <i class="bi bi-shield-lock-fill me-1"></i>
                    El reinicio maestro está deshabilitado permanentemente en esta instalación.
                </div>

                <button type="button" class="btn btn-danger w-100 fw-bold py-2 rounded-3" disabled>
                    <i class="bi bi-exclamation-octagon me-2"></i>Borrar Datos de Prueba
                </button>
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
