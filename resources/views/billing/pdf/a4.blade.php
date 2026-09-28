<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $order->document_type }} {{ $order->full_number }}</title>
    <style>
        @page { size: A4; margin: 12mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11pt; color: #000; margin: 0; padding: 0; }
        .sheet { max-width: 210mm; margin: 0 auto; padding: 6mm; }

        .header { display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 8px; border-bottom: 2px solid #000; }
        .h-left { width: 60%; }
        .h-right { width: 38%; border: 2px solid #000; padding: 8px; text-align: center; }
        .h-right h2 { margin: 0; font-size: 14pt; letter-spacing: 1px; }
        .h-right h1 { margin: 4px 0 0; font-size: 13pt; letter-spacing: 2px; }
        .h-right .ruc { font-size: 11pt; font-weight: bold; }
        .company-name { font-size: 16pt; font-weight: bold; margin: 0 0 4px; }
        .small { font-size: 9pt; color: #444; }

        .client { margin-top: 8px; padding: 6px 0; border-bottom: 1px solid #999; font-size: 10pt; }
        .client div { margin: 2px 0; }
        .client strong { display: inline-block; min-width: 90px; }

        table.items { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 10pt; }
        table.items th { background: #f0f0f0; padding: 6px; border: 1px solid #999; text-align: left; }
        table.items td { padding: 6px; border: 1px solid #ddd; }
        table.items td.num { text-align: right; }
        table.items td.center { text-align: center; }

        .totals { margin-top: 10px; display: flex; justify-content: flex-end; }
        .totals table { font-size: 10pt; min-width: 240px; }
        .totals td { padding: 4px 8px; }
        .totals tr.grand td { font-size: 12pt; font-weight: bold; border-top: 2px solid #000; }

        .footer { margin-top: 12px; display: flex; justify-content: space-between; align-items: flex-end; gap: 12px; }
        .legend { flex: 1; font-size: 9pt; color: #333; line-height: 1.5; }
        .legend .label { font-style: italic; }

        .cdr-info { margin-top: 12px; font-size: 8.5pt; color: #666; border-top: 1px dashed #999; padding-top: 4px; }
        .actions { text-align: center; margin: 8px 0 16px; }
        .actions button { padding: 8px 18px; border: 0; background: #c62828; color: #fff; cursor: pointer; border-radius: 4px; }
        @media print { .actions { display: none; } }
    </style>
</head>
<body>

<div class="sheet">

    <div class="actions">
        <button onclick="window.print()">🖨️  Imprimir / Guardar PDF</button>
    </div>

    {{-- Cabecera --}}
    <div class="header">
        <div class="h-left">
            <p class="company-name">{{ $company['razon_social'] }}</p>
            @if($company['nombre_comercial'] !== $company['razon_social'])
                <div class="small">{{ $company['nombre_comercial'] }}</div>
            @endif
            <div class="small">{{ $company['direccion'] }}</div>
        </div>
        <div class="h-right">
            <div class="ruc">R.U.C. {{ $company['ruc'] }}</div>
            <h2>{{ strtoupper($order->document_type) }} ELECTRÓNICA</h2>
            <h1>{{ $order->full_number }}</h1>
        </div>
    </div>

    {{-- Datos del cliente --}}
    <div class="client">
        <div><strong>Cliente:</strong> {{ $order->client_name ?: 'Cliente Varios' }}</div>
        <div><strong>{{ $order->document_type === 'Factura' ? 'RUC' : 'DNI' }}:</strong> {{ $order->client_document ?: '—' }}</div>
        <div><strong>Fecha emisión:</strong> {{ ($order->issued_at ?? $order->created_at)->format('d/m/Y H:i') }}</div>
        <div><strong>Moneda:</strong> SOLES (S/)</div>
    </div>

    {{-- Tabla de items --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width: 8%;" class="center">Cant.</th>
                <th>Descripción</th>
                <th style="width: 14%;" class="center">P. Unit.</th>
                <th style="width: 14%;" class="center">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->details as $d)
                <tr>
                    <td class="center">{{ $d->quantity }}</td>
                    <td>
                        {{ $d->product->name ?? '—' }}
                        @if($d->sauce_choices)<br><small>Sabores: {{ implode(', ', $d->sauce_choices) }}</small>@endif
                    </td>
                    <td class="num">{{ number_format($d->price, 2) }}</td>
                    <td class="num">{{ number_format($d->price * $d->quantity, 2) }}</td>
                </tr>
            @endforeach
            @if($order->details->count() < 8)
                @for($i = 0; $i < 8 - $order->details->count(); $i++)
                    <tr><td colspan="4">&nbsp;</td></tr>
                @endfor
            @endif
        </tbody>
    </table>

    {{-- Totales --}}
    <div class="totals">
        <table>
            <tr><td>Op. Gravada:</td><td class="num">S/ {{ number_format($order->total_gravada, 2) }}</td></tr>
            <tr><td>IGV ({{ $config->igvRate() }}%):</td><td class="num">S/ {{ number_format($order->igv, 2) }}</td></tr>
            <tr class="grand"><td>TOTAL:</td><td class="num">S/ {{ number_format($order->total, 2) }}</td></tr>
        </table>
    </div>

    {{-- Pie: leyenda --}}
    <div class="footer">
        <div class="legend">
            <div class="label">SON:</div>
            <div><strong>{{ strtoupper(\App\Services\Sunat\NumeroLetras::convert($order->total)) }} SOLES</strong></div>
            @if($order->sunat_status === 'ACCEPTED')
                <div style="color:#1b5e20; margin-top:6px;">
                    ✔ Aceptado por SUNAT — Código {{ $order->sunat_code }}
                </div>
            @elseif($order->sunat_status === 'OBSERVED')
                <div style="color:#bf360c; margin-top:6px;">
                    ⚠ Aceptado con observaciones — {{ $order->sunat_description }}
                </div>
            @else
                <div style="color:#c62828; margin-top:6px;">
                    Estado SUNAT: {{ $order->sunat_status }}
                </div>
            @endif
        </div>
    </div>

    @if($order->hash)
        <div class="cdr-info">
            Resumen XML (DigestValue): <code>{{ $order->hash }}</code>
        </div>
    @endif
</div>

</body>
</html>
