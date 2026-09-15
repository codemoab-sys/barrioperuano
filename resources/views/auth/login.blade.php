<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión — {{ \App\Models\Setting::where('key','company_name')->value('value') ?? 'Mi Restaurante' }}</title>
    @php
        $brandColors = \App\Models\Setting::whereIn('key', ['color_primary','color_primary_hover','color_primary_soft','color_sidebar_bg','brand_text_color'])->pluck('value','key')->toArray();
        $cPrimary       = $brandColors['color_primary']       ?? '#ff8c00';
        $cPrimaryHover  = $brandColors['color_primary_hover'] ?? '#e07b00';
        $cSidebar       = $brandColors['color_sidebar_bg']    ?? '#2d1b5e';
        $brandTextColor = $brandColors['brand_text_color']   ?? '#ffffff';
    @endphp
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --brand: {{ $cPrimary }};
            --brand-hover: {{ $cPrimaryHover }};
            --brand-sidebar: {{ $cSidebar }};
            --brand-text: {{ $brandTextColor }};
        }
        *:root { color-scheme: light; }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            background: #dcdcf0;
            overflow-x: hidden;
        }

        @media (max-width: 899px) {
            body { overflow-y: auto; }
        }

        /* ── LEFT PANEL: Image ── */
        .login-hero {
            flex: 1;
            position: relative;
            display: none;
            overflow: hidden;
        }

        @media (min-width: 900px) { .login-hero { display: block; } }

        .login-hero img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }

        /* Dark gradient overlay */
        .login-hero::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(
                135deg,
                rgba(20, 8, 50, 0.72) 0%,
                rgba(255, 140, 0, 0.30) 100%
            );
        }

        /* Text overlay on image */
        .hero-content {
            position: absolute;
            inset: 0;
            z-index: 2;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 48px;
            color: white;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,140,0,0.85);
            color: white;
            font-size: .75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 6px 16px;
            border-radius: 50px;
            margin-bottom: 20px;
            width: fit-content;
            backdrop-filter: blur(6px);
        }

        .hero-title {
            font-size: 2.6rem;
            font-weight: 800;
            line-height: 1.15;
            margin-bottom: 14px;
            text-shadow: 0 2px 20px rgba(0,0,0,0.4);
        }

        .hero-subtitle {
            font-size: 1rem;
            opacity: 0.85;
            line-height: 1.6;
            max-width: 380px;
            margin-bottom: 36px;
        }

        .hero-stats {
            display: flex;
            gap: 28px;
        }

        .hero-stat {
            display: flex;
            flex-direction: column;
        }

        .hero-stat strong {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--brand);
        }

        .hero-stat span {
            font-size: .72rem;
            opacity: .75;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        /* ── RIGHT PANEL: Form ── */
        .login-panel {
            width: 100%;
            max-width: 480px;
            background: var(--brand-sidebar);
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 48px 52px;
            position: relative;
            overflow: hidden;
        }

        /* Decorative circles */
        .login-panel::before {
            content: '';
            position: absolute;
            width: 300px; height: 300px;
            border-radius: 50%;
            background: rgba(255,140,0,0.08);
            top: -80px; right: -80px;
            pointer-events: none;
        }

        .login-panel::after {
            content: '';
            position: absolute;
            width: 200px; height: 200px;
            border-radius: 50%;
            background: rgba(255,140,0,0.06);
            bottom: -60px; left: -60px;
            pointer-events: none;
        }

        /* Brand */
        .login-brand {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 44px;
        }

        .login-brand.brand-rect {
            flex-direction: column;
            justify-content: center;
            text-align: center;
            gap: 0;
        }

        .login-logo {
            width: 50px; height: 50px;
            background: var(--brand);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px;
            color: white;
            box-shadow: 0 6px 20px rgba(255,140,0,0.45);
            flex-shrink: 0;
        }

        .login-brand.brand-rect .login-logo {
            width: auto;
            height: 110px;
            max-width: 260px;
            background: transparent;
            border-radius: 12px;
            box-shadow: none;
            object-fit: contain !important;
            margin-bottom: 8px;
        }

        .login-brand.brand-rect .login-brand-text { display: none; }

        .login-brand-text h1 {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--brand-text);
            letter-spacing: -0.3px;
            line-height: 1.2;
        }

        .login-brand-text p {
            font-size: .72rem;
            color: rgba(255,255,255,0.50);
            margin-top: 2px;
        }

        /* Heading */
        .login-heading {
            margin-bottom: 36px;
            text-align: center;
        }

        .login-heading h2 {
            font-size: 1.8rem;
            font-weight: 800;
            color: white;
            line-height: 1.2;
            margin-bottom: 8px;
        }

        .login-heading p {
            font-size: .85rem;
            color: rgba(255,255,255,0.55);
        }

        /* Fields */
        .field-group {
            margin-bottom: 20px;
        }

        .field-group label {
            display: block;
            font-size: .78rem;
            font-weight: 600;
            color: rgba(255,255,255,0.65);
            text-transform: uppercase;
            letter-spacing: .6px;
            margin-bottom: 8px;
        }

        .field-wrap {
            position: relative;
        }

        .field-wrap i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,0.35);
            font-size: 1rem;
            pointer-events: none;
        }

        .field-wrap input {
            width: 100%;
            background: rgba(255,255,255,0.08);
            border: 1.5px solid rgba(255,255,255,0.12);
            border-radius: 12px;
            padding: 14px 16px 14px 44px;
            font-family: 'Inter', sans-serif;
            font-size: .9rem;
            color: white;
            outline: none;
            transition: border-color .2s, background .2s;
        }

        .field-wrap input::placeholder { color: rgba(255,255,255,0.30); }

        .field-wrap input:focus {
            border-color: var(--brand);
            background: rgba(255,255,255,0.12);
        }

        /* Error message */
        .field-error {
            font-size: .75rem;
            color: #ff6b6b;
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Alert */
        .alert-error {
            background: rgba(255,107,107,0.15);
            border: 1px solid rgba(255,107,107,0.35);
            border-radius: 12px;
            padding: 12px 16px;
            color: #ff9999;
            font-size: .83rem;
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Submit button */
        .btn-login {
            width: 100%;
            background: var(--brand);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 15px;
            font-family: 'Inter', sans-serif;
            font-size: .95rem;
            font-weight: 700;
            letter-spacing: .3px;
            cursor: pointer;
            transition: background .2s, transform .15s, box-shadow .2s;
            box-shadow: 0 6px 22px rgba(255,140,0,0.40);
            margin-top: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-login:hover {
            background: var(--brand-hover);
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(255,140,0,0.50);
        }

        .btn-login:active { transform: translateY(0); }

        /* Footer */
        .login-footer {
            margin-top: 36px;
            text-align: center;
            font-size: .72rem;
            color: rgba(255,255,255,0.30);
        }

        /* Mobile full width */
        @media (max-width: 899px) {
            .login-panel {
                max-width: 100%;
                padding: 36px 28px;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>

    <!-- ── LEFT: Hero Image ── -->
    <div class="login-hero">
        {{--
            Imagen de restaurante elegante desde Unsplash (carga instantánea, sin costo).
            Foto de un restaurante lujoso con iluminación cálida.
        --}}
        <img
            src="https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=1200&q=85&auto=format&fit=crop"
            alt="Restaurante elegante"
            loading="eager"
        >

        <div class="hero-content">
            <div class="hero-badge">
                <i class="bi bi-shop"></i> Sistema de Restaurante
            </div>
            <h2 class="hero-title">Gestión integral<br>de tu restaurante</h2>
            <p class="hero-subtitle">
                Controla pedidos, mesas, cocina e inventario desde un solo lugar. Rápido, moderno y confiable.
            </p>
            <div class="hero-stats">
                <div class="hero-stat">
                    <strong>100%</strong>
                    <span>En tiempo real</span>
                </div>
                <div class="hero-stat">
                    <strong>POS</strong>
                    <span>Integrado</span>
                </div>
                <div class="hero-stat">
                    <strong>KDS</strong>
                    <span>Cocina digital</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ── RIGHT: Login Form ── -->
    <div class="login-panel">

        <!-- Brand -->
        <div class="login-brand">
            @php $logo = \App\Models\Setting::where('key','company_logo')->value('value'); @endphp
            @if($logo)
                <img src="{{ route('uploaded-assets.show', ['path' => $logo]) }}" id="loginLogo" class="login-logo" style="object-fit:cover;" alt="logo">
            @else
                <div class="login-logo"><i class="bi bi-shop"></i></div>
            @endif
            <div class="login-brand-text">
                <h1>{{ \App\Models\Setting::where('key','company_name')->value('value') ?? 'Mi Restaurante' }}</h1>
                <p>Sistema de Gestión Profesional</p>
            </div>
        </div>

        <!-- Heading -->
        <div class="login-heading">
            <h2>{{ \App\Models\Setting::where('key','welcome_message')->value('value') ?? 'Bienvenido de vuelta' }}</h2>
            <p>Ingresa tus credenciales para continuar</p>
        </div>

        <!-- Error flash -->
        @if(session('error'))
            <div class="alert-error">
                <i class="bi bi-exclamation-triangle-fill"></i>
                {{ session('error') }}
            </div>
        @endif

        <!-- Form -->
        <form action="{{ route('login.perform') }}" method="POST" novalidate>
            @csrf

            <div class="field-group">
                <label for="email">Correo Electrónico</label>
                <div class="field-wrap">
                    <i class="bi bi-envelope-fill"></i>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        placeholder="correo@restaurante.com"
                        required
                        autofocus
                    >
                </div>
                @error('email')
                    <div class="field-error"><i class="bi bi-exclamation-circle"></i> {{ $message }}</div>
                @enderror
            </div>

            <div class="field-group">
                <label for="password">Contraseña</label>
                <div class="field-wrap">
                    <i class="bi bi-lock-fill"></i>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="••••••••"
                        required
                    >
                </div>
            </div>

            <button type="submit" class="btn-login">
                <i class="bi bi-box-arrow-in-right"></i>
                Ingresar al Sistema
            </button>
        </form>

        <!-- Footer -->
        <div class="login-footer">
            &copy; {{ date('Y') }} · <a href="https://moabcode.com" target="_blank" rel="noopener" style="color: rgba(255,255,255,0.5); text-decoration: none; font-weight: 600;">Desarrollado por MoabCode</a>
        </div>
    </div>

    <script>
        const logo = document.getElementById('loginLogo');
        if (logo) {
            if (logo.complete) {
                detectBrand(logo);
            } else {
                logo.addEventListener('load', () => detectBrand(logo));
            }
        }
        function detectBrand(el) {
            if (!el.naturalWidth || !el.naturalHeight) return;
            const isMoreSquare = el.naturalHeight >= el.naturalWidth * 0.92;
            if (!isMoreSquare) {
                el.closest('.login-brand')?.classList.add('brand-rect');
            }
        }
    </script>

</body>
</html>
