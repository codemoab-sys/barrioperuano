@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold text-dark mb-0"><i class="bi bi-box-seam-fill me-2"></i>Inventario de Productos</h2>
        <p class="text-muted mb-0">Gestión de carta y existencias</p>
    </div>
    <div>
        <a href="{{ route('inventory.logs') }}" class="btn btn-dark me-2">
            <i class="bi bi-clock-history me-1"></i> Ver Kardex
        </a>
        <a href="{{ route('products.create') }}" class="btn btn-primary fw-bold shadow-sm">
            <i class="bi bi-plus-lg me-1"></i> Nuevo Producto
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <form method="GET" action="{{ route('products.index') }}" class="p-3 border-bottom">
            <div class="row g-2">
                <div class="col-12 col-md-8 col-lg-6">
                    <label for="inventorySearch" class="visually-hidden">Buscar productos</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="search" name="search" id="inventorySearch" class="form-control" value="{{ request('search') }}" placeholder="Producto, código de barras o categoría" autocomplete="off">
                        <span class="input-group-text d-none" id="inventorySearchSpinner" aria-label="Buscando">
                            <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
                        </span>
                        @if(request()->filled('search'))
                            <a href="{{ route('products.index') }}" class="btn btn-outline-secondary" title="Limpiar búsqueda" aria-label="Limpiar búsqueda">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        @endif
                        <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Buscar</button>
                    </div>
                </div>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4 text-uppercase text-muted small fw-bold">Producto</th>
                        <th class="text-uppercase text-muted small fw-bold">Categoría</th>
                        <th class="text-uppercase text-muted small fw-bold">Costo</th>
                        <th class="text-uppercase text-muted small fw-bold">Precio</th>
                        <th class="text-uppercase text-muted small fw-bold">Stock</th>
                        <th class="text-center text-uppercase text-muted small fw-bold">Estado</th>
                        <th class="text-end pe-4 text-uppercase text-muted small fw-bold">Acciones</th>
                    </tr>
                </thead>
                <tbody id="inventoryProductsBody">
                    @forelse($products as $product)
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    @if($product->image)
                                        <img src="{{ route('uploaded-assets.show', ['path' => $product->image]) }}" class="rounded me-3 border" width="48" height="48" style="object-fit: cover;">
                                    @else
                                        <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center border text-muted" style="width: 48px; height: 48px;">
                                            <i class="bi bi-image"></i>
                                        </div>
                                    @endif
                                    <div>
                                        <div class="fw-bold text-dark">{{ $product->name }}</div>
                                        
                                        @if($product->barcode)
                                            <small class="text-muted d-block" style="font-size: 0.75rem;">
                                                <i class="bi bi-upc-scan me-1"></i>{{ $product->barcode }}
                                            </small>
                                        @endif

                                        @if(!$product->is_saleable)
                                            <span class="badge bg-secondary" style="font-size: 0.65rem;"><i class="bi bi-eye-slash me-1"></i>Solo Insumo</span>
                                        @endif
                                        @if($product->ingredients->count() > 0)
                                            <span class="badge bg-info text-dark" style="font-size: 0.65rem;"><i class="bi bi-diagram-3 me-1"></i>Tiene Receta</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ $product->category->name }}</span>
                            </td>
                            <td>
                                @if($product->ingredients->count() > 0)
                                    <span class="text-muted small">{{ $currencySymbol }} {{ number_format($product->recipe_cost, 2) }}</span>
                                    <i class="bi bi-info-circle text-info ms-1" title="Costo calculado por receta"></i>
                                @else
                                    <span class="text-muted small">{{ $currencySymbol }} {{ number_format($product->cost, 2) }}</span>
                                @endif
                            </td>
                            <td class="fw-bold text-primary">{{ $currencySymbol }} {{ number_format($product->price, 2) }}</td>
                            <td>
                                @if(is_null($product->stock))
                                    <span class="text-muted small">--</span>
                                @elseif($product->stock <= 5)
                                    <span class="badge bg-warning text-dark border border-warning">Bajo: {{ $product->stock }}</span>
                                @else
                                    <span class="badge bg-light text-success border border-success fw-bold">{{ $product->stock }}</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm {{ $product->is_active ? 'btn-outline-success' : 'btn-outline-secondary' }} rounded-pill px-3 fw-bold" style="font-size: 0.75rem;" onclick="toggleProduct({{ $product->id }}, {{ $product->is_active ? 'true' : 'false' }})">
                                    {{ $product->is_active ? 'ACTIVO' : 'INACTIVO' }}
                                </button>
                            </td>
                            <td class="text-end pe-4">
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#adjustStock{{ $product->id }}" title="Ajustar Stock">
                                        <i class="bi bi-arrow-left-right"></i>
                                    </button>
                                    <a href="{{ route('products.edit', $product->id) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteProduct({{ $product->id }}, '{{ $product->name }}')" title="Eliminar">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                                
                                <div class="modal fade" id="adjustStock{{ $product->id }}" tabindex="-1">
                                    <div class="modal-dialog modal-sm modal-dialog-centered">
                                        <form action="{{ route('products.adjust', $product->id) }}" method="POST" class="modal-content" onsubmit="return adjustStock(event, this)">
                                            @csrf
                                            <div class="modal-header py-2 bg-light">
                                                <h6 class="modal-title fw-bold">Ajustar Stock</h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body text-start">
                                                <p class="small mb-2">Producto: <strong>{{ $product->name }}</strong></p>
                                                <div class="input-group mb-3">
                                                    <select name="type" class="form-select" style="max-width: 110px;">
                                                        <option value="add">➕ Sumar</option>
                                                        <option value="sub">➖ Restar</option>
                                                        <option value="set">🎯 Fijar</option>
                                                    </select>
                                                    <input type="number" name="quantity" class="form-control" placeholder="Cantidad" required min="0">
                                                </div>
                                            </div>
                                            <div class="modal-footer p-1">
                                                <button class="btn btn-primary w-100 btn-sm">Guardar Ajuste</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-5 text-muted">No hay productos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white border-0 py-3" id="inventoryPagination">
        {{ $products->links() }}
    </div>
