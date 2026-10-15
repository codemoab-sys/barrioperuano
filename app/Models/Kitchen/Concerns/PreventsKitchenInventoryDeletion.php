<?php

namespace App\Models\Kitchen\Concerns;

use RuntimeException;

trait PreventsKitchenInventoryDeletion
{
    public static function bootPreventsKitchenInventoryDeletion(): void
    {
        static::deleting(function ($model): void {
            throw new RuntimeException('Los registros de inventario de cocina no pueden eliminarse; el historial de auditoría debe conservarse.');
        });
    }
}
