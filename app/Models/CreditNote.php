<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditNote extends Model
{
    protected $table = 'bape_credit_notes';
use HasFactory;

    protected $fillable = [
        'order_id',
        'serie',
        'correlativo',
        'document_type',
        'reason_code',
        'reason_description',
        'items',
        'subtotal',
        'igv',
        'total',
        'sunat_status',
        'sunat_code',
        'sunat_description',
        'xml_path',
        'cdr_path',
        'pdf_path',
        'hash',
        'sent_at',
        'user_id',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'igv' => 'decimal:2',
        'total' => 'decimal:2',
        'items' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Formato SUNAT del comprobante: FC01-1, BC01-2 ...
     */
    public function getFullNumberAttribute(): string
    {
        return sprintf('%s-%d', $this->serie, $this->correlativo);
    }
}
