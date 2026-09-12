<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background: #0d6efd; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .header h1 { margin: 0; font-size: 22px; }
        .body { background: #f8f9fa; padding: 25px; border: 1px solid #dee2e6; }
        .footer { text-align: center; padding: 15px; font-size: 12px; color: #6c757d; }
        .btn { display: inline-block; background: #0d6efd; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; }
        .info-box { background: white; padding: 15px; border-radius: 6px; margin: 15px 0; border-left: 4px solid #0d6efd; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Restaurante Barrio Peruano</h1>
        </div>
        <div class="body">
            <p>Estimado/a <strong>{{ $order->client_name ?: 'Cliente' }}</strong>,</p>

            <p>Adjuntamos su comprobante electrónico:</p>

            <div class="info-box">
                <strong>{{ $order->document_type }}:</strong> {{ $order->full_number }}<br>
                <strong>Fecha:</strong> {{ $order->created_at->format('d/m/Y H:i') }}<br>
                <strong>Total:</strong> S/ {{ number_format($order->total, 2) }}
            </div>

            @if($messageBody)
                <p>{{ $messageBody }}</p>
            @endif

            <p style="text-align: center; margin: 25px 0;">
                <a href="{{ $order->pdf_path }}" class="btn">Ver comprobante</a>
            </p>

            <p class="footer">
                Este es un correo automático generado por el sistema de facturación electrónica.<br>
                Restaurante Barrio Peruano - RUC: {{ $order->client_document ?? '' }}
            </p>
        </div>
    </div>
</body>
</html>
