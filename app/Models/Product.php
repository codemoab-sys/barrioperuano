<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'bape_products';
use HasFactory;

    protected $fillable = [
        'name',
        'barcode', // <--- NUEVO CAMPO AGREGADO
        'description',
        'price',
        'cost',
        'image',
        'category_id',
        'stock',
        'is_active',
        'is_saleable',
        'promotional_price',
        'is_chef_recommendation',
        'is_new'
    ];

    // RelaciÃƒÂ³n con CategorÃƒÂ­a
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // RelaciÃƒÂ³n con Ingredientes (Para el descuento de inventario)
    public function ingredients()
    {
        return $this->belongsToMany(Product::class, 'bape_product_ingredients', 'product_id', 'ingredient_id')
                    ->withPivot('quantity');
    }

    // RelaciÃƒÂ³n con Detalles de Orden
    // RelaciÃƒÂ³n con Detalles de Orden
    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class);
    }

    // Atributo dinÃƒÂ¡mico: Costo de Receta (Escandallo)
    public function getRecipeCostAttribute()
    {
        if ($this->ingredients->isEmpty()) {
            return $this->cost; // Si no tiene receta, su costo es el costo asignado manualmente
        }
        
        return $this->ingredients->sum(function($ingredient) {
            return $ingredient->cost * $ingredient->pivot->quantity;
        });
    }
}
