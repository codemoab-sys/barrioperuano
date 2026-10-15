<?php

namespace App\Models\Kitchen;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenSupply extends Model
{
    protected $table = 'bape_kitchen_supplies';

    protected $fillable = ['category_id', 'name', 'unit', 'stock_current', 'is_active'];

    protected $casts = ['stock_current' => 'decimal:4', 'is_active' => 'boolean'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(KitchenCategory::class, 'category_id');
    }
}