</div>

<form id="toggleForm-0" method="POST" class="d-none">@csrf</form>
@endsection

@push('scripts')
<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const inventorySearch = document.getElementById('inventorySearch');
    const inventoryProductsBody = document.getElementById('inventoryProductsBody');
    const inventoryPagination = document.getElementById('inventoryPagination');
    const inventorySearchSpinner = document.getElementById('inventorySearchSpinner');
    let inventorySearchTimer;
    let inventorySearchRequest;

    inventorySearch.addEventListener('input', function() {
        clearTimeout(inventorySearchTimer);
        inventorySearchTimer = setTimeout(() => {
            searchInventoryProducts(inventorySearch.value);
        }, 300);
    });

    async function searchInventoryProducts(search) {
        if (inventorySearchRequest) {
            inventorySearchRequest.abort();
        }

        inventorySearchRequest = new AbortController();
        inventorySearchSpinner.classList.remove('d-none');
        const url = new URL(inventorySearch.form.action, window.location.origin);
        if (search.trim() !== '') {
            url.searchParams.set('search', search.trim());
        }

        try {
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: inventorySearchRequest.signal
            });
            if (!response.ok) {
                throw new Error('No se pudo buscar el inventario.');
            }

            const html = await response.text();
            const documentResponse = new DOMParser().parseFromString(html, 'text/html');
            const body = documentResponse.querySelector('#inventoryProductsBody');
            const pagination = documentResponse.querySelector('#inventoryPagination');
            if (!body || !pagination) {
                throw new Error('La respuesta de búsqueda no tiene el formato esperado.');
            }

            inventoryProductsBody.innerHTML = body.innerHTML;
            inventoryPagination.innerHTML = pagination.innerHTML;
            window.history.replaceState({}, '', url);
        } catch (error) {
            if (error.name !== 'AbortError') {
                showToast('error', 'Error', error.message);
            }
        } finally {
            inventorySearchSpinner.classList.add('d-none');
        }
    }

    function showToast(icon, title, text) {
        Swal.fire({ icon, title, text, confirmButtonColor: '#0d6efd', timer: 2500, timerProgressBar: true });
    }

    @if(session('success'))
        showToast('success', 'Listo', '{{ session('success') }}');
    @endif
    @if(session('error'))
        showToast('error', 'Error', '{{ session('error') }}');
    @endif

    function deleteProduct(id, name) {
        Swal.fire({
            title: '¿Eliminar producto?',
            text: name,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ url("/products") }}/' + id;
                form.innerHTML = '<input type="hidden" name="_token" value="' + csrfToken + '"><input type="hidden" name="_method" value="DELETE">';
                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    function toggleProduct(id, isActive) {
        Swal.fire({
            title: '¿Cambiar estado del producto?',
            text: isActive ? 'El producto pasará a inactivo.' : 'El producto pasará a activo.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, cambiar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#6c757d'
        }).then((result) => {
            if (!result.isConfirmed) {
                return;
            }

            fetch('{{ url("/products") }}/' + id + '/toggle', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' }
            }).then(r => r.json()).then(data => {
                showToast('success', 'Estado actualizado', data.message || 'Listo');
                setTimeout(() => location.reload(), 800);
            }).catch(() => location.reload());
        });
    }

    function adjustStock(e, form) {
        e.preventDefault();

        var btn = form.querySelector('button[type="submit"], button:not([type])') || form.querySelector('button');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        }

        fetch(form.action, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form)
        }).then(async (r) => {
            const contentType = r.headers.get('content-type') || '';
            const data = contentType.includes('application/json') ? await r.json() : null;

            if (r.ok && data && !data.error) {
                const modalInstance = bootstrap.Modal.getInstance(form.closest('.modal'));
                if (modalInstance) modalInstance.hide();
                showToast('success', 'Stock ajustado', data.message || 'Listo');
                setTimeout(() => location.reload(), 800);
                return;
            }

            const modalInstance = bootstrap.Modal.getInstance(form.closest('.modal'));
            if (modalInstance) modalInstance.hide();
            if (data && data.error) {
                showToast('error', 'Error', data.error);
            } else {
                showToast('error', 'Error', 'No se pudo ajustar el stock.');
            }
        }).catch(() => location.reload());

        return false;
    }
</script>
@endpush