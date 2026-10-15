<?php

namespace App\Models\Kitchen;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KitchenRecipe extends Model
{
    protected $table = 'bape_kitchen_recipes';

    protected $fillable = ['product_id', 'name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(KitchenRecipeVersion::class, 'recipe_id');
    }
}
