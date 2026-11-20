<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $order->document_type }} {{ $order->full_number }} — Ticket</title>
    <style>
        @page { size: 80mm auto; margin: 2mm; }
        * { box-sizing: border-box; }
        body { font-family: 'Courier New', monospace; font-size: 10pt; color: #000; margin: 0; padding: 4mm; width: 72mm; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        hr { border: 0; border-top: 1px dashed #000; margin: 4px 0; }

        .head { text-align: center; }
        .head .name { font-weight: bold; font-size: 12pt; }
        .head .ruc  { font-weight: bold; margin: 2px 0; }
        .head .doc  { font-weight: bold; font-size: 11pt; margin-top: 4px; border: 1px solid #000; padding: 2px; }

        table { width: 100%; border-collapse: collapse; font-size: 9pt; }
        table th, table td { padding: 2px 0; }

        .total { font-size: 13pt; font-weight: bold; }
        .legend { font-size: 8pt; text-align: center; margin-top: 4px; }

        .actions { text-align: center; margin: 0 0 6px; }
        .actions button { padding: 6px 12px; border: 0; background: #c62828; color: #fff; cursor: pointer; border-radius: 3px; font-size: 9pt; }
        @media print { .actions { display: none; } body { padding: 0; } }
    </style>
</head>
<body>

<div class="actions"><button onclick="window.print()">🖨️ Imprimir</button></div>

<div class="head">
    <div class="name">{{ $company['nombre_comercial'] }}</div>
    <div class="ruc">RUC: {{ $company['ruc'] }}</div>
    <div>{{ $company['direccion'] }}</div>
    <div class="doc">{{ strtoupper($order->document_type) }} ELECTRÓNICA<br>{{ $order->full_number }}</div>
</div>

<hr>

<div>
    <div><span class="bold">Fecha:</span> {{ ($order->issued_at ?? $order->created_at)->format('d/m/Y H:i') }}</div>
    <div><span class="bold">Cliente:</span> {{ $order->client_name ?: 'Cliente Varios' }}</div>
    <div><span class="bold">{{ $order->document_type === 'Factura' ? 'RUC' : 'DNI' }}:</span> {{ $order->client_document ?: '—' }}</div>
</div>

<hr>

<table>
    <thead>
        <tr>
            <th style="text-align:left;">Descripción</th>
            <th class="right">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($order->details as $d)
            <tr>
                <td>
                    {{ $d->quantity }} x {{ $d->product->name ?? '—' }}
                    @if($d->sauce_choices)<br><small>Sabores: {{ implode(', ', $d->sauce_choices) }}</small>@endif
                </td>
                <td class="right">{{ number_format($d->price * $d->quantity, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<hr>

<table>
    <tr><td>Op. Gravada</td><td class="right">S/ {{ number_format($order->total_gravada, 2) }}</td></tr>
    <tr><td>IGV ({{ $config->igvRate() }}%)</td><td class="right">S/ {{ number_format($order->igv, 2) }}</td></tr>
    <tr class="total"><td>TOTAL</td><td class="right">S/ {{ number_format($order->total, 2) }}</td></tr>
</table>

<hr>

<div class="legend">
    <em>SON:</em><br>
    <strong>{{ strtoupper(\App\Services\Sunat\NumeroLetras::convert($order->total)) }} SOLES</strong>
</div>

<div class="legend">
    Representación impresa del<br>comprobante electrónico.<br>
    @if($order->sunat_status === 'ACCEPTED')
        ✔ Aceptado por SUNAT
    @else
        Estado: {{ $order->sunat_status }}
    @endif
</div>

<hr>
<div class="legend">{{ $company['ticket_footer'] }}</div>

</body>
</html>
