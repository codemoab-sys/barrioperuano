<?php

namespace App\Models\Kitchen;

use App\Models\Kitchen\Concerns\PreventsKitchenInventoryDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenInventoryMovement extends Model
{
    use PreventsKitchenInventoryDeletion;

    protected $table = 'bape_kitchen_inventory_movements';

    protected $fillable = ['operation_id', 'kitchen_supply_id', 'type', 'theoretical_quantity', 'quantity', 'covered_quantity', 'shortage_quantity', 'stock_before', 'stock_after', 'source_type', 'source_id', 'idempotency_key', 'user_id', 'note'];

    protected $casts = ['theoretical_quantity' => 'decimal:4', 'quantity' => 'decimal:4', 'covered_quantity' => 'decimal:4', 'shortage_quantity' => 'decimal:4', 'stock_before' => 'decimal:4', 'stock_after' => 'decimal:4'];

    public function supply(): BelongsTo
    {
        return $this->belongsTo(KitchenSupply::class, 'kitchen_supply_id');
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(KitchenInventoryOperation::class, 'operation_id');
    }
}
