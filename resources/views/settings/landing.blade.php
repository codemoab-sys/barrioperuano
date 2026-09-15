@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-0"><i class="bi bi-globe2 me-2"></i>Sitio Web Informativo</h2>
            <p class="text-muted mb-0">Edita textos, imágenes y muestra/oculta las secciones de la página /inicio</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('landing.index') }}" target="_blank" rel="noopener" class="btn btn-outline-secondary fw-bold">
                <i class="bi bi-display me-1"></i> Ver Sitio Web
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success d-flex align-items-center shadow-sm rounded-4">
            <i class="bi bi-check-circle-fill fs-4 me-3"></i>
            <div><strong>¡Éxito!</strong> {{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form action="{{ route('landing.settings.update') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="accordion" id="webAccordion">
        <div class="accordion-item mb-3 border rounded-4 shadow-sm overflow-hidden">
            <h2 class="accordion-header">
                <button class="accordion-button fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#colAppearance">
                    <i class="bi bi-palette-fill me-2 text-primary"></i> Apariencia de la web
                </button>
            </h2>
            <div id="colAppearance" class="accordion-collapse collapse show" data-bs-parent="#webAccordion">
                <div class="accordion-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Color de fondo de página</label>
                            <div class="input-group">
                                <input type="color" name="web_page_bg" class="form-control form-control-color" value="{{ $settings['web_page_bg'] ?? '#ffffff' }}">
                                <span class="input-group-text">Fondo general</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Color de cabecera</label>
                            <div class="input-group">
                                <input type="color" name="web_header_bg" class="form-control form-control-color" value="{{ $settings['web_header_bg'] ?? '#ffffff' }}">
                                <span class="input-group-text">Barra superior</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Color del texto del nombre</label>
                            <div class="input-group">
                                <input type="color" name="brand_text_color" class="form-control form-control-color" value="{{ $settings['brand_text_color'] ?? '#1e1e2d' }}">
                                <span class="input-group-text">Logo + nombre</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

            {{-- ═══════════ HERO ═══════════ --}}
            <div class="accordion-item mb-3 border rounded-4 shadow-sm overflow-hidden">
                <h2 class="accordion-header">
                    <button class="accordion-button fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#colHero">
                        <i class="bi bi-house-heart-fill me-2 text-primary"></i> Hero (Portada)
                    </button>
                </h2>
                <div id="colHero" class="accordion-collapse collapse show" data-bs-parent="#webAccordion">
                    <div class="accordion-body">
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold">Título principal</label>
                                <input type="text" name="web_hero_title" class="form-control" value="{{ $settings['web_hero_title'] ?? '' }}" placeholder="Bienvenido a ...">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold">Texto del botón (Carta Digital)</label>
                                <input type="text" name="web_hero_btn_text" class="form-control" value="{{ $settings['web_hero_btn_text'] ?? '' }}" placeholder="Ver Carta Digital">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold">Subtítulo</label>
                                <textarea name="web_hero_subtitle" class="form-control" rows="2" placeholder="Frase corta que acompaña al título">{{ $settings['web_hero_subtitle'] ?? '' }}</textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold">Imagenes del slider (Hero) - hasta 4</label>
                                <small class="text-muted d-block mb-2">Sube las imagenes que rotaran en el banner. Recomendado: 1600x900px.</small>
                                <div class="row g-3">
                                    @foreach([1,2,3,4] as $i)
                                        @php $img = $settings['web_slider_'.$i] ?? ''; @endphp
                                        <div class="col-12 col-md-6">
                                            <div class="p-3 border rounded-3 bg-light h-100">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="fw-bold small">Slider {{ $i }}</span>
                                                    @if($img)
                                                        <div class="d-flex align-items-center gap-1">
                                                            <input class="form-check-input" type="checkbox" name="web_slider_remove_{{ $i }}" id="removeSlider{{ $i }}" value="1">
                                                            <label class="form-check-label small text-danger mb-0" for="removeSlider{{ $i }}">Quitar</label>
                                                        </div>
                                                    @endif
                                                </div>
                                                <input type="file" name="web_slider_{{ $i }}" class="form-control form-control-sm" accept="image/*">
                                                <div class="mt-2 text-center">
                                                    @if($img)
                                                        <img src="{{ route('uploaded-assets.show', ['path' => 'web/'.$img]) }}" class="img-thumbnail shadow-sm" style="max-height:70px; object-fit:cover;">
                                                    @else
                                                        <span class="text-muted small">Sin imagen</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="col-12 d-flex align-items-center gap-2">
                                <input type="hidden" name="web_show_hero" value="0">
                                <input type="checkbox" name="web_show_hero" value="1" class="form-check-input" id="showHero" {{ ($settings['web_show_hero'] ?? '1') == '1' ? 'checked' : '' }}>
                                <label class="form-label fw-bold mb-0" for="showHero">Mostrar sección Hero</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══════════ NOSOTROS ═══════════ --}}
            <div class="accordion-item mb-3 border rounded-4 shadow-sm overflow-hidden">
                <h2 class="accordion-header">
                    <button class="accordion-button fw-bold collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#colAbout">
                        <i class="bi bi-shop-window me-2 text-primary"></i> Nosotros
                    </button>
                </h2>
                <div id="colAbout" class="accordion-collapse collapse" data-bs-parent="#webAccordion">
                    <div class="accordion-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Título</label>
                                <input type="text" name="web_about_title" class="form-control" value="{{ $settings['web_about_title'] ?? '' }}" placeholder="Nuestra historia">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Badge (años de experiencia)</label>
                                <input type="text" name="web_about_badge" class="form-control" value="{{ $settings['web_about_badge'] ?? '' }}" placeholder="Ej: 10 años" maxlength="12">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold">Descripción</label>
                                <textarea name="web_about_text" class="form-control" rows="3" placeholder="Texto sobre el restaurante">{{ $settings['web_about_text'] ?? '' }}</textarea>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label fw-bold">Imagen</label>
                                <input type="file" name="web_about_image" class="form-control" accept="image/*">
                            </div>
                            <div class="col-md-4 d-flex align-items-center justify-content-center">
                                @if(!empty($settings['web_about_image']))
                                    <img src="{{ route('uploaded-assets.show', ['path' => 'web/'.$settings['web_about_image']]) }}" class="img-thumbnail shadow-sm" style="max-height:90px; object-fit:cover;">
                                @endif
                            </div>
                            <div class="col-12 d-flex align-items-center gap-2">
                                <input type="hidden" name="web_show_about" value="0">
                                <input type="checkbox" name="web_show_about" value="1" class="form-check-input" id="showAbout" {{ ($settings['web_show_about'] ?? '1') == '1' ? 'checked' : '' }}>
                                <label class="form-label fw-bold mb-0" for="showAbout">Mostrar sección Nosotros</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══════════ SERVICIOS ═══════════ --}}
            <div class="accordion-item mb-3 border rounded-4 shadow-sm overflow-hidden">
                <h2 class="accordion-header">
                    <button class="accordion-button fw-bold collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#colFeatures">
                        <i class="bi bi-grid-fill me-2 text-primary"></i> Servicios (4 tarjetas)
                    </button>
                </h2>
                <div id="colFeatures" class="accordion-collapse collapse" data-bs-parent="#webAccordion">
                    <div class="accordion-body">
                        @foreach($features as $i => $feature)
                            <div class="row g-2 align-items-end mb-3 p-3 rounded-3 bg-light">
                                <div class="col-md-3">
                                    <label class="form-label fw-bold small">Icono (bootstrap)</label>
                                    <input type="text" name="web_features[{{ $i }}][icon]" class="form-control form-control-sm" value="{{ $feature['icon'] }}" placeholder="bi-bicycle">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold small">Título</label>
                                    <input type="text" name="web_features[{{ $i }}][title]" class="form-control form-control-sm" value="{{ $feature['title'] }}">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label fw-bold small">Descripción</label>
                                    <input type="text" name="web_features[{{ $i }}][text]" class="form-control form-control-sm" value="{{ $feature['text'] }}">
                                </div>
                            </div>
                        @endforeach
                        <small class="text-muted">Iconos disponibles: <code>bi-bicycle</code>, <code>bi-calendar-check-fill</code>, <code>bi-people-fill</code>, <code>bi-qr-code-scan</code>, <code>bi-egg-fried</code>, <code>bi-fire</code>...</small>
                        <div class="mt-3 d-flex align-items-center gap-2">
                            <input type="hidden" name="web_show_features" value="0">
                            <input type="checkbox" name="web_show_features" value="1" class="form-check-input" id="showFeatures" {{ ($settings['web_show_features'] ?? '1') == '1' ? 'checked' : '' }}>
                            <label class="form-label fw-bold mb-0" for="showFeatures">Mostrar sección Servicios</label>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══════════ CÓMO PEDIR ═══════════ --}}
            <div class="accordion-item mb-3 border rounded-4 shadow-sm overflow-hidden">
                <h2 class="accordion-header">
                    <button class="accordion-button fw-bold collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#colSteps">
                        <i class="bi bi-lightning-charge-fill me-2 text-primary"></i> Cómo pedir (pasos)
                    </button>
                </h2>
                <div id="colSteps" class="accordion-collapse collapse" data-bs-parent="#webAccordion">
                    <div class="accordion-body">
                        @foreach($steps as $i => $step)
                            <div class="row g-2 align-items-end mb-3 p-3 rounded-3 bg-light">
                                <div class="col-md-3">
                                    <label class="form-label fw-bold small">Icono</label>
                                    <input type="text" name="web_steps[{{ $i }}][icon]" class="form-control form-control-sm" value="{{ $step['icon'] }}" placeholder="bi-1-circle-fill">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold small">Título</label>
                                    <input type="text" name="web_steps[{{ $i }}][title]" class="form-control form-control-sm" value="{{ $step['title'] }}">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label fw-bold small">Descripción</label>
                                    <input type="text" name="web_steps[{{ $i }}][text]" class="form-control form-control-sm" value="{{ $step['text'] }}">
                                </div>
                            </div>
                        @endforeach
                        <div class="d-flex align-items-center gap-2">
                            <input type="hidden" name="web_show_steps" value="0">
                            <input type="checkbox" name="web_show_steps" value="1" class="form-check-input" id="showSteps" {{ ($settings['web_show_steps'] ?? '1') == '1' ? 'checked' : '' }}>
                            <label class="form-label fw-bold mb-0" for="showSteps">Mostrar sección Cómo pedir</label>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══════════ CONTACTO ═══════════ --}}
            <div class="accordion-item mb-3 border rounded-4 shadow-sm overflow-hidden">
                <h2 class="accordion-header">
                    <button class="accordion-button fw-bold collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#colContact">
                        <i class="bi bi-geo-alt-fill me-2 text-primary"></i> Contacto
                    </button>
                </h2>
                <div id="colContact" class="accordion-collapse collapse" data-bs-parent="#webAccordion">
                    <div class="accordion-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Título</label>
                                <input type="text" name="web_contact_title" class="form-control" value="{{ $settings['web_contact_title'] ?? '' }}" placeholder="Visítanos">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Teléfono</label>
                                <input type="text" name="web_contact_phone" class="form-control" value="{{ $settings['web_contact_phone'] ?? ($settings['company_phone'] ?? '') }}" placeholder="999-888-777">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">WhatsApp</label>
                                <input type="text" name="web_contact_whatsapp" class="form-control" value="{{ $settings['web_contact_whatsapp'] ?? '' }}" placeholder="+51 999 888 777">
                                <small class="text-muted">Si lo dejas vacío usa el teléfono.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Dirección</label>
                                <input type="text" name="web_contact_address" class="form-control" value="{{ $settings['web_contact_address'] ?? ($settings['company_address'] ?? '') }}" placeholder="Av. Principal 123">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Horario</label>
                                <input type="text" name="web_contact_hours" class="form-control" value="{{ $settings['web_contact_hours'] ?? '' }}" placeholder="Lun – Dom: 12:00 pm – 10:00 pm">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Email</label>
                                <input type="email" name="web_contact_email" class="form-control" value="{{ $settings['web_contact_email'] ?? '' }}" placeholder="contacto@restaurante.com">
                            </div>
                            <div class="col-12 d-flex align-items-center gap-2">
                                <input type="hidden" name="web_show_contact" value="0">
                                <input type="checkbox" name="web_show_contact" value="1" class="form-check-input" id="showContact" {{ ($settings['web_show_contact'] ?? '1') == '1' ? 'checked' : '' }}>
                                <label class="form-label fw-bold mb-0" for="showContact">Mostrar sección Contacto</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══════════ FOOTER ═══════════ --}}
            <div class="accordion-item mb-3 border rounded-4 shadow-sm overflow-hidden">
                <h2 class="accordion-header">
                    <button class="accordion-button fw-bold collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#colFooter">
                        <i class="bi bi-window-stack me-2 text-primary"></i> Pie de página
                    </button>
                </h2>
                <div id="colFooter" class="accordion-collapse collapse" data-bs-parent="#webAccordion">
                    <div class="accordion-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-bold">Texto del footer</label>
                                <input type="text" name="web_footer_text" class="form-control" value="{{ $settings['web_footer_text'] ?? '' }}" placeholder="© 2026 Restaurante. Todos los derechos reservados.">
                                <small class="text-muted">Si lo dejas vacío se usa el copyright automático.</small>
                            </div>
                            <div class="col-12 d-flex align-items-center gap-2">
                                <input type="hidden" name="web_show_footer" value="0">
                                <input type="checkbox" name="web_show_footer" value="1" class="form-check-input" id="showFooter" {{ ($settings['web_show_footer'] ?? '1') == '1' ? 'checked' : '' }}>
                                <label class="form-label fw-bold mb-0" for="showFooter">Mostrar pie de página</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
            <a href="{{ route('landing.index') }}" target="_blank" rel="noopener" class="btn btn-outline-secondary fw-bold">
                <i class="bi bi-display me-1"></i> Vista previa
            </a>
            <button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm">
                <i class="bi bi-save me-2"></i> Guardar Cambios
            </button>
        </div>
    </form>
