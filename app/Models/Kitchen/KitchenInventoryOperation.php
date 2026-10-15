<?php

namespace App\Models\Kitchen;

use App\Models\Kitchen\Concerns\PreventsKitchenInventoryDeletion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KitchenInventoryOperation extends Model
{
    use PreventsKitchenInventoryDeletion;

    protected $table = 'bape_kitchen_inventory_operations';

    protected $fillable = ['event_key', 'operation_type', 'request_hash', 'status', 'user_id'];

    public function movements(): HasMany
    {
        return $this->hasMany(KitchenInventoryMovement::class, 'operation_id');
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(KitchenOrderConsumption::class, 'operation_id');
    }

    public function regularizations(): HasMany
    {
        return $this->hasMany(KitchenShortageRegularization::class, 'operation_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
