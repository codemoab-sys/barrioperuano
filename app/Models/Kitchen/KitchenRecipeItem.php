<?php

namespace App\Models\Kitchen;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenRecipeItem extends Model
{
    protected $table = 'bape_kitchen_recipe_items';

    protected $fillable = ['recipe_version_id', 'kitchen_supply_id', 'quantity'];

    protected $casts = ['quantity' => 'decimal:4'];

    public function version(): BelongsTo
    {
        return $this->belongsTo(KitchenRecipeVersion::class, 'recipe_version_id');
    }

    public function supply(): BelongsTo
    {
        return $this->belongsTo(KitchenSupply::class, 'kitchen_supply_id');
    }
}
