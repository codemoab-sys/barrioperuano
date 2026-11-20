@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <i class="bi bi-shield-lock text-primary fs-1"></i>
                        <h2 class="fw-bold mt-2">Actualiza tu contraseña</h2>
                        <p class="text-muted mb-0">Por seguridad, crea una nueva contraseña para continuar.</p>
                    </div>

                    @if ($errors->any())
                        <div class="alert alert-danger" role="alert">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('password.force-change.update') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="password" class="form-label fw-semibold">Nueva contraseña</label>
                            <input id="password" type="password" name="password" class="form-control"
                                minlength="6" maxlength="255" autocomplete="new-password" required autofocus>
                            <div class="form-text">Debe tener al menos 6 caracteres; puede usar letras y números.</div>
                        </div>
                        <div class="mb-4">
                            <label for="password_confirmation" class="form-label fw-semibold">Repite la contraseña</label>
                            <input id="password_confirmation" type="password" name="password_confirmation"
                                class="form-control" minlength="6" maxlength="255"
                                autocomplete="new-password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 fw-bold">Guardar contraseña</button>
                    </form>

                    <form method="POST" action="{{ route('logout') }}" class="mt-3 text-center">
                        @csrf
                        <button type="submit" class="btn btn-link text-muted">Cerrar sesión</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
