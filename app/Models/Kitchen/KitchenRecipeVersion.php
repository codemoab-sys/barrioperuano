<?php

namespace App\Models\Kitchen;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KitchenRecipeVersion extends Model
{
    protected $table = 'bape_kitchen_recipe_versions';

    protected $fillable = ['recipe_id', 'version', 'status', 'published_at'];

    protected $casts = ['published_at' => 'datetime'];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(KitchenRecipe::class, 'recipe_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(KitchenRecipeItem::class, 'recipe_version_id');
    }
}
