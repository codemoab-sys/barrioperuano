<?php

namespace App\Models\Kitchen;

use App\Models\Kitchen\Concerns\PreventsKitchenInventoryDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenShortageRegularization extends Model
{
    use PreventsKitchenInventoryDeletion;

    protected $table = 'bape_kitchen_shortage_regularizations';

    protected $fillable = ['operation_id', 'kitchen_order_consumption_id', 'kitchen_supply_id', 'user_id', 'quantity', 'regularization_key', 'note'];

    protected $casts = ['quantity' => 'decimal:4'];

    public function consumption(): BelongsTo
    {
        return $this->belongsTo(KitchenOrderConsumption::class, 'kitchen_order_consumption_id');
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(KitchenInventoryOperation::class, 'operation_id');
    }
}
