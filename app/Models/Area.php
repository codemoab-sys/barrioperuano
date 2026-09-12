<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    protected $table = 'bape_areas';
use HasFactory;

    protected $fillable = ['name', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // RelaciÃƒÂ³n: Un ÃƒÂ¡rea tiene muchas mesas
    public function tables()
    {
        return $this->hasMany(Table::class);
    }
}