</div>

<style>
    [data-theme="dark"] .accordion-item,
    [data-theme="dark"] .accordion-body,
    [data-theme="dark"] .accordion-button,
    [data-theme="dark"] .bg-light,
    [data-theme="dark"] .form-control,
    [data-theme="dark"] .form-select,
    [data-theme="dark"] .input-group-text,
    [data-theme="dark"] .border,
    [data-theme="dark"] .rounded-3,
    [data-theme="dark"] .rounded-4 {
        background-color: #111827 !important;
        color: #e5e7eb !important;
        border-color: rgba(148, 163, 184, 0.28) !important;
    }

    [data-theme="dark"] .accordion-button {
        background: #111827 !important;
        color: #f9fafb !important;
        box-shadow: none !important;
    }

    [data-theme="dark"] .accordion-button:not(.collapsed) {
        background: #1f2937 !important;
        color: #fff !important;
    }

    [data-theme="dark"] .form-control,
    [data-theme="dark"] .form-select,
    [data-theme="dark"] .input-group-text {
        background-color: #0f172a !important;
        border-color: rgba(148, 163, 184, 0.28) !important;
        color: #f3f4f6 !important;
    }

    [data-theme="dark"] .form-control::placeholder,
    [data-theme="dark"] .text-muted,
    [data-theme="dark"] small,
    [data-theme="dark"] .text-secondary,
    [data-theme="dark"] .text-primary,
    [data-theme="dark"] .text-dark {
        color: #cbd5e1 !important;
    }

    [data-theme="dark"] .form-check-input {
        background-color: #0f172a;
        border-color: rgba(148, 163, 184, 0.35);
    }

    [data-theme="dark"] .btn-outline-secondary {
        background: rgba(255,255,255,0.03);
        border-color: rgba(148, 163, 184, 0.35);
        color: #f3f4f6;
    }
</style>
@endsection
