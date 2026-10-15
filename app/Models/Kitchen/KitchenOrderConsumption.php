<?php

namespace App\Models\Kitchen;

use App\Models\Kitchen\Concerns\PreventsKitchenInventoryDeletion;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenOrderConsumption extends Model
{
    use PreventsKitchenInventoryDeletion;

    protected $table = 'bape_kitchen_order_consumptions';

    protected $fillable = ['operation_id', 'order_id', 'order_detail_id', 'product_id', 'recipe_version_id', 'kitchen_supply_id', 'sold_quantity', 'recipe_quantity', 'theoretical_quantity', 'consumed_quantity', 'covered_quantity', 'shortage_quantity', 'returned_quantity', 'idempotency_key'];

    protected $casts = ['sold_quantity' => 'decimal:4', 'recipe_quantity' => 'decimal:4', 'theoretical_quantity' => 'decimal:4', 'consumed_quantity' => 'decimal:4', 'covered_quantity' => 'decimal:4', 'shortage_quantity' => 'decimal:4', 'returned_quantity' => 'decimal:4'];

    public function operation(): BelongsTo
    {
        return $this->belongsTo(KitchenInventoryOperation::class, 'operation_id');
    }

    public function supply(): BelongsTo
    {
        return $this->belongsTo(KitchenSupply::class, 'kitchen_supply_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderDetail(): BelongsTo
    {
        return $this->belongsTo(OrderDetail::class);
    }

    public function recipeVersion(): BelongsTo
    {
        return $this->belongsTo(KitchenRecipeVersion::class, 'recipe_version_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
