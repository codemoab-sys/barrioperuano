<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DocumentSeries extends Model
{
    protected $table = 'bape_document_series';
use HasFactory;

    protected $fillable = [
        'area_id',
        'document_code',
        'document_type',
        'serie',
        'last_number',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_number' => 'integer',
    ];

    /**
     * Reserva el prÃƒÂ³ximo correlativo de forma atÃƒÂ³mica.
     */
    public static function next(string $documentType, ?int $areaId = null): array
    {
        return DB::transaction(function () use ($documentType) {
            $serie = static::where('document_type', $documentType)
                ->whereNull('area_id')
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (!$serie) {
                throw new \RuntimeException("No hay serie configurada para '{$documentType}' en la zona seleccionada.");
            }

            $serie->last_number = $serie->last_number + 1;
            $serie->save();

            return [
                'serie' => $serie->serie,
                'correlativo' => $serie->last_number,
            ];
        });
    }
}
