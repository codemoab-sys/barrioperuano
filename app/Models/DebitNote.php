<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DebitNote extends Model
{
    protected $table = 'bape_debit_notes';
    use HasFactory;

    protected $fillable = [
        'order_id',
        'serie',
        'correlativo',
        'document_type',
        'reason_code',
        'reason_description',
        'amount',
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
        'amount' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getFullNumberAttribute(): string
    {
        return sprintf('%s-%d', $this->serie, $this->correlativo);
    }
}
