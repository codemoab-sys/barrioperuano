<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryDriver extends Model
{
    protected $table = 'bape_delivery_drivers';
protected $fillable = ['name', 'phone', 'is_active'];

    public function deliveries()
    {
        return $this->hasMany(Delivery::class, 'driver_id');
    }

    public function activeDeliveries()
    {
        return $this->hasMany(Delivery::class, 'driver_id')
                    ->whereIn('status', ['preparing', 'on_way']);
    }
}
