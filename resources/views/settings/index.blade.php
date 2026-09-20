@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold text-dark mb-0"><i class="bi bi-gear-fill me-2"></i>Configuración</h2>
                    <p class="text-muted mb-0">Personaliza la identidad y región de tu negocio</p>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">

                    <form action="{{ route('settings.update') }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        @if($errors->any())
                            <div class="alert alert-danger border-0 shadow-sm mb-4" role="alert">
                                <div class="d-flex align-items-start gap-2">
                                    <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                                    <div>
                                        <div class="fw-bold mb-1">No se pudo guardar la configuración</div>
                                        <ul class="mb-0 ps-3">
                                            @foreach($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <h5 class="fw-bold text-primary mb-3">Datos de la Empresa</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold">Nombre del Restaurante</label>
                                <input type="text" name="company_name" class="form-control" value="{{ old('company_name', $settings['company_name'] ?? ($settings['sunat_nombre_comercial'] ?? $settings['sunat_razon_social'] ?? '')) }}" placeholder="Ej: Restaurante Vito">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold">Teléfono / Pedidos</label>
                                <input type="text" name="company_phone" class="form-control" value="{{ old('company_phone', $settings['company_phone'] ?? '') }}" placeholder="Ej: 999-888-777">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold">Dirección</label>
                                <input type="text" name="company_address" class="form-control" value="{{ old('company_address', $settings['company_address'] ?? ($settings['sunat_direccion_fiscal'] ?? '')) }}" placeholder="Ej: Av. Principal 123, Ica">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold">Mensaje de Bienvenida</label>
                                <input type="text" name="welcome_message" class="form-control" value="{{ old('welcome_message', $settings['welcome_message'] ?? 'Bienvenido de vuelta') }}" placeholder="Ej: Bienvenido" maxlength="100">
                                <small class="text-muted">Texto que aparece en la barra superior de todas las páginas.</small>
                            </div>
                        </div>

                        <hr class="text-muted opacity-25">

                        <h5 class="fw-bold text-primary mb-3">Región y Sistema</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold">Moneda</label>
                                <select name="currency_symbol" class="form-select">
                                    <option value="S/" {{ ($settings['currency_symbol'] ?? '') == 'S/' ? 'selected' : '' }}>S/ (Soles)</option>
                                    <option value="$" {{ ($settings['currency_symbol'] ?? '') == '$' ? 'selected' : '' }}>$ (Dólares)</option>
                                    <option value="€" {{ ($settings['currency_symbol'] ?? '') == '€' ? 'selected' : '' }}>€ (Euros)</option>
                                </select>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-bold">Mensaje Pie de Ticket</label>
                                <input type="text" name="ticket_footer" class="form-control" value="{{ $settings['ticket_footer'] ?? '¡Gracias por su visita!' }}">
                            </div>
                        </div>

                        <hr class="text-muted opacity-25">

                        <h5 class="fw-bold text-primary mb-3">Logotipo</h5>
                        <div class="row align-items-center mb-4">
                            <div class="col-md-8">
                                <label class="form-label">Subir Logo (Ticket y Sistema)</label>
                                <input type="file" name="company_logo" class="form-control @error('company_logo') is-invalid @enderror" accept="image/*">
                                @error('company_logo')
                                    <div class="invalid-feedback d-block mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4 text-center">
                                @if(isset($settings['company_logo']) && $settings['company_logo'])
                                    <img src="{{ route('uploaded-assets.show', ['path' => $settings['company_logo']]) }}" class="img-thumbnail" style="max-height: 80px;">
                                @else
                                    <div class="p-3 border rounded bg-light text-muted"><i class="bi bi-image fs-1"></i></div>
                                @endif
                            </div>
                        </div>

                        <hr class="text-muted opacity-25">

                        @php
                            $colorDefaults = [
                                'color_primary'         => '#ff8c00',
                                'color_primary_hover'   => '#e07b00',
                                'color_primary_soft'    => '#fff4e6',
                                'color_sidebar_bg'      => '#2d1b5e',
                                'color_sidebar_active'  => '#ff8c00',
                                'brand_text_color'      => '#ffffff',
                            ];
                        @endphp

                        {{-- ═══════════════════════════════════════════════════════════════ --}}
                        {{--    COLORES DE MARCA (combinan con el logo)                     --}}
                        {{-- ═══════════════════════════════════════════════════════════════ --}}
                        <h5 class="fw-bold text-primary mb-1">
                            <i class="bi bi-palette-fill me-2"></i>Colores de Marca
                        </h5>
                        <p class="text-muted small mb-3">Elige los colores que combinan con tu logo. Se aplican al sistema, carta digital y web informativa.</p>

                        <div class="row g-3 mb-3" id="colorSettings">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Color Principal</label>
                                <div class="input-group">
                                    <input type="color" name="color_primary" class="form-control form-control-color color-input"
                                           value="{{ $settings['color_primary'] ?? $colorDefaults['color_primary'] }}"
                                           data-default="{{ $colorDefaults['color_primary'] }}">
                                    <span class="input-group-text color-hex">{{ $settings['color_primary'] ?? $colorDefaults['color_primary'] }}</span>
                                </div>
                                <small class="text-muted">Botones, acentos, gráficos.</small>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold">Color Principal (hover)</label>
                                <div class="input-group">
                                    <input type="color" name="color_primary_hover" class="form-control form-control-color color-input"
                                           value="{{ $settings['color_primary_hover'] ?? $colorDefaults['color_primary_hover'] }}"
                                           data-default="{{ $colorDefaults['color_primary_hover'] }}">
                                    <span class="input-group-text color-hex">{{ $settings['color_primary_hover'] ?? $colorDefaults['color_primary_hover'] }}</span>
                                </div>
                                <small class="text-muted">Versión más oscura al pasar el mouse.</small>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold">Color Principal (suave)</label>
                                <div class="input-group">
                                    <input type="color" name="color_primary_soft" class="form-control form-control-color color-input"
                                           value="{{ $settings['color_primary_soft'] ?? $colorDefaults['color_primary_soft'] }}"
                                           data-default="{{ $colorDefaults['color_primary_soft'] }}">
                                    <span class="input-group-text color-hex">{{ $settings['color_primary_soft'] ?? $colorDefaults['color_primary_soft'] }}</span>
                                </div>
                                <small class="text-muted">Fondos claros e iconos.</small>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold">Color Sidebar</label>
                                <div class="input-group">
                                    <input type="color" name="color_sidebar_bg" class="form-control form-control-color color-input"
                                           value="{{ $settings['color_sidebar_bg'] ?? $colorDefaults['color_sidebar_bg'] }}"
                                           data-default="{{ $colorDefaults['color_sidebar_bg'] }}">
                                    <span class="input-group-text color-hex">{{ $settings['color_sidebar_bg'] ?? $colorDefaults['color_sidebar_bg'] }}</span>
                                </div>
                                <small class="text-muted">Fondo de la barra lateral.</small>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold">Color Sidebar (activo)</label>
                                <div class="input-group">
                                    <input type="color" name="color_sidebar_active" class="form-control form-control-color color-input"
                                           value="{{ $settings['color_sidebar_active'] ?? $colorDefaults['color_sidebar_active'] }}"
                                           data-default="{{ $colorDefaults['color_sidebar_active'] }}">
                                    <span class="input-group-text color-hex">{{ $settings['color_sidebar_active'] ?? $colorDefaults['color_sidebar_active'] }}</span>
                                </div>
                                <small class="text-muted">Resalta el ítem seleccionado.</small>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold">Color texto de marca</label>
                                <div class="input-group">
                                    <input type="color" name="brand_text_color" class="form-control form-control-color color-input"
                                           value="{{ $settings['brand_text_color'] ?? $colorDefaults['brand_text_color'] }}"
                                           data-default="{{ $colorDefaults['brand_text_color'] }}">
                                    <span class="input-group-text color-hex">{{ $settings['brand_text_color'] ?? $colorDefaults['brand_text_color'] }}</span>
                                </div>
                                <small class="text-muted">Texto del nombre junto al logo.</small>
                            </div>
                        </div>

                        <div class="d-flex gap-2 mb-4">
                            <button type="button" id="resetColors" class="btn btn-outline-secondary fw-bold">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Colores por defecto
                            </button>
                            <button type="button" id="applySuggested" class="btn fw-bold" style="background:#F4B41A;color:#171717;border:1px solid #D99A00;">
                                <i class="bi bi-stars me-1"></i> Sugerido (Barrio Peruano)
                            </button>
                        </div>

                        <hr class="text-muted opacity-25">

                        {{-- ═══════════════════════════════════════════════════════════════ --}}
                        {{--    FACTURACIÓN ELECTRÓNICA — NUBEFACT (PERÚ)                    --}}
                        {{-- ═══════════════════════════════════════════════════════════════ --}}
                        <h5 class="fw-bold text-danger mb-3">
                            <i class="bi bi-receipt-cutoff me-2"></i>Facturación Electrónica · NubeFact
                        </h5>

                        @php
                            $ambiente = $settings['sunat_environment'] ?? 'beta';
                        @endphp

                        <div class="alert {{ $ambiente === 'produccion' ? 'alert-danger' : 'alert-warning' }} py-2 mb-3">
                            <strong>Ambiente actual:</strong>
                            {{ $ambiente === 'produccion' ? 'PRODUCCIÓN (emisión real)' : 'BETA (pruebas)' }}
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">RUC del emisor <span class="text-danger">*</span></label>
                                <input type="text" name="sunat_ruc" class="form-control"
                                       value="{{ $settings['sunat_ruc'] ?? '' }}"
                                       maxlength="11" pattern="\d{11}" placeholder="20000000001">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-bold">Razón social <span class="text-danger">*</span></label>
                                <input type="text" name="sunat_razon_social" class="form-control"
                                       value="{{ $settings['sunat_razon_social'] ?? '' }}"
                                       placeholder="MI EMPRESA SAC">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Nombre comercial</label>
                                <input type="text" name="sunat_nombre_comercial" class="form-control"
                                       value="{{ $settings['sunat_nombre_comercial'] ?? '' }}">
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-bold">Dirección fiscal</label>
                                <input type="text" name="sunat_direccion_fiscal" class="form-control"
                                       value="{{ $settings['sunat_direccion_fiscal'] ?? '' }}"
                                       placeholder="AV. PRINCIPAL 123">
                            </div>

                            <div class="col-md-2">
                                <label class="form-label fw-bold">Ubigeo</label>
                                <input type="text" name="sunat_ubigeo" class="form-control"
                                       value="{{ $settings['sunat_ubigeo'] ?? '150101' }}"
                                       maxlength="6" placeholder="150101">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Departamento</label>
                                <input type="text" name="sunat_departamento" class="form-control"
                                       value="{{ $settings['sunat_departamento'] ?? 'LIMA' }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Provincia</label>
                                <input type="text" name="sunat_provincia" class="form-control"
                                       value="{{ $settings['sunat_provincia'] ?? 'LIMA' }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-bold">Distrito</label>
                                <input type="text" name="sunat_distrito" class="form-control"
                                       value="{{ $settings['sunat_distrito'] ?? 'LIMA' }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-bold">Urbanización</label>
                                <input type="text" name="sunat_urbanizacion" class="form-control"
                                       value="{{ $settings['sunat_urbanizacion'] ?? '-' }}">
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Ambiente <span class="text-danger">*</span></label>
                                <select name="sunat_environment" class="form-select fw-bold">
                                    <option value="beta" {{ $ambiente === 'beta' ? 'selected' : '' }}>
                                        BETA (Pruebas)
                                    </option>
                                    <option value="produccion" {{ $ambiente === 'produccion' ? 'selected' : '' }}>
                                        PRODUCCIÓN (Real)
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">IGV (%)</label>
                                @php $igvRate = (float) ($settings['sunat_igv_rate'] ?? 10.5); @endphp
                                <select name="sunat_igv_rate" class="form-select">
                                    <option value="18" {{ $igvRate === 18.0 ? 'selected' : '' }}>18%</option>
                                    <option value="10.5" {{ $igvRate === 10.5 ? 'selected' : '' }}>10.5%</option>
                                    <option value="10" {{ $igvRate === 10.0 ? 'selected' : '' }}>10%</option>
                                    <option value="4" {{ $igvRate === 4.0 ? 'selected' : '' }}>4%</option>
                                </select>
                            </div>
                        </div>

                        {{-- ═══ CREDENCIALES NUBEFACT ═══ --}}
                        <h6 class="fw-bold text-primary mb-3 mt-4">
                            <i class="bi bi-key-fill me-2"></i>Credenciales NubeFact
                        </h6>
                        <p class="text-muted small mb-3">
                            Obtén tu RUTA y TOKEN en <a href="https://www.nubefact.com/login" target="_blank">nubefact.com</a> → API (Integración).
                        </p>

                        <div class="row g-3 mb-3">
                            <div class="col-md-8">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-link-45deg"></i> RUTA NubeFact <span class="text-danger">*</span>
                                </label>
                                <input type="url" name="nubefact_ruta" class="form-control"
                                       value="{{ $settings['nubefact_ruta'] ?? '' }}"
                                       placeholder="https://api.nubefact.com/api/v1/48239908-7ae7-4353-824d-071765d4">
                                <small class="text-muted">
                                    ONLINE: <code>https://api.nubefact.com/api/v1/{uuid}</code><br>
                                    OFFLINE: <code>http://localhost:8000/api/v1/{uuid}</code>
                                </small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-shield-lock"></i> TOKEN NubeFact <span class="text-danger">*</span>
                                </label>
                                <input type="password" name="nubefact_token" class="form-control"
                                        value=""
                                        placeholder="{{ !empty($settings['nubefact_token'] ?? '') ? 'Token registrado; escribir para reemplazar' : 'Ingresa el TOKEN de NubeFact' }}"
                                        autocomplete="new-password">
                                    <small class="text-muted">
                                     {{ !empty($settings['nubefact_token'] ?? '') ? 'Token registrado de forma segura. Déjalo vacío para conservarlo.' : 'Se guardará cifrado.' }}
                                    </small>
                            </div>
                        </div>

                        @php
                            $tieneNubeFact = !empty($settings['nubefact_ruta']) && !empty($settings['nubefact_token']);
                        @endphp
                        <div class="alert {{ $tieneNubeFact ? 'alert-success' : 'alert-secondary' }} py-2 mb-3">
                            @if($tieneNubeFact)
                                <i class="bi bi-check-circle-fill"></i>
                                <strong>NubeFact configurado.</strong> RUTA y TOKEN establecidos.
                            @else
                                <i class="bi bi-exclamation-triangle"></i>
                                <strong>Sin configurar.</strong> Ingresa tu RUTA y TOKEN de NubeFact para emitir comprobantes electrónicos.
                            @endif
                        </div>

                        {{-- ═══ SMTP (Envío de comprobantes) ═══ --}}
                        <h6 class="fw-bold text-primary mb-3 mt-4">
                            <i class="bi bi-envelope me-2"></i>Correo Electrónico (SMTP)
                        </h6>
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-body">
                                <p class="small text-muted mb-3">
                                    Servidor: <code>mail.moabcode.com:465 (SSL)</code> ·
                                    Usuario: <code>barrioperuano@moabcode.com</code>
                                </p>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Contraseña SMTP <span class="text-danger">*</span></label>
                                    <input type="password" name="smtp_password" class="form-control"
                                           placeholder="{{ !empty($settings['smtp_password'] ?? '') ? 'Contraseña registrada; escribir para reemplazar' : 'Ingresa la contraseña del correo' }}">
                                    <small class="text-muted">
                                        {{ !empty($settings['smtp_password'] ?? '') ? 'Contraseña configurada. Déjalo vacío para conservarla.' : 'Se usará para enviar comprobantes por correo.' }}
                                    </small>
                                </div>
                            </div>
                        </div>

                        {{-- ═══ SERIES DE COMPROBANTES ═══ --}}
                        <h6 class="fw-bold text-primary mb-3 mt-4">
                            <i class="bi bi-list-ol me-2"></i>Series de Comprobantes
                        </h6>

                        <div class="alert alert-warning alert-permanent py-3 mb-3 border border-warning">
                            <h6 class="alert-heading fw-bold mb-2">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i> IMPORTANTE: Las series deben ser IGUALES a las de tu cuenta NubeFact.
                            </h6>
                            <p class="mb-1"><strong>Para este sistema migrado, registra series nuevas en NubeFact antes de emitir.</strong> Ejemplo: Factura <code>F002</code> y Boleta <code>B002</code>.</p>
                            <p class="mb-1">Una serie nueva comenzará obligatoriamente con el correlativo <strong>1</strong>. No reutilices una serie del sistema anterior.</p>
                            <p class="mb-1">Si conservas una serie ya usada, debes mantener su último correlativo real; el sistema lo preserva al guardar la configuración.</p>
                            <p class="mb-0"><small>Verifica tus series en: <a href="https://www.nubefact.com/login" target="_blank">nubefact.com</a> → Datos de la Empresa → Series asignadas.</small></p>
                        </div>

                        <div class="alert alert-danger py-2 mb-3 small">
                            <i class="bi bi-shield-exclamation me-1"></i>
                            Al cambiar a <strong>PRODUCCIÓN</strong>, debes usar series nuevas para separar las pruebas BETA.
                            El primer comprobante de cada serie productiva comenzará en el correlativo <strong>1</strong>.
                        </div>

                        @foreach($areas as $area)
                            @php
                                $areaSeries = \App\Models\DocumentSeries::where('area_id', $area->id)->where('is_active', true)->get()->keyBy('document_type');
                                $seriesFields = [
                                    'factura' => ['label' => 'Serie Factura', 'placeholder' => 'F001'],
                                    'boleta' => ['label' => 'Serie Boleta', 'placeholder' => 'B001'],
                                    'nota_credito_factura' => ['label' => 'Serie NC Factura', 'placeholder' => 'F001'],
                                    'nota_credito_boleta' => ['label' => 'Serie NC Boleta', 'placeholder' => 'B001'],
                                    'nota_debito_factura' => ['label' => 'Serie ND Factura', 'placeholder' => 'F001'],
                                    'nota_debito_boleta' => ['label' => 'Serie ND Boleta', 'placeholder' => 'B001'],
                                ];
                            @endphp
                            <div class="card border shadow-sm mb-3">
                                <div class="card-header bg-light fw-bold">
                                    <i class="bi bi-shop me-2"></i>{{ $area->name }}
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        @foreach($seriesFields as $type => $field)
                                            @php $series = $areaSeries->get($type); @endphp
                                            <div class="col-md-3">
                                                <label class="form-label fw-bold">{{ $field['label'] }} <span class="text-danger">*</span></label>
                                                <input type="text" name="area_series[{{ $area->id }}][{{ $type }}]" class="form-control" value="{{ $series?->serie ?? '' }}" maxlength="4" placeholder="{{ $field['placeholder'] }}" required>
                                                <small class="text-muted">Próximo: {{ $series ? $series->serie . '-' . ($series->last_number + 1) : '1' }}</small>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        @if($areas->isEmpty())
                            <div class="alert alert-info">Crea al menos una zona para configurar sus series de comprobantes.</div>
                        @endif

                        <div class="mt-5 d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary px-5 fw-bold shadow" id="saveSettingsButton">
                                <i class="bi bi-save me-2"></i> Guardar Configuración
                            </button>
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
    document.addEventListener('DOMContentLoaded', function () {
        @if(session('success'))
            Swal.fire({
                icon: 'success',
                title: 'Configuración guardada',
                text: @json(session('success')),
                timer: 2200,
                showConfirmButton: false
            });
        @endif

        @if(session('error'))
            Swal.fire({
                icon: 'error',
                title: 'No se pudo guardar',
                text: @json(session('error')),
                confirmButtonColor: '#dc3545'
            });
        @endif

        // ── Muestra el valor HEX junto a cada selector de color ──
        document.querySelectorAll('.color-input').forEach(function (input) {
            const hex = input.closest('.input-group')?.querySelector('.color-hex');
            const sync = () => { if (hex) hex.textContent = input.value.toUpperCase(); };
            input.addEventListener('input', sync);
        });

        // ── "Colores por defecto": restaura los valores originales ──
        const resetBtn = document.getElementById('resetColors');
        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                document.querySelectorAll('.color-input').forEach(function (input) {
                    const def = input.getAttribute('data-default');
                    if (def) {
                        input.value = def;
                        const hex = input.closest('.input-group')?.querySelector('.color-hex');
                        if (hex) hex.textContent = def.toUpperCase();
                    }
                });
            });
        }

        // ── "Sugerido (Barrio Peruano)": aplica la paleta profesional sugerida ──
        const suggestedBtn = document.getElementById('applySuggested');
        if (suggestedBtn) {
            suggestedBtn.addEventListener('click', function () {
                const suggested = {
                    'color_primary':        '#F4B41A',
                    'color_primary_hover':  '#D99A00',
                    'color_primary_soft':   '#FEF9E7',
                    'color_sidebar_bg':     '#171717',
                    'color_sidebar_active': '#F4B41A',
                    'brand_text_color':     '#FFFFFF',
                };
                document.querySelectorAll('.color-input').forEach(function (input) {
                    const name = input.getAttribute('name');
                    if (suggested[name]) {
                        input.value = suggested[name];
                        const hex = input.closest('.input-group')?.querySelector('.color-hex');
                        if (hex) hex.textContent = suggested[name].toUpperCase();
                    }
                });
            });
        }

        // ── Guardar configuración: confirmación SweetAlert2 + spinner ──
        const form = document.querySelector('form[action="{{ route('settings.update') }}"]');
        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();

                if (form.dataset.submitting === 'true') return;

                Swal.fire({
                    title: '¿Guardar configuración?',
                    text: 'Se aplicarán los cambios en todo el sistema.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#ff8c00',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '<i class="bi bi-check-lg"></i> Sí, guardar',
                    cancelButtonText: 'Cancelar',
                    reverseButtons: true,
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.dataset.submitting = 'true';
                        document.getElementById('saveSettingsButton').disabled = true;
                        Swal.fire({
                            title: 'Guardando...',
                            html: 'Aplicando los cambios, un momento.',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            showConfirmButton: false,
                            didOpen: () => {
                                Swal.showLoading();
                            },
                        });
                        form.submit();
                    }
                });
            });
        }
    });
</script>
@endpush
