<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pre-orden Delivery #{{ $delivery->id }}</title>
    <style>
        @page { margin: 0; padding: 0; size: auto; }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 13px;
            margin: 0;
            padding: 6px;
            width: 78mm;
            color: #000;
            background: #fff;
        }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .fw-bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; }

        .header { margin-bottom: 10px; border-bottom: 1px dashed #000; padding-bottom: 6px; }
        .footer { margin-top: 10px; border-top: 1px dashed #000; padding-top: 6px; font-size: 11px; }
        .pedido { border: 2px solid #000; padding: 6px; margin-bottom: 8px; }

        table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        td, th { vertical-align: top; padding: 3px 0; }

        .qty { width: 12%; text-align: left; font-weight: bold; }
        .desc { width: 68%; text-align: left; }
        .price { width: 20%; text-align: right; }

        .totals { margin-top: 10px; border-top: 1px solid #000; padding-top: 5px; }
        .row { display: flex; justify-content: space-between; margin-bottom: 2px; }

        .no-print { display: none; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body>
<div class="no-print" style="position: fixed; top: 0; right: 0; padding: 10px; background: white; border: 1px solid #ccc; z-index: 1000;">
    <button onclick="window.print()" style="padding: 10px 20px; font-weight: bold; cursor: pointer;">Imprimir</button>
</div>

<div class="header text-center">
    @if(isset($settings['company_logo']))
        <img src="{{ route('uploaded-assets.show', ['path' => $settings['company_logo']]) }}" style="max-width: 50px; filter: grayscale(100%); margin-bottom: 5px;">
        <br>
    @endif

    <div class="fw-bold uppercase" style="font-size: 16px;">{{ $settings['company_name'] ?? 'MI RESTAURANTE' }}</div>
    <div>{{ $settings['company_address'] ?? 'Dirección del Local' }}</div>
    <div>Tel: {{ $settings['company_phone'] ?? '---' }}</div>
    <div style="margin-top: 5px;">{{ now()->format('d/m/Y H:i') }}</div>
</div>

<div class="pedido">
    <div class="fw-bold uppercase text-center" style="font-size: 17px; letter-spacing: 1px;">PEDIDO DELIVERY</div>
    <div class="text-center" style="font-size: 12px;">
        PRE-ORDEN #{{ str_pad($delivery->order_id, 6, '0', STR_PAD_LEFT) }} &nbsp;|&nbsp; {{ now()->format('d/m/Y H:i') }}
    </div>
</div>

<table>
    <tbody>
        <tr>
            <td class="qty">CLI.</td>
            <td class="desc">{{ $delivery->client_name ?: 'Cliente - Varios' }}</td>
            <td class="price">{{ $delivery->client_phone }}</td>
        </tr>
        <tr>
            <td class="qty">DIR.</td>
            <td class="desc">{{ $delivery->address ?: '—' }}</td>
            <td class="price"></td>
        </tr>
        @if($delivery->reference)
            <tr>
                <td class="qty">REF.</td>
                <td class="desc">{{ $delivery->reference }}</td>
                <td class="price"></td>
            </tr>
        @endif
        <tr>
            <td class="qty">EST.</td>
            <td class="desc">{{ $delivery->status_label }} @if($delivery->driver) — {{ $delivery->driver->name }} @endif</td>
            <td class="price"></td>
        </tr>
    </tbody>
</table>

<table>
    <thead>
        <tr style="border-bottom: 1px solid #000;">
            <th class="qty">CANT.</th>
            <th class="desc">DESCRIPCION</th>
            <th class="price">TOTAL</th>
        </tr>
    </thead>
    <tbody>
        @foreach($delivery->order->details as $detail)
            <tr>
                <td class="qty">{{ $detail->quantity }}</td>
                <td class="desc">
                    {{ $detail->product->name }}
                    @if($detail->sauce_choices)
                        <br><b>Sabores: {{ implode(', ', $detail->sauce_choices) }}</b>
                    @endif
                    @if($detail->note)
                        <br><b>({{ $detail->note }})</b>
                    @endif
                </td>
                <td class="price">{{ number_format($detail->quantity * $detail->price, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="totals">
    <div class="row">
        <span>Subtotal:</span>
        <span>{{ $settings['currency_symbol'] ?? 'S/' }} {{ number_format($delivery->order->details->sum(fn ($detail) => $detail->quantity * $detail->price), 2) }}</span>
    </div>
    <div class="row">
        <span>Delivery:</span>
        <span>{{ $settings['currency_symbol'] ?? 'S/' }} {{ number_format($delivery->delivery_fee, 2) }}</span>
    </div>
    <div class="row fw-bold" style="font-size: 16px; margin-top: 5px; border-top: 1px dashed #000; padding-top: 5px;">
        <span>TOTAL:</span>
        <span>{{ $settings['currency_symbol'] ?? 'S/' }} {{ number_format($delivery->total_with_fee, 2) }}</span>
    </div>
</div>

<div class="footer text-center">
    --- FIN PRE-ORDEN #{{ $delivery->order_id }} ---
</div>

<script>
    window.onload = function () { window.print(); };
</script>
</body>
</html>
