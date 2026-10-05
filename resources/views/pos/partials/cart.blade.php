<div class="pos-cart-items p-2" data-order-id="{{ $order?->id }}">
    @if($order && $order->details->count() > 0)
        <div class="w-100">
            <table class="table table-borderless align-middle mb-0 pos-cart-table">
                <colgroup>
                    <col style="width: 28px;">
                    <col>
                    <col style="width: 84px;">
                </colgroup>
                <thead class="text-muted small border-bottom">
                    <tr>
                        <th></th>
                        <th>PRODUCTO</th>
                        <th class="text-center">CANT.</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->details as $detail)
                        <tr class="border-bottom">

                            <td class="px-0 align-middle">
                                <button class="btn btn-sm text-danger p-0" onclick="removeItem({{ $detail->id }})" title="Eliminar">
                                    <i class="bi bi-x-circle-fill fs-5"></i>
                                </button>
                            </td>

                            <td class="align-middle px-1">
                                <div class="fw-bold text-dark text-break" style="font-size: 0.85rem; line-height: 1.25;" title="{{ $detail->product->name }}">
                                    {{ $detail->product->name }}
                                </div>
                                @if($detail->sauce_choices)
                                    <small class="d-block text-danger fw-semibold text-break">Sabores: {{ implode(', ', $detail->sauce_choices) }}</small>
                                @endif
                                <div class="d-flex align-items-center flex-wrap">
                                    <small class="text-muted me-1" style="font-size: 0.7rem;">
                                        {{ $currency ?? 'S/' }}{{ number_format($detail->price, 2) }}
                                    </small>
                                    @if(mb_strtolower(trim((string) $detail->product->category?->name)) === 'servicio')
                                        <button type="button" class="btn btn-link btn-sm p-0 text-primary"
                                                onclick="editServicePrice({{ $detail->id }}, {{ $detail->price }})"
                                                title="Editar precio del servicio">
                                            <i class="bi bi-pencil-square" style="font-size: 0.7rem;"></i>
                                        </button>
                                    @endif
                                    @if($detail->note)
                                        <i class="bi bi-chat-square-text-fill text-warning" style="font-size: 0.7rem;" title="{{ $detail->note }}"></i>
                                    @endif
                                    <a href="javascript:void(0)" class="ms-1 text-decoration-none text-primary"
                                       data-bs-toggle="modal" data-bs-target="#noteModal"
                                       data-detail-id="{{ $detail->id }}"
                                       data-note-content="{{ $detail->note }}">
                                       <i class="bi bi-pencil" style="font-size: 0.7rem;"></i>
                                    </a>
                                </div>
                                <small class="d-block text-end fw-bold text-dark" style="font-size: 0.75rem;">
                                    Total: {{ $currency ?? 'S/' }}{{ number_format($detail->quantity * $detail->price, 2) }}
                                </small>
                            </td>

                            <td class="align-middle px-0">
                                <div class="input-group input-group-sm flex-nowrap cart-quantity-control">
                                    <button class="btn btn-outline-secondary px-1 py-0" type="button" aria-label="Restar uno" onclick="updateQty({{ $detail->id }}, {{ $detail->quantity - 1 }})">-</button>
                                    <input type="text" class="form-control text-center px-0 py-0 fw-bold bg-white border-secondary"
                                           value="{{ $detail->quantity }}" readonly aria-label="Cantidad" style="font-size: 0.85rem;">
                                    @php $atLimit = !is_null($detail->product->stock) && $detail->quantity >= $detail->product->stock; @endphp
                                    <button class="btn btn-{{ $atLimit ? 'secondary' : 'outline-primary' }} px-1 py-0" style="font-size: 0.8rem;"
                                            type="button" aria-label="Sumar uno"
                                            onclick="updateQty({{ $detail->id }}, {{ $detail->quantity + 1 }})"
                                            {{ $atLimit ? 'disabled' : '' }}>
                                        @if($atLimit) <i class="bi bi-lock-fill" style="font-size: 0.6rem;"></i> @endif +
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="h-100 d-flex flex-column align-items-center justify-content-center text-muted opacity-50">
            <i class="bi bi-basket3 display-1 mb-3"></i>
            <p class="small text-center m-0">Cuenta vacía</p>
        </div>
    @endif
</div>

<div class="pos-cart-summary bg-white border-top p-3">
    @if($order)
        @php
            $igvRate = (new \App\Services\Sunat\SunatConfig())->igvRate();
            $orderTotal = (float) $order->total;
            $subtotalWithoutIgv = round($orderTotal / (1 + ($igvRate / 100)), 2);
            $igvAmount = round($orderTotal - $subtotalWithoutIgv, 2);
        @endphp
        <input type="hidden" id="cartTotalValue" value="{{ number_format($order->total, 2, '.', '') }}">

        <div class="row mb-1" style="font-size: 0.8rem;">
            <div class="col-6 text-muted">Subtotal:</div>
            <div class="col-6 text-end">{{ $currency ?? 'S/' }}{{ number_format($subtotalWithoutIgv, 2) }}</div>
        </div>

        <div class="row mb-1" style="font-size: 0.8rem;">
            <div class="col-6 text-muted">IGV ({{ rtrim(rtrim(number_format($igvRate, 2, '.', ''), '0'), '.') }}%):</div>
            <div class="col-6 text-end">{{ $currency ?? 'S/' }}{{ number_format($igvAmount, 2) }}</div>
        </div>

        @if($order->discount > 0)
            <div class="row mb-1 text-danger fw-bold" style="font-size: 0.8rem;">
                <div class="col-6">Descuento:</div>
                <div class="col-6 text-end">-{{ number_format($order->discount, 2) }}</div>
            </div>
        @endif

        <div class="d-flex justify-content-between align-items-center mb-2 mt-2 border-top pt-2">
            <h5 class="mb-0 fw-bold text-dark fs-6">TOTAL:</h5>
            <h3 class="mb-0 fw-bold text-primary fs-4">
                {{ $currency ?? 'S/' }}{{ number_format($order->total, 2) }}
            </h3>
        </div>

        <div class="row g-1 mb-2">
            <div class="col-4">
                <button class="btn btn-light w-100 border text-muted btn-sm fw-bold py-2" data-bs-toggle="modal" data-bs-target="#optionsModal" title="Opciones">
                    <i class="bi bi-sliders"></i> <span style="font-size: 0.7rem;">Opc.</span>
                </button>
            </div>
            <div class="col-4">
                <a href="{{ route('pos.precheck', $order->id) }}" target="_blank" class="btn btn-light w-100 border text-muted btn-sm fw-bold py-2" title="Pre-cuenta">
                    <i class="bi bi-receipt"></i> <span style="font-size: 0.7rem;">Pre</span>
                </a>
            </div>
        </div>

        @if(in_array(auth()->user()->role, ['admin', 'cashier', 'cashier_waiter'], true))
            <div class="d-grid">
                <button class="btn btn-success fw-bold py-2 shadow-sm"
                        onclick="var m=document.getElementById('checkoutModal'); if(m){ new bootstrap.Modal(m).show(); }">
                    <i class="bi bi-cash-coin me-2"></i> COBRAR
                </button>
            </div>
        @else
            <div class="d-grid">
                <a href="{{ route('pos.precheck', $order->id) }}" target="_blank" class="btn btn-outline-primary fw-bold py-2">
                    <i class="bi bi-receipt me-2"></i> MOSTRAR PRECUENTA
                </a>
            </div>
        @endif
    @endif
</div>
