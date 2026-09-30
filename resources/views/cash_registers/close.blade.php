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
                            <i class="bi bi-box-arrow-left" style="color: var(--primary);"></i>
                        </div>
                    </div>
                    <h4 class="fw-bold mb-1">Cierre de Caja (Arqueo)</h4>
                    <p class="mb-0 opacity-75 small">Turno abierto a las: {{ $cashRegister->opening_time->format('h:i A') }}</p>
                </div>

                <!-- Body -->
                <div class="card-body p-4 p-md-5">
                    
                    <div class="mb-4 bg-light rounded p-3 text-center border">
                        <small class="text-muted text-uppercase fw-bold d-block mb-1">Monto Esperado en Sistema</small>
                        <h2 class="text-dark fw-bold mb-0">
                            {{ \App\Models\Setting::where('key','currency_symbol')->value('value') ?? 'S/' }} 
                            {{ number_format($expectedAmount, 2) }}
                        </h2>
                    </div>

                    <form action="{{ route('cash_registers.processClose') }}" method="POST">
                        @csrf
                        
                        <div class="mb-4">
                            <label class="form-label text-muted fw-bold small text-uppercase">Dinero Físico en Caja</label>
                            <div class="input-group input-group-lg shadow-sm" style="border-radius: 12px; overflow: hidden;">
                                <span class="input-group-text bg-white border-end-0 text-muted">
                                    {{ \App\Models\Setting::where('key','currency_symbol')->value('value') ?? 'S/' }}
                                </span>
                                <input type="number" step="0.01" min="0" class="form-control border-start-0 ps-0 form-control-lg" name="closing_amount" placeholder="Ej: 150.50" required autofocus>
                            </div>
                            @error('closing_amount')
                                <small class="text-danger mt-2 d-block">{{ $message }}</small>
                            @enderror
                            <div class="form-text mt-2">Cuenta todo el efectivo que tienes en el cajón de dinero e ingrésalo aquí. El sistema calculará el sobrante o faltante.</div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label text-muted fw-bold small text-uppercase">Observaciones (Opcional)</label>
                            <textarea name="notes" class="form-control" rows="2" style="border-radius: 12px;" placeholder="¿Ocurrió alguna eventualidad con el dinero?"></textarea>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg fw-bold border-0 shadow-sm" style="background: #2d1b5e; border-radius: 12px; padding: 14px;">
                                Cerrar Caja
                            </button>
                            <a href="{{ auth()->user()->role === 'admin' ? route('dashboard') : route('pos.index') }}" class="btn btn-light btn-lg mt-3 text-muted fw-bold shadow-sm" style="border-radius: 12px; padding: 14px;">
                                Cancelar
                            </a>
                        </div>
                    </form>

                </div>
            </div>

        </div>
    </div>
</div>
@endsection
