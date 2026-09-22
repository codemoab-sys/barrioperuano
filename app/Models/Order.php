<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $table = 'bape_orders';
protected $fillable = [
        'table_id',
        'user_id',
        'client_id',
        'status',
        'total',
        'payment_method',
        'received_amount',
        'change_amount',
        'document_type',
        'client_name',
        'client_document',
        'discount',
        'cash_register_id',
        'issued_at',
        'anulacion_status',
        'anulacion_ticket',
        'anulacion_description',
        'anulacion_pdf_path',
        'anulacion_xml_path',
        'anulacion_cdr_path',
        'anulacion_sent_at',

        // Ã¢â€â‚¬Ã¢â€â‚¬ SUNAT Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
        'serie',
        'correlativo',
        'subtotal',
        'igv',
        'total_gravada',
        'total_exonerada',
        'total_inafecta',
        'total_gratuita',
        'sunat_status',
        'sunat_code',
        'sunat_description',
        'xml_path',
        'cdr_path',
        'pdf_path',
        'hash',
        'sent_at',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'anulacion_sent_at' => 'datetime',
        'sent_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'igv' => 'decimal:2',
        'total' => 'decimal:2',
        'total_gravada' => 'decimal:2',
        'total_exonerada' => 'decimal:2',
        'total_inafecta' => 'decimal:2',
        'total_gratuita' => 'decimal:2',
    ];

    public function getClientNameAttribute(?string $value): ?string
    {
        if ($value !== null && preg_match('/Ã.|Â.|â€™|â€œ|â€|�/', $value)) {
            return mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        }

        return $value;
    }

    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    public function area()
    {
        return $this->hasOneThrough(Area::class, Table::class, 'id', 'id', 'table_id', 'area_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function details()
    {
        return $this->hasMany(OrderDetail::class);
    }

    public function cashRegister()
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function creditNotes()
    {
        return $this->hasMany(CreditNote::class);
    }

    /**
     * "B001-15" / "F001-3" / null si aÃƒÂºn no se ha numerado.
     */
    public function getFullNumberAttribute(): ?string
    {
        if (!$this->serie || !$this->correlativo) {
            return null;
        }

        return sprintf('%s-%d', $this->serie, $this->correlativo);
    }

    public function isElectronic(): bool
    {
        return in_array($this->document_type, ['Boleta', 'Factura'], true);
    }
}
