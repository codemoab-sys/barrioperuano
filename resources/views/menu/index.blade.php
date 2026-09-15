<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Carta digital de {{ $companyName }}. Explora nuestros platos, ofertas y favoritos.">
    <title>Carta Digital — {{ $companyName }}</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: {{ $brandColor['primary'] }};
            --primary-dark: {{ $brandColor['primary_hover'] }};
            --primary-soft: {{ $brandColor['primary_soft'] }};
            --dark: #1e1e2d;
            --ink: #2b2b3a;
            --muted: #7c7c8c;
            --gray-bg: #f7f7f9;
            --radius: 18px;
            --shadow-sm: 0 2px 10px rgba(30, 30, 45, .06);
            --shadow-md: 0 10px 30px rgba(30, 30, 45, .10);
        }
        * { box-sizing: border-box; }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--gray-bg);
            color: var(--ink);
            -webkit-font-smoothing: antialiased;
        }

        section { scroll-margin-top: 84px; }

        :focus-visible {
            outline: 3px solid rgba(255, 140, 0, .55);
            outline-offset: 2px;
        }

        /* ═══════════════ HERO ═══════════════ */
        .hero {
            position: relative;
            isolation: isolate;
            background: linear-gradient(135deg, #17182a 0%, var(--dark) 45%, #3a3a5c 100%);
            color: #fff;
            padding: 48px 16px 96px;
            border-radius: 0 0 34px 34px;
            overflow: hidden;
        }
        .hero::before,
        .hero::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            filter: blur(70px);
            z-index: -1;
        }
        .hero::before { width: 260px; height: 260px; background: rgba(255, 140, 0, .30); right: -60px; top: -70px; }
        .hero::after  { width: 220px; height: 220px; background: rgba(88, 101, 242, .30); left: -60px; bottom: -60px; }

        .hero-logo {
            width: 84px; height: 84px;
            object-fit: cover;
            background: transparent;
        }
        .brand-rect .hero-logo {
            width: auto;
            max-width: 100%;
            max-height: 160px;
            height: auto;
            object-fit: contain !important;
            background: transparent;
        }
        .brand-rect .hero-title-name,
        .brand-rect .hero-tagline { display: none; }
        .hero-icon {
            width: 84px; height: 84px;
            border-radius: 22px;
            background: #fff;
            color: var(--primary);
            font-size: 2.2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .35);
        }
        .hero h1 { font-weight: 800; font-size: clamp(1.7rem, 5vw, 2.5rem); letter-spacing: -.6px; }
        .hero-tagline { font-weight: 300; opacity: .85; }

        /* ═══════════════ SEARCH ═══════════════ */
        .search-box {
            position: relative;
            max-width: 460px;
            margin: 20px auto 0;
        }
        .search-box .bi-search {
            position: absolute;
            left: 18px; top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            pointer-events: none;
        }
        .search-box input {
            width: 100%;
            padding: 14px 18px 14px 48px;
            border: none;
            border-radius: 50px;
            font-size: 1rem;
            background: rgba(255, 255, 255, .97);
            box-shadow: 0 10px 30px rgba(0, 0, 0, .25);
        }
        .search-box input:focus { box-shadow: 0 0 0 4px rgba(255, 140, 0, .35); }
        .search-hint { color: rgba(255, 255, 255, .7); font-size: .78rem; margin-top: 10px; }

        /* ═══════════════ STICKY NAV ═══════════════ */
        .category-nav-wrap {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(247, 247, 249, .92);
            backdrop-filter: blur(8px);
            padding: 12px 0 6px;
            border-bottom: 1px solid rgba(30, 30, 45, .06);
        }
        .category-nav {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            scrollbar-width: none;
            padding: 4px 16px 8px;
        }
        .category-nav::-webkit-scrollbar { display: none; }
        .category-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fff;
            color: var(--ink);
            padding: 9px 18px;
            border-radius: 50px;
            font-weight: 600;
            font-size: .85rem;
            white-space: nowrap;
            text-decoration: none;
            border: 1px solid rgba(30, 30, 45, .08);
            box-shadow: var(--shadow-sm);
            transition: all .25s ease;
        }
        .category-pill:hover { color: var(--primary); transform: translateY(-1px); }
        .category-pill.active {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
            box-shadow: 0 6px 18px rgba(255, 140, 0, .4);
        }

        /* ═══════════════ SECTIONS ═══════════════ */
        .section-wrap { margin-top: 26px; }
        .section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }
        .section-title {
            font-weight: 800;
            font-size: 1.25rem;
            color: var(--dark);
            letter-spacing: -.3px;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 9px;
        }
        .section-title i { color: var(--primary); }
        .count-badge {
            background: #fff;
            color: var(--muted);
            border: 1px solid rgba(30, 30, 45, .08);
            border-radius: 50px;
            padding: 3px 12px;
            font-size: .75rem;
            font-weight: 700;
        }

        /* ═══════════════ PRODUCT CARD ═══════════════ */
        .product-card {
            background: #fff;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: transform .2s ease, box-shadow .2s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        .product-media {
            position: relative;
            aspect-ratio: 4 / 3;
            background: #eee;
            overflow: hidden;
        }
        .product-media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .product-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #b9b9c4;
            font-size: 2rem;
            background: linear-gradient(135deg, #f1f1f4, #e7e7ec);
        }
        .badge-custom {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 12px;
            font-size: .68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .5px;
            border-radius: 50px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .18);
        }
        .badge-promo   { background: #e53935; color: #fff; }
        .badge-chef    { background: var(--dark); color: #ffd700; }
        .badge-new     { background: #2e7d32; color: #fff; }
        .badge-popular { background: var(--primary); color: #fff; }
        .badge-discount {
            position: absolute;
            top: 10px;
            left: 10px;
            padding: 5px 12px;
            font-size: .68rem;
            font-weight: 800;
            border-radius: 50px;
            background: #fff;
            color: #e53935;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .14);
        }
        .product-body {
            padding: 14px 16px 16px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }
        .product-name {
            font-weight: 700;
            font-size: 1.02rem;
            color: var(--dark);
            line-height: 1.25;
            margin-bottom: 4px;
        }
        .product-desc {
            font-size: .82rem;
            color: var(--muted);
            margin-bottom: 12px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .product-price-row {
            margin-top: auto;
            display: flex;
            align-items: baseline;
            gap: 10px;
        }
        .price { font-weight: 800; font-size: 1.15rem; color: var(--dark); }
        .price.promo { color: #e53935; }
        .price-old { font-size: .85rem; color: #aaa; text-decoration: line-through; font-weight: 500; }

        /* ═══════════════ EMPTY / FOOTER / TOP ═══════════════ */
        .empty-state {
            background: #fff;
            border-radius: var(--radius);
            padding: 48px 20px;
            text-align: center;
            color: var(--muted);
        }
        .site-footer {
            margin-top: 40px;
            padding: 22px 16px 30px;
            text-align: center;
            color: var(--muted);
            font-size: .8rem;
        }
        .back-to-top {
            position: fixed;
            right: 18px;
            bottom: 20px;
            width: 46px;
            height: 46px;
            border-radius: 50%;
            border: none;
            background: var(--primary);
            color: #fff;
            font-size: 1.1rem;
            display: grid;
            place-items: center;
            box-shadow: var(--shadow-md);
            opacity: 0;
            visibility: hidden;
            transform: translateY(12px);
            transition: all .3s ease;
            z-index: 100;
        }
        .back-to-top.show { opacity: 1; visibility: visible; transform: translateY(0); }
        .back-to-top:hover { background: var(--primary-dark); }

        @media (max-width: 575px) {
            .product-media { aspect-ratio: 16 / 10; }
            .section-title { font-size: 1.1rem; }
        }

        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            * { transition: none !important; }
        }
    </style>
</head>
<body>

    {{-- ═══════════════ HERO ═══════════════ --}}
    <header class="hero text-center" id="menuHero">
        <div class="container">
            @if($companyLogo)
                <img src="{{ route('uploaded-assets.show', ['path' => $companyLogo]) }}" id="heroLogo" class="hero-logo" alt="{{ $companyName }}">
            @else
                <div class="hero-icon d-inline-flex align-items-center justify-content-center">
                    <i class="bi bi-shop"></i>
                </div>
            @endif

            <h1 class="mt-3 mb-1 hero-title-name">{{ $companyName }}</h1>
            <p class="hero-tagline mb-0">Explora nuestra deliciosa carta digital</p>

            <div class="search-box">
                <i class="bi bi-search"></i>
                <input type="search" id="menuSearch" class="form-control" placeholder="Buscar plato o ingrediente..." autocomplete="off" aria-label="Buscar en la carta">
            </div>
            <p class="search-hint">Encuentra tu plato favorito en segundos</p>
        </div>
    </header>

    {{-- ═══════════════ NAV CATEGORÍAS ═══════════════ --}}
    <nav class="category-nav-wrap" aria-label="Secciones de la carta">
        <div class="category-nav">
            @if($promotions->count() > 0)
                <a href="#promo" class="category-pill"><i class="bi bi-tag-fill text-danger"></i> Ofertas</a>
            @endif
            @if($chefRecommendations->count() > 0)
                <a href="#chef" class="category-pill"><i class="bi bi-star-fill text-warning"></i> Sugerencias</a>
            @endif
            @if($mostPopular->count() > 0)
                <a href="#popular" class="category-pill"><i class="bi bi-fire text-danger"></i> Favoritos</a>
            @endif
            @if($newProducts->count() > 0)
                <a href="#nuevos" class="category-pill"><i class="bi bi-stars text-success"></i> Novedades</a>
            @endif
            @foreach($categories as $category)
                @if($category->products->count() > 0)
                    <a href="#cat-{{ $category->id }}" class="category-pill"><i class="bi bi-journal-text"></i> {{ $category->name }}</a>
                @endif
            @endforeach
        </div>
    </nav>

    <main class="container px-3">

        {{-- ═══════════════ PROMOCIONES ═══════════════ --}}
        @if($promotions->count() > 0)
        <section id="promo" class="section-wrap">
            <div class="section-head">
                <h2 class="section-title"><i class="bi bi-tags-fill"></i> Ofertas Especiales</h2>
                <span class="count-badge">{{ $promotions->count() }} platos</span>
            </div>
            <div class="row g-3">
                @foreach($promotions as $product)
                    <div class="col-12 col-sm-6 col-xl-4">
                        @include('menu.partials.product-card', ['product' => $product, 'badge' => 'promo', 'badgeText' => 'OFERTA'])
                    </div>
                @endforeach
            </div>
        </section>
        @endif

        {{-- ═══════════════ SUGERENCIAS DEL CHEF ═══════════════ --}}
        @if($chefRecommendations->count() > 0)
        <section id="chef" class="section-wrap">
            <div class="section-head">
                <h2 class="section-title"><i class="bi bi-star-fill text-warning" style="color:#f59e0b;"></i> Sugerencias del Chef</h2>
                <span class="count-badge">{{ $chefRecommendations->count() }} platos</span>
            </div>
            <div class="row g-3">
                @foreach($chefRecommendations as $product)
                    <div class="col-12 col-sm-6 col-xl-4">
                        @include('menu.partials.product-card', ['product' => $product, 'badge' => 'chef', 'badgeText' => 'Sugerencia'])
                    </div>
                @endforeach
            </div>
        </section>
        @endif

        {{-- ═══════════════ FAVORITOS ═══════════════ --}}
        @if($mostPopular->count() > 0)
        <section id="popular" class="section-wrap">
            <div class="section-head">
                <h2 class="section-title"><i class="bi bi-fire text-danger"></i> Los Favoritos</h2>
                <span class="count-badge">{{ $mostPopular->count() }} platos</span>
            </div>
            <div class="row g-3">
                @foreach($mostPopular as $product)
                    <div class="col-12 col-sm-6 col-xl-4">
                        @include('menu.partials.product-card', ['product' => $product, 'badge' => 'popular', 'badgeText' => 'Top Ventas'])
                    </div>
                @endforeach
            </div>
        </section>
        @endif

        {{-- ═══════════════ NOVEDADES ═══════════════ --}}
        @if($newProducts->count() > 0)
        <section id="nuevos" class="section-wrap">
            <div class="section-head">
                <h2 class="section-title"><i class="bi bi-stars text-success" style="color:#16a34a;"></i> Novedades</h2>
                <span class="count-badge">{{ $newProducts->count() }} platos</span>
            </div>
            <div class="row g-3">
                @foreach($newProducts as $product)
                    <div class="col-12 col-sm-6 col-xl-4">
                        @include('menu.partials.product-card', ['product' => $product, 'badge' => 'new', 'badgeText' => 'NUEVO'])
                    </div>
                @endforeach
            </div>
        </section>
        @endif

        {{-- ═══════════════ CARTA POR CATEGORÍAS ═══════════════ --}}
        @foreach($categories as $category)
            @if($category->products->count() > 0)
            <section id="cat-{{ $category->id }}" class="section-wrap">
                <div class="section-head">
                    <h2 class="section-title"><i class="bi bi-journal-text"></i> {{ $category->name }}</h2>
                    <span class="count-badge">{{ $category->products->count() }} platos</span>
                </div>
                <div class="row g-3">
                    @foreach($category->products as $product)
                        <div class="col-12 col-sm-6 col-xl-4">
                            @include('menu.partials.product-card', ['product' => $product, 'badge' => null])
                        </div>
                    @endforeach
                </div>
            </section>
            @endif
        @endforeach

        {{-- ═══════════════ SIN RESULTADOS ═══════════════ --}}
        <div id="noResults" class="empty-state d-none mt-3">
            <i class="bi bi-emoji-frown fs-1 d-block mb-2"></i>
            <strong class="d-block mb-1">No encontramos resultados</strong>
            Prueba con otro nombre o ingrediente.
        </div>

    </main>

    <footer class="site-footer">
        <i class="bi bi-qr-code-scan d-block mb-2" style="font-size:1.6rem;"></i>
        Carta Digital &copy; {{ date('Y') }} {{ $companyName }}
    </footer>

    <button type="button" class="back-to-top" id="backToTop" aria-label="Volver arriba">
        <i class="bi bi-chevron-up"></i>
    </button>

    <script>
        (() => {
            'use strict';

            /* ── Scrollspy: resalta la pestaña de la sección visible ── */
            const links = [...document.querySelectorAll('.category-pill')];
            const sections = [...document.querySelectorAll('section[id]')];
            const linkBySection = new Map(
                links.map(l => [l.getAttribute('href').slice(1), l])
            );

            if ('IntersectionObserver' in window && sections.length) {
                const spy = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting && linkBySection.has(entry.target.id)) {
                            links.forEach(l => l.classList.toggle('active', l === linkBySection.get(entry.target.id)));
                        }
                    });
                }, { rootMargin: '-15% 0px -70% 0px' });
                sections.forEach(s => spy.observe(s));
            }

            /* ── Búsqueda en vivo ── */
            const input = document.getElementById('menuSearch');
            const noResults = document.getElementById('noResults');

            input.addEventListener('input', () => {
                const q = input.value.trim().toLowerCase();

                document.querySelectorAll('.product-card').forEach(card => {
                    const hay = (card.dataset.name + ' ' + (card.dataset.desc || '')).toLowerCase();
                    card.classList.toggle('d-none', q !== '' && !hay.includes(q));
                });

                document.querySelectorAll('section').forEach(sec => {
                    const visible = [...sec.querySelectorAll('.product-card')].some(c => !c.classList.contains('d-none'));
                    sec.classList.toggle('d-none', !visible);
                });

                links.forEach(p => {
                    const sec = document.getElementById(p.getAttribute('href').slice(1));
                    p.classList.toggle('d-none', sec ? sec.classList.contains('d-none') : false);
                });

                const total = document.querySelectorAll('.product-card:not(.d-none)').length;
                noResults.classList.toggle('d-none', total > 0);
            });

            /* ── Logo rectangular: ocupa el espacio del hero ── */
            const heroLogo = document.getElementById('heroLogo');
            if (heroLogo) {
                const apply = () => {
                    const w = heroLogo.naturalWidth, h = heroLogo.naturalHeight;
                    if (w && h && h < w * 0.92) {
                        document.getElementById('menuHero')?.classList.add('brand-rect');
                    }
                };
                if (heroLogo.complete) apply();
                else heroLogo.addEventListener('load', apply);
            }

            /* ── Botón volver arriba ── */
            const topBtn = document.getElementById('backToTop');
            window.addEventListener('scroll', () => {
                topBtn.classList.toggle('show', window.scrollY > 600);
            }, { passive: true });
            topBtn.addEventListener('click', () => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        })();
    </script>
</body>
</html>
