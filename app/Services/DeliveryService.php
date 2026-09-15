<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class DeliveryService
{
    public function __construct(protected \App\Services\SalesService $salesService) {}

    public function ensureCashRegisterIsOpen(): void
    {
        $cashRegister = Auth::user()?->activeCashRegister;

        if (!$cashRegister) {
            throw ValidationException::withMessages([
                'general' => ['Debes abrir tu turno de caja antes de crear o cobrar un delivery.'],
            ]);
        }
    }

    public function getCashRegisterId(): ?int
    {
        return Auth::user()?->activeCashRegister?->id;
    }

    public function restoreInventoryForOrder(Order $order): void
    {
        $this->salesService->restoreInventoryForOrder($order);
    }
}
