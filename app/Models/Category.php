<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $table = 'bape_categories';

    use HasFactory;

    // Campos que permitimos llenar masivamente
    protected $fillable = [
        'name',
        'image',
        'is_active'
    ];

    // RelaciÃ³n: Una categorÃ­a tiene muchos productos
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
