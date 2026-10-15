<?php

namespace App\Models\Kitchen;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KitchenCategory extends Model
{
    protected $table = 'bape_kitchen_categories';

    protected $fillable = ['name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function supplies(): HasMany
    {
        return $this->hasMany(KitchenSupply::class, 'category_id');
    }
}
