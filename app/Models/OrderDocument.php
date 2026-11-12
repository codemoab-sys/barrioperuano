<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderDocument extends Model
{
    protected $table = 'bape_order_documents';

    protected $fillable = [
        'order_id',
        'client_id',
        'document_type',
        'client_name',
        'client_document',
        'client_address',
        'client_email',
        'serie',
        'correlativo',
        'issued_at',
        'total',
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
        'sent_at' => 'datetime',
        'total' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function getFullNumberAttribute(): ?string
    {
        if (!$this->serie || !$this->correlativo) {
            return null;
        }

        return sprintf('%s-%d', $this->serie, $this->correlativo);
    }
}
