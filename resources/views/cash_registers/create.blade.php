@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">

            <div class="card border-0 shadow-sm" style="border-radius: 16px; overflow: hidden;">
                <!-- Header -->
                <div class="bg-primary text-white p-4 text-center" style="background: linear-gradient(135deg, var(--sidebar-bg) 0%, var(--primary) 100%) !important;">
                    <div class="mb-3">
                        <div class="d-inline-flex align-items-center justify-content-center bg-white text-primary rounded-circle" style="width: 60px; height: 60px; font-size: 24px;">
                            <i class="bi bi-box-arrow-in-right" style="color: var(--primary);"></i>
                        </div>
                    </div>
                    <h4 class="fw-bold mb-1">Apertura de Caja</h4>
                    <p class="mb-0 opacity-75 small">Registra tu fondo de cambio inicial para comenzar el turno.</p>
                </div>

                <!-- Body -->
                <div class="card-body p-4 p-md-5">

                    @if(session('warning'))
                        <div class="alert alert-warning border-0 bg-warning bg-opacity-10 text-warning px-4 py-3" style="border-radius: 12px;">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('warning') }}
                        </div>
                    @endif

                    <form id="openingCashRegisterForm" action="{{ route('cash_registers.store') }}" method="POST">
                        @csrf

                        <div class="mb-4">
                            <label class="form-label text-muted fw-bold small text-uppercase">Monto Inicial (Fondo de Caja)</label>
                            <div class="input-group input-group-lg shadow-sm" style="border-radius: 12px; overflow: hidden;">
                                <span class="input-group-text bg-white border-end-0 text-muted">
                                    {{ \App\Models\Setting::where('key','currency_symbol')->value('value') ?? 'S/' }}
                                </span>
                                <input type="number" step="0.01" min="0" class="form-control border-start-0 ps-0 form-control-lg" name="opening_amount" value="0.00" required autofocus>
                            </div>
                            @error('opening_amount')
                                <small class="text-danger mt-2 d-block">{{ $message }}</small>
                            @enderror
                            <div class="form-text mt-2">Ingresa el dinero físico con el que inicias operaciones hoy (monedas y billetes para dar vuelto).</div>
                        </div>

                        <div class="d-grid mt-5">
                            <button type="submit" class="btn btn-primary btn-lg fw-bold border-0 shadow-sm" style="background: var(--primary); border-radius: 12px; padding: 14px;">
                                Abrir Turno de Caja
                            </button>
                            @if(auth()->user()->role === 'admin')
                                <a href="{{ route('dashboard') }}" class="btn btn-light btn-lg mt-3 text-muted fw-bold shadow-sm" style="border-radius: 12px; padding: 14px;">
                                    Cancelar y volver
                                </a>
                            @endif
                        </div>
                    </form>

                </div>
            </div>

        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('openingCashRegisterForm')?.addEventListener('submit', function (event) {
        event.preventDefault();

        const form = this;
        const amount = form.querySelector('[name="opening_amount"]').value;

        Swal.fire({
            icon: 'question',
            title: '¿Abrir turno de caja?',
            text: `Se registrará un fondo inicial de S/ ${Number(amount || 0).toFixed(2)}.`,
            showCancelButton: true,
            confirmButtonText: 'Sí, abrir turno',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#6b7280'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });

    @if(session('success'))
        Swal.fire({
            icon: 'success',
            title: 'Turno abierto',
            text: @json(session('success')),
            timer: 2200,
            showConfirmButton: false
        });
    @endif

    @if(session('error'))
        Swal.fire({
            icon: 'error',
            title: 'No se pudo abrir el turno',
            text: @json(session('error'))
        });
    @endif
</script>
@endpush
