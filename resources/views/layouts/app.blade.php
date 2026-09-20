<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script>
        (function () {
            try {
                if (localStorage.getItem('theme') === 'dark') {
                    document.documentElement.setAttribute('data-theme', 'dark');
                }
            } catch (e) {}
        })();
    </script>

    <title>{{ \App\Models\Setting::where('key', 'company_name')->value('value') ?? 'Mi Restaurante' }}</title>

    @php
        // ── Colores de marca (desde configuración) ──
        $__brandSettings = \App\Models\Setting::whereIn('key', [
            'color_primary', 'color_primary_hover', 'color_primary_soft',
            'color_sidebar_bg', 'color_sidebar_active', 'brand_text_color',
        ])->pluck('value', 'key')->toArray();

        $__colorPrimary        = $__brandSettings['color_primary']        ?? '#ff8c00';
        $__colorPrimaryHover   = $__brandSettings['color_primary_hover']  ?? '#e07b00';
        $__colorPrimarySoft    = $__brandSettings['color_primary_soft']   ?? '#fff4e6';
        $__colorSidebarBg      = $__brandSettings['color_sidebar_bg']     ?? '#2d1b5e';
        $__colorSidebarActive  = $__brandSettings['color_sidebar_active'] ?? '#ff8c00';
        $__brandTextColor      = $__brandSettings['brand_text_color']    ?? '#ffffff';
    @endphp

    <script>
        /* Colores de marca disponibles para JS (gráficos, etc.) */
        window.BRAND_COLORS = {
            primary: {!! json_encode($__colorPrimary) !!},
            primaryHover: {!! json_encode($__colorPrimaryHover) !!},
            primarySoft: {!! json_encode($__colorPrimarySoft) !!},
            sidebarBg: {!! json_encode($__colorSidebarBg) !!},
            sidebarActive: {!! json_encode($__colorSidebarActive) !!},
            brandText: {!! json_encode($__brandTextColor) !!},
        };
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        /* =============================================
           VARIABLES — TEMA PÚRPURA / NARANJA
        ============================================= */
        :root {
            --sidebar-bg:       {{ $__colorSidebarBg }};
            --sidebar-hover:    rgba(255,255,255,0.10);
            --sidebar-active:   {{ $__colorSidebarActive }};
            --sidebar-width:    220px;

            --primary:          {{ $__colorPrimary }};
            --primary-hover:    {{ $__colorPrimaryHover }};
            --primary-soft:     {{ $__colorPrimarySoft }};
            --brand-text:       {{ $__brandTextColor }};
            --accent-pink:      #ff4d7e;

            --body-bg:          #dcdcf0;
            --card-bg:          #ffffff;
            --text-main:        #1e1e2e;
            --text-muted:       #6c6c90;

            --radius-xl:        20px;
            --radius-md:        14px;
            --radius-sm:        10px;

            --shadow-soft:      0 8px 32px rgba(45,27,94,0.10);
            --shadow-card:      0 4px 18px rgba(45,27,94,0.08);
        }

        /* =============================================
           MODO OSCURO (no altera el modo claro)
        ============================================= */
        [data-theme="dark"] {
            --body-bg:          #101120;
            --card-bg:          #1b1c2e;
            --text-main:        #e8e8f2;
            --text-muted:       #9a9bb8;
            --shadow-soft:      0 8px 32px rgba(0,0,0,0.45);
            --shadow-card:      0 4px 18px rgba(0,0,0,0.35);
            color-scheme: dark;
        }

        [data-theme="dark"] body {
            background-color: var(--body-bg);
            color: var(--text-main);
        }

        [data-theme="dark"] .card {
            background-color: var(--card-bg);
            color: var(--text-main);
            box-shadow: var(--shadow-card);
        }

        [data-theme="dark"] .card-header,
        [data-theme="dark"] .card-footer,
        [data-theme="dark"] .offcanvas-header {
            background-color: var(--card-bg);
            border-color: #2a2b40;
            color: var(--text-main);
        }

        [data-theme="dark"] .bg-white { background-color: var(--card-bg) !important; }
        [data-theme="dark"] .bg-light { background-color: #242539 !important; }
        [data-theme="dark"] .bg-body { background-color: var(--body-bg) !important; }
        [data-theme="dark"] .text-dark { color: var(--text-main) !important; }
        [data-theme="dark"] .text-muted,
        [data-theme="dark"] .text-secondary { color: var(--text-muted) !important; }

        [data-theme="dark"] .border,
        [data-theme="dark"] .border-top,
        [data-theme="dark"] .border-bottom { border-color: #2a2b40 !important; }

        [data-theme="dark"] .topbar { background: var(--card-bg); border-bottom-color: #2a2b40; }
        [data-theme="dark"] .topbar-title h4 { color: var(--text-main); }
        [data-theme="dark"] .topbar-user-btn { background: var(--card-bg); }
        [data-theme="dark"] .topbar-user-btn span { color: var(--text-main) !important; }
        [data-theme="dark"] .card-stat-white { background: var(--card-bg); }

        [data-theme="dark"] .table {
            --bs-table-bg: transparent;
            --bs-table-hover-bg: #242539;
            background-color: transparent;
            color: var(--text-main);
        }
        [data-theme="dark"] .table td,
        [data-theme="dark"] .table th { border-color: #2a2b40; color: inherit; }
        [data-theme="dark"] .table > :not(caption) > * > * { background-color: transparent; }
        [data-theme="dark"] .table thead th,
        [data-theme="dark"] .table-light,
        [data-theme="dark"] thead.table-light,
        [data-theme="dark"] .table-hover > tbody > tr:hover,
        [data-theme="dark"] .table-hover > tbody > tr:hover > * {
            background-color: #242539 !important;
            color: var(--text-main) !important;
        }
        [data-theme="dark"] .table-striped > tbody > tr:nth-of-type(odd) { background-color: #202138; }

        [data-theme="dark"] .modal-content,
        [data-theme="dark"] .dropdown-menu,
        [data-theme="dark"] .offcanvas,
        [data-theme="dark"] .list-group-item {
            background-color: var(--card-bg);
            color: var(--text-main);
            border-color: #2a2b40;
        }
        [data-theme="dark"] .dropdown-item { color: var(--text-main); }
        [data-theme="dark"] .dropdown-item:hover { background-color: #2a2b40; color: var(--text-main); }
        [data-theme="dark"] .dropdown-divider { border-color: #2a2b40; }

        [data-theme="dark"] .form-control,
        [data-theme="dark"] .form-select {
            background-color: #242539;
            border-color: #33344d;
            color: var(--text-main);
        }
        [data-theme="dark"] .form-control:focus,
        [data-theme="dark"] .form-select:focus {
            background-color: #242539;
            color: var(--text-main);
        }
        [data-theme="dark"] .form-control::placeholder { color: var(--text-muted); }
        [data-theme="dark"] .form-control[readonly] { background-color: #242539; color: var(--text-main); }
        [data-theme="dark"] .input-group-text {
            background-color: #2a2b40;
            border-color: #33344d;
            color: var(--text-main);
        }
        [data-theme="dark"] .form-label { color: var(--text-main); }

        [data-theme="dark"] .btn-light {
            background-color: #2a2b40;
            border-color: #33344d;
            color: var(--text-main);
        }
        [data-theme="dark"] .btn-outline-secondary {
            background-color: transparent;
            color: var(--text-muted);
            border-color: #33344d;
        }
        [data-theme="dark"] .btn-outline-secondary:hover {
            background-color: #33344d;
            color: var(--text-main);
        }
        [data-theme="dark"] .btn-outline-dark {
            color: var(--text-main);
            border-color: #33344d;
        }
        [data-theme="dark"] .btn-outline-dark:hover { background-color: #33344d; color: var(--text-main); }
        [data-theme="dark"] .btn-close { filter: invert(1); }

        [data-theme="dark"] .nav-pills .nav-link { background-color: #242539; color: var(--text-muted); }
        [data-theme="dark"] .nav-tabs .nav-link { color: var(--text-muted); }
        [data-theme="dark"] .nav-tabs .nav-link.active { color: var(--primary); background-color: #242539; border-color: #2a2b40; border-bottom-color: #242539; }
        [data-theme="dark"] .nav-link { color: var(--text-muted); }

        [data-theme="dark"] .alert { color: var(--text-main); }
        [data-theme="dark"] .badge.bg-light { background-color: #2a2b40 !important; color: var(--text-main) !important; }
        [data-theme="dark"] .form-text { color: var(--text-muted); }
        [data-theme="dark"] .text-decoration-none.text-dark { color: var(--text-main) !important; }

        /* --- Paneles con estilos inline claros → oscuros --- */
        [data-theme="dark"] [style*="background:#f8f7ff"],
        [data-theme="dark"] [style*="background: #f8f7ff"],
        [data-theme="dark"] [style*="background:#f8f5ff"],
        [data-theme="dark"] [style*="background: #f8f5ff"],
        [data-theme="dark"] [style*="background:#f0eef8"],
        [data-theme="dark"] [style*="background: #f0eef8"] {
            background-color: #1b1c2e !important;
        }
        [data-theme="dark"] [style*="background:#fff1f2"],
        [data-theme="dark"] [style*="background: #fff1f2"] {
            background-color: #3a2430 !important;
        }
        [data-theme="dark"] [style*="#e5e7eb"] { border-color: #2a2b40 !important; }

        /* --- POS --- */
        [data-theme="dark"] #pos-wrap { background: var(--body-bg) !important; }
        [data-theme="dark"] #pos-wrap [style*="background: #fff"],
        [data-theme="dark"] #pos-wrap [style*="background:#fff"] { background-color: var(--card-bg) !important; }
        [data-theme="dark"] .pos-product-card { background-color: var(--card-bg) !important; border-color: #33344d !important; }
        [data-theme="dark"] .pos-product-icon-block,
        [data-theme="dark"] .pos-product-footer { background-color: #242539 !important; }
        [data-theme="dark"] .pos-product-name { color: var(--text-main) !important; }
        [data-theme="dark"] .pos-product-cat { color: var(--text-muted) !important; }
        [data-theme="dark"] .category-btn.list-group-item { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
        [data-theme="dark"] .category-btn.list-group-item.active { background-color: var(--primary) !important; color: #fff !important; }

        /* --- Paginación y botones claros --- */
        [data-theme="dark"] .page-link {
            background-color: var(--card-bg);
            border-color: #2a2b40;
            color: var(--text-main);
        }
        [data-theme="dark"] .page-item.active .page-link { background-color: var(--primary); border-color: var(--primary); color: #fff; }
        [data-theme="dark"] .page-item.disabled .page-link { background-color: #1b1c2e; color: var(--text-muted); }
        [data-theme="dark"] .btn-outline-light { color: var(--text-main); border-color: #33344d; }
        [data-theme="dark"] .btn-outline-light:hover { background-color: #33344d; color: var(--text-main); }

        /* Cualquier .btn sin variante de color (btn-light, btn-white, etc.) queda oscuro */
        [data-theme="dark"] .btn:not(.btn-primary):not(.btn-success):not(.btn-danger):not(.btn-warning):not(.btn-info):not(.btn-secondary):not(.btn-link):not(.btn-outline-primary):not(.btn-outline-secondary):not(.btn-outline-success):not(.btn-outline-danger):not(.btn-outline-warning):not(.btn-outline-info):not(.btn-outline-dark):not(.btn-outline-light):not(.nav-link) {
            background-color: #2a2b40 !important;
            border-color: #33344d !important;
            color: var(--text-main) !important;
        }

        /* --- Mesas: lienzo --- */
        [data-theme="dark"] .salon-canvas {
            background-color: #17182a !important;
            background-image: radial-gradient(#2a2b40 1px, transparent 1px) !important;
        }
        [data-theme="dark"] .draggable-table { background-color: var(--card-bg) !important; border-color: #33344d !important; }
        [data-theme="dark"] [style*="radial-gradient"] { background-image: radial-gradient(#2a2b40 1px, transparent 1px) !important; }

        /* --- Notas / cajas info claras --- */
        [data-theme="dark"] .alert-info { background-color: #1c2733 !important; border-color: #274057 !important; color: #b7d9f2 !important; }
        [data-theme="dark"] .alert-success { background-color: #0f2e1a !important; border-color: #1a5c2e !important; color: #86efac !important; }
        [data-theme="dark"] .alert-warning { background-color: #2e2200 !important; border-color: #7c5c00 !important; color: #fde68a !important; }
        [data-theme="dark"] .alert-danger { background-color: #2e0f0f !important; border-color: #7c1a1a !important; color: #fca5a5 !important; }

        .theme-toggle-btn {
            width: 38px; height: 38px;
            display: flex; align-items: center; justify-content: center;
            background: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            box-shadow: var(--shadow-card);
            font-size: 1.05rem;
            color: var(--text-main);
            transition: transform .2s, box-shadow .2s;
        }
        .theme-toggle-btn:hover { transform: scale(1.08); box-shadow: 0 6px 20px rgba(45,27,94,0.14); }
        [data-theme="dark"] .theme-toggle-btn { background: var(--card-bg); color: #ffb84d; }

        /* =============================================
           BASE
        ============================================= */
        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--body-bg);
            color: var(--text-main);
            font-size: 0.92rem;
            overflow-x: hidden;
            margin: 0;
        }

        /* =============================================
           SIDEBAR
        ============================================= */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            top: 0; left: 0;
            background: var(--sidebar-bg);
            color: white;
            z-index: 1050;
            transition: transform 0.3s cubic-bezier(0.4,0,0.2,1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* --- Logo / Brand --- */
        .sidebar-brand {
            padding: 24px 20px 18px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
            border-bottom: 1px solid rgba(255,255,255,0.07);
        }

        .sidebar-brand:has(.sidebar-brand-logo-link) {
            justify-content: center;
            padding-top: 14px;
            padding-bottom: 14px;
        }
        .sidebar-brand-logo-link {
            display: flex;
            justify-content: center;
            width: 100%;
        }
        .sidebar-brand-logo-link img {
            max-width: 160px !important;
            max-height: 70px !important;
        }

        .logo-box {
            width: 40px; height: 40px;
            background: var(--primary);
            color: white;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
            box-shadow: 0 4px 14px rgba(255,140,0,0.45);
        }

        .brand-name {
            font-weight: 700;
            font-size: 15px;
            letter-spacing: -0.3px;
            color: var(--brand-text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* --- User Profile Block --- */
        .sidebar-user {
            padding: 8px 14px;
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 8px;
            border-bottom: 1px solid rgba(255,255,255,0.07);
            flex-shrink: 0;
        }

        .sidebar-avatar-wrap {
            position: relative;
            display: inline-block;
            flex-shrink: 0;
        }

        .sidebar-avatar {
            width: 32px; height: 32px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px;
            font-weight: 700;
        }

        .sidebar-avatar-dot {
            width: 8px; height: 8px;
            background: var(--accent-pink);
            border-radius: 50%;
            border: 2px solid var(--sidebar-bg);
            position: absolute;
            bottom: 0; right: 0;
        }

        .sidebar-username {
            color: rgba(255,255,255,0.92);
            font-size: 0.78rem;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* --- Navigation Menu --- */
        .sidebar-menu {
            padding: 16px 12px;
            flex-grow: 1;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: rgba(255,255,255,0.15) transparent;
        }

        .sidebar-menu::-webkit-scrollbar { width: 4px; }
        .sidebar-menu::-webkit-scrollbar-track { background: transparent; }
        .sidebar-menu::-webkit-scrollbar-thumb {
            background-color: rgba(255,255,255,0.18);
            border-radius: 20px;
        }

        .menu-category {
            color: rgba(255,255,255,0.35);
            font-size: 0.67rem;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 1.2px;
            margin: 18px 8px 8px;
        }

        .sidebar .nav-link {
            color: rgba(255,255,255,0.70);
            padding: 11px 14px;
            border-radius: var(--radius-sm);
            margin-bottom: 3px;
            font-weight: 500;
            font-size: 0.88rem;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .sidebar .nav-link i {
            font-size: 1.15rem;
            opacity: 0.75;
            transition: opacity 0.2s;
            flex-shrink: 0;
        }

        .sidebar .nav-link:hover {
            background: var(--sidebar-hover);
            color: white;
        }

        .sidebar .nav-link.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 6px 18px rgba(255,140,0,0.35);
        }

        .sidebar .nav-link.active i { opacity: 1; }

        /* =============================================
           MAIN CONTENT
        ============================================= */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 24px 28px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: margin-left 0.3s;
        }

        /* POS page: sin padding para aprovechar 100% de altura */
        body.pos-page .main-content {
            padding: 0;
            height: 100vh;
            overflow: hidden;
        }

        /* =============================================
           TOPBAR
        ============================================= */
        .top-navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 14px;
        }

        .topbar-title h4 {
            font-size: 1.45rem;
            font-weight: 700;
            color: var(--text-main);
            margin: 0;
            line-height: 1.2;
        }

        .topbar-title p {
            font-size: 0.78rem;
            color: var(--text-muted);
            margin: 3px 0 0;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .topbar-user-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            background: white;
            border: none;
            border-radius: 50px;
            padding: 6px 14px 6px 6px;
            cursor: pointer;
            box-shadow: var(--shadow-card);
            transition: box-shadow 0.2s;
        }

        .topbar-user-btn:hover {
            box-shadow: 0 6px 20px rgba(45,27,94,0.14);
        }

        .topbar-avatar {
            width: 34px; height: 34px;
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: white;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700;
            font-size: 14px;
        }

        /* =============================================
           CARDS
        ============================================= */
        .card {
            border: none;
            background: var(--card-bg);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-card);
            overflow: hidden;
        }

        .card-header {
            background: transparent;
            border-bottom: 1px solid #f0eef8;
            padding: 1.1rem 1.4rem;
        }

        .card-body { padding: 1.4rem; }

        /* Orange Stat Cards */
        .card-orange {
            background: var(--primary) !important;
            color: white !important;
            border-radius: var(--radius-xl);
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(255,140,0,0.35);
        }

        .card-orange .card-body { padding: 1.2rem 1.4rem; }

        .card-orange h6 {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            opacity: 0.9;
            margin-bottom: 6px;
        }

        .card-orange h2 {
            font-size: 2rem;
            font-weight: 800;
            margin: 0;
            letter-spacing: -1px;
        }

        .card-orange small {
            font-size: 0.72rem;
            opacity: 0.8;
        }

        .card-orange .icon-bg {
            position: absolute;
            right: -8px; top: 50%;
            transform: translateY(-50%);
            font-size: 5.5rem;
            opacity: 0.12;
            pointer-events: none;
        }

        .card-orange .card-minus {
            position: absolute;
            top: 10px; right: 12px;
            background: rgba(255,255,255,0.25);
            border: none;
            color: white;
            width: 22px; height: 22px;
            border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px;
            font-weight: 700;
            cursor: default;
        }

        /* White stat cards */
        .card-stat-white {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-card);
            overflow: hidden;
        }

        /* Buttons */
        .btn { padding: 0.55rem 1.3rem; border-radius: 50px; font-weight: 600; border: none; font-size: 0.85rem; }
        .btn-primary { background: var(--primary); box-shadow: 0 4px 14px rgba(255,140,0,0.3); color: white; }
        .btn-primary:hover { background: var(--primary-hover); color: white; }
        .btn-success { background: #22c55e; color: white; }
        .btn-success:hover { background: #16a34a; color: white; }
        .btn-danger { background: var(--accent-pink); color: white; }
        .btn-danger:hover { background: #e03060; color: white; }
        .btn-outline-secondary { border: 1.5px solid #e0ddf0; color: var(--text-muted); background: white; }
        .btn-outline-secondary:hover { background: var(--body-bg); border-color: #c5c0e0; color: var(--text-main); }

        /* Forms */
        .form-control, .form-select {
            background-color: #f7f5fd;
            border: 1px solid #e8e4f4;
            border-radius: var(--radius-sm);
            padding: 0.75rem 1rem;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(255,140,0,0.15);
            background: white;
        }

        /* Alerts */
        .alert { border-radius: var(--radius-md); }

        /* Tables */
        .table { --bs-table-hover-bg: #faf8ff; }
        .table th { font-weight: 700; color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px; }

        /* Badges */
        .badge { font-weight: 600; border-radius: 50px; }

        /* Scrollbar global */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(45,27,94,0.15); border-radius: 10px; }

        /* =============================================
           MOBILE RESPONSIVE
        ============================================= */
        .mobile-overlay {
            position: fixed; top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(20,10,45,0.75);
            z-index: 1040;
            backdrop-filter: blur(4px);
            display: none;
        }

        .mobile-overlay.show { display: block; }

        @media (max-width: 991px) {
            .sidebar { transform: translateX(-100%); width: 240px; }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 16px; }
        }

        /* ── Global mobile overrides ── */
        @media (max-width: 575.98px) {
            .modal-content { margin: 8px; }
            .modal-fullscreen-sm-down .modal-body { padding: 12px; overflow-y: auto; max-height: 70vh; }
            .table-responsive { font-size: 0.85rem; }
            .btn { white-space: nowrap; }
            .fw-bold { font-weight: 600 !important; }
            .top-navbar { flex-wrap: nowrap; gap: 8px; margin-bottom: 16px; }
            .topbar-title { min-width: 0; flex: 1; overflow: hidden; }
            .topbar-title h4 { font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .topbar-title p { font-size: 0.65rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .topbar-right { flex-shrink: 0; gap: 6px; }
            .topbar-user-btn { padding: 4px 8px; gap: 6px; }
            .topbar-avatar { width: 28px; height: 28px; font-size: 0.7rem; }
            .theme-toggle-btn { width: 30px; height: 30px; font-size: 0.8rem; }
        }
    </style>
</head>
<body class="{{ request()->routeIs('pos.order') ? 'pos-page' : '' }}">

<div class="mobile-overlay" id="mobileOverlay" onclick="closeMenu()"></div>

<!-- ===================== SIDEBAR ===================== -->
<div class="sidebar" id="sidebar">

    {{-- Brand --}}
    <div class="sidebar-brand">
        @php
            $logo = \App\Models\Setting::where('key', 'company_logo')->value('value');
            $logoIsRect = false;
            if ($logo) {
                $size = @getimagesize(storage_path('app/public/'.$logo));
                if ($size) {
                    [$w, $h] = $size;
                    $logoIsRect = $w > $h;
                }
            }
        @endphp
        @if($logo && !$logoIsRect)
            <a href="{{ route('dashboard') }}" class="sidebar-brand-link" aria-label="Ir al dashboard">
                <img src="{{ route('uploaded-assets.show', ['path' => $logo]) }}" style="width:40px;height:40px;object-fit:contain;" alt="logo">
            </a>
            <div class="brand-name">{{ \App\Models\Setting::where('key', 'company_name')->value('value') ?? 'Mi Restaurante' }}</div>
        @elseif($logoIsRect)
            <a href="{{ route('dashboard') }}" class="sidebar-brand-link sidebar-brand-logo-link" aria-label="Ir al dashboard">
                <img src="{{ route('uploaded-assets.show', ['path' => $logo]) }}" style="max-width:100%;width:100%;max-height:60px;object-fit:contain;" alt="logo">
            </a>
        @else
            <a href="{{ route('dashboard') }}" class="sidebar-brand-link" aria-label="Ir al dashboard">
                <div class="logo-box"><i class="bi bi-shop"></i></div>
            </a>
            <div class="brand-name">{{ \App\Models\Setting::where('key', 'company_name')->value('value') ?? 'Mi Restaurante' }}</div>
        @endif
        <button class="btn btn-sm text-white-50 d-lg-none ms-auto p-0" onclick="closeMenu()"><i class="bi bi-x-lg fs-5"></i></button>
    </div>

    {{-- User Profile --}}
    <div class="sidebar-user">
        <div class="sidebar-avatar-wrap">
            <div class="sidebar-avatar">
                {{ substr(Auth::user()->name, 0, 1) }}
            </div>
            <div class="sidebar-avatar-dot"></div>
        </div>
        <span class="sidebar-username">{{ Auth::user()->name }}</span>
    </div>

    {{-- Navigation --}}
    <div class="sidebar-menu">
        @php $role = Auth::user()->role; @endphp

        @if(in_array($role, ['admin', 'cashier', 'cashier_waiter']))
            <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="bi bi-grid-1x2-fill"></i> Dashboard
            </a>
        @endif

        @if($role === 'admin')
            <a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                <i class="bi bi-bar-chart-line-fill"></i> Reportes
            </a>
        @endif

        <div class="menu-category">Operaciones</div>
        <a href="{{ route('pos.index') }}" class="nav-link {{ request()->routeIs('pos.*') ? 'active' : '' }}">
            <i class="bi bi-bag-check-fill"></i> Punto de Venta
        </a>
        @if(in_array($role, ['admin', 'cashier', 'cashier_waiter']))
            <a href="{{ route('delivery.index') }}" class="nav-link {{ request()->routeIs('delivery.*') ? 'active' : '' }}">
                <i class="bi bi-bicycle"></i> Delivery
            </a>
        @endif
        <a href="{{ route('reservations.index') }}" class="nav-link {{ request()->routeIs('reservations.*') ? 'active' : '' }}">
            <i class="bi bi-calendar-event-fill"></i> Reservas
        </a>
        @if(in_array($role, ['admin', 'cashier', 'cashier_waiter']))
            <a href="{{ route('sales.index') }}" class="nav-link {{ request()->routeIs('sales.*') ? 'active' : '' }}">
                <i class="bi bi-receipt"></i> Historial de Ventas
            </a>
            @if(in_array($role, ['admin', 'cashier', 'cashier_waiter']))
                <div class="menu-category" style="margin-top:12px;">Facturación Electrónica</div>
                <a href="{{ route('billing.index') }}" class="nav-link {{ request()->routeIs('billing.*') ? 'active' : '' }}">
                    <i class="bi bi-receipt-cutoff"></i> Comprobantes
                </a>
            @endif
            @if($role === 'admin')
                <a href="{{ route('credit_notes.index') }}" class="nav-link {{ request()->routeIs('credit_notes.*') ? 'active' : '' }}">
                    <i class="bi bi-arrow-counterclockwise"></i> Notas de Crédito
                </a>
                <a href="{{ route('debit_notes.index') }}" class="nav-link {{ request()->routeIs('debit_notes.*') ? 'active' : '' }}">
                    <i class="bi bi-arrow-clockwise"></i> Notas de Débito
                </a>
            @endif
            <div class="menu-category" style="margin-top:12px;">Caja / Arqueo</div>
            @if(Auth::user() && Auth::user()->activeCashRegister)
                <a href="{{ route('cash_registers.close') }}" class="nav-link {{ request()->routeIs('cash_registers.close') ? 'active' : '' }}">
                    <i class="bi bi-box-arrow-left"></i> Cerrar Caja
                </a>
            @else
                <a href="{{ route('cash_registers.create') }}" class="nav-link {{ request()->routeIs('cash_registers.create') ? 'active' : '' }}">
                    <i class="bi bi-box-arrow-in-right"></i> Abrir Caja
                </a>
            @endif
            @if($role === 'admin')
                <a href="{{ route('cash_registers.index') }}" class="nav-link {{ request()->routeIs('cash_registers.index') ? 'active' : '' }}">
                    <i class="bi bi-clock-history"></i> Historial de Turnos
                </a>
            @endif
        @endif
        <a href="{{ route('kitchen.index') }}" class="nav-link {{ request()->routeIs('kitchen.*') ? 'active' : '' }}">
            <i class="bi bi-fire"></i> Cocina (KDS)
        </a>

        <div class="menu-category">Gestión</div>
        @if(in_array($role, ['admin', 'cashier', 'cashier_waiter', 'waiter']))
            <a href="{{ route('clients.index') }}" class="nav-link {{ request()->routeIs('clients.*') ? 'active' : '' }}">
                <i class="bi bi-people-fill"></i> Clientes
            </a>
        @endif
        @if($role === 'admin')
            <a href="{{ route('categories.index') }}" class="nav-link {{ request()->routeIs('categories.*') ? 'active' : '' }}">
                <i class="bi bi-tags-fill"></i> Categorías
            </a>
            <a href="{{ route('products.index') }}" class="nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}">
                <i class="bi bi-box-seam-fill"></i> Inventario
            </a>
            <a href="{{ route('menu.index') }}" target="_blank" class="nav-link text-warning">
                <i class="bi bi-qr-code-scan"></i> Ver Carta Digital <i class="bi bi-box-arrow-up-right ms-auto" style="font-size: 0.75rem;"></i>
            </a>
            <a href="{{ route('tables.index') }}" class="nav-link {{ request()->routeIs('tables.*') ? 'active' : '' }}">
                <i class="bi bi-grid-3x3-gap-fill"></i> Mesas
            </a>
            <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                <i class="bi bi-person-badge-fill"></i> Personal / Usuarios
            </a>
            <a href="{{ route('settings.index') }}" class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                <i class="bi bi-gear-fill"></i> Configuración
            </a>
            {{-- Sitio Web (oculto temporalmente — conservar para futuro)
            <a href="{{ route('landing.settings') }}" class="nav-link {{ request()->routeIs('landing.*') ? 'active' : '' }}">
                <i class="bi bi-globe2"></i> Sitio Web
            </a>
            <a href="{{ route('landing.index') }}" target="_blank" rel="noopener" class="nav-link text-warning">
                <i class="bi bi-display"></i> Ver Sitio Web <i class="bi bi-box-arrow-up-right ms-auto" style="font-size: 0.75rem;"></i>
            </a>
            --}}
            {{-- Mantenimiento oculto en producción; se conserva la ruta para administración controlada. --}}
        @endif
    </div>
</div>

<!-- ===================== MAIN CONTENT ===================== -->
<div class="main-content">

    @if(!request()->routeIs('pos.order'))
    <div class="top-navbar">

        {{-- Left: Title --}}
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-light border d-lg-none px-2 py-1" onclick="openMenu()">
                <i class="bi bi-list fs-5"></i>
            </button>
            <div class="topbar-title">
                <h4>{{ \App\Models\Setting::where('key', 'welcome_message')->value('value') ?? 'Bienvenido de vuelta' }}</h4>
                <p>{{ \Carbon\Carbon::now()->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY') }}</p>
            </div>
        </div>

        {{-- Right: User --}}
        <div class="topbar-right">
            <button type="button" class="theme-toggle-btn" id="themeToggle" title="Cambiar tema">
                <i class="bi bi-moon-stars-fill"></i>
            </button>
            <div class="dropdown">
                <div class="topbar-user-btn" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="topbar-avatar">{{ substr(Auth::user()->name, 0, 1) }}</div>
                    <span class="fw-semibold text-dark small d-none d-sm-inline">{{ Auth::user()->name }}</span>
                    <i class="bi bi-chevron-down text-muted small"></i>
                </div>
                <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg p-2 rounded-4" style="width:210px;">
                    <li class="px-2 py-1 text-muted small fw-bold">MI CUENTA</li>
                    <li>
                        <button class="dropdown-item rounded-3 mb-1" data-bs-toggle="modal" data-bs-target="#profileModal">
                            <i class="bi bi-person-gear me-2 text-primary"></i> Editar Perfil
                        </button>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="dropdown-item rounded-3 text-danger fw-bold">
                                <i class="bi bi-box-arrow-right me-2"></i> Cerrar Sesión
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    @endif

    @yield('content')
</div>

<!-- ===================== PROFILE MODAL ===================== -->
<div class="modal fade" id="profileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold">Mi Perfil</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <p class="text-muted small mb-3">Actualiza tus datos de acceso.</p>
                <form action="{{ route('users.update', Auth::user()->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Nombre</label>
                        <input type="text" name="name" class="form-control" value="{{ Auth::user()->name }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Correo (Solo lectura)</label>
                        <input type="email" class="form-control bg-light" value="{{ Auth::user()->email }}" readonly>
                    </div>
                    <hr>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Nueva Contraseña (Opcional)</label>
                        <input type="password" name="password" class="form-control" placeholder="Dejar en blanco para no cambiar">
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary fw-bold">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    window.lookupPeruvianDocument = function (url, documentNumber) {
        return fetch(url + '?document=' + encodeURIComponent(documentNumber))
            .then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) {
                        const error = new Error(data.message || 'No se pudo consultar el documento.');
                        error.data = data;
                        throw error;
                    }
                    return data;
                });
            });
    };

    function openMenu()  { document.getElementById('sidebar').classList.add('show');    document.getElementById('mobileOverlay').classList.add('show');    document.body.style.overflow = 'hidden'; }
    function closeMenu() { document.getElementById('sidebar').classList.remove('show'); document.getElementById('mobileOverlay').classList.remove('show'); document.body.style.overflow = 'auto';   }

    // Auto-disable bootstrap dismiss buttons (alerts are now permanent)
    // No auto-dismiss: all alerts stay visible until page navigation

    // ── Modo oscuro / claro ──
    function applyTheme(theme) {
        if (theme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        } else {
            document.documentElement.removeAttribute('data-theme');
        }
        const icon = document.querySelector('#themeToggle i');
        if (icon) icon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
    }

    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', function () {
            const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
            const next = current === 'dark' ? 'light' : 'dark';
            localStorage.setItem('theme', next);
            applyTheme(next);
        });
    }
    applyTheme(document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light');
</script>

@if(session('success') && !request()->routeIs('settings.index', 'settings.update', 'pos.index', 'categories.index', 'products.index', 'tables.index', 'billing.index'))
<script>
    Swal.fire({
        icon: 'success',
        title: '¡Éxito!',
        text: @json(session('success')),
        timer: 2200,
        showConfirmButton: false
    });
</script>
@endif

@if(session('error') && !request()->routeIs('settings.index', 'settings.update', 'categories.index', 'products.index', 'tables.index', 'billing.index'))
<script>
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: @json(session('error')),
        confirmButtonText: 'Entendido'
    });
</script>
@endif

@if(session('warning'))
<script>
    Swal.fire({
        icon: 'warning',
        title: 'Aviso',
        text: @json(session('warning')),
        confirmButtonText: 'Entendido'
    });
</script>
@endif

@if(session('info'))
<script>
    Swal.fire({
        icon: 'info',
        title: 'Información',
        text: @json(session('info')),
        confirmButtonText: 'Entendido'
    });
</script>
@endif

@stack('scripts')
</body>
</html>
