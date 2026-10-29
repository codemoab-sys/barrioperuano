<?php

namespace App\Services;

use App\Models\CashRegister;
use App\Models\Delivery;
use App\Models\Kitchen\KitchenInventoryOperation;
use App\Models\Kitchen\KitchenOrderConsumption;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class DeliveryService
{
    private const EPSILON = 0.0001;

    public function __construct(
        protected \App\Services\SalesService $salesService,
        protected KitchenStockControl $kitchenStockControl,
        protected KitchenInventoryService $kitchenInventoryService
    ) {}

    public function ensureCashRegisterIsOpen(): void
    {
        $cashRegister = CashRegister::current();

        if (!$cashRegister) {
            throw ValidationException::withMessages([
                'general' => ['Debes abrir tu turno de caja antes de crear o cobrar un delivery.'],
            ]);
        }
    }

    public function getCashRegisterId(): ?int
    {
        return CashRegister::current()?->id;
    }

    /**
     * Crea la orden con sus detalles, descuenta el inventario comercial y de cocina
     * y registra el delivery en estado "pending".
     *
     * No abre transacción: el llamador la envuelve, de modo que la creación y el
     * cobro (delivery directo) se confirmen como una sola unidad atómica.
     *
     * @param  array<int, array{id:int, qty:int, sauce_choices?:array, note?:string|null}>  $products
     * @param  array<string, mixed>  $data
     */
    public function createPendingDelivery(array $products, array $data): Delivery
    {
        // 1. Crear la Orden (sin mesa — delivery)
        $order = Order::create([
            'table_id'       => null,
            'user_id'        => Auth::id(),
            'client_id'      => $data['client_id'] ?? null,
            'client_name'    => $data['client_name'],
            'status'         => 'pending',
            'total'          => 0,
            'cash_register_id' => $this->getCashRegisterId(),
        ]);

        // 2. Agregar productos a la orden
        $subtotal = 0;
        foreach ($products as $item) {
            $product = Product::findOrFail($item['id']);
            $line    = $product->price * $item['qty'];
            $subtotal += $line;

            OrderDetail::create([
                'order_id'   => $order->id,
                'product_id' => $product->id,
                'quantity'   => $item['qty'],
                'price'      => $product->price,
                'status'     => 'pending',
                'note'       => $item['note'] ?? null,
                'sauce_choices' => ! empty($item['sauce_choices']) ? $item['sauce_choices'] : null,
            ]);
        }

        $order->update(['total' => $subtotal]);
        $this->salesService->applyStockReductionForOrder($order, 'Delivery');

        // Control de stock de cocina (kitchen_stock_control): consume los ingredientes
        // publicados en la receta dentro de esta misma transacción (SAVEPOINT anidado).
        // Un insumo faltante revierte también la orden, el delivery y el stock comercial.
        $this->consumeKitchenForOrder($order);

        // 3. Crear el Delivery
        return Delivery::create([
            'order_id'        => $order->id,
            'client_id'       => $data['client_id'] ?? null,
            'client_name'     => $data['client_name'],
            'client_phone'    => $data['client_phone'],
            'address'         => $data['address'] ?? '',
            'reference'       => $data['reference'] ?? null,
            'driver_id'       => $data['driver_id'] ?? null,
            'user_id'         => Auth::id(),
            'cash_register_id'=> $this->getCashRegisterId(),
            'status'          => 'pending',
            'payment_method'  => $data['payment_method'],
            'delivery_fee'    => (float) ($data['delivery_fee'] ?? 0),
            'notes'           => $data['notes'] ?? null,
            'scheduled_at'    => $data['scheduled_at'] ?? null,
        ]);
    }

    public function restoreInventoryForOrder(Order $order): void
    {
        $this->salesService->restoreInventoryForOrder($order);
    }

    /**
     * Consumo de cocina al crear un delivery (mirror de la costura de PosCheckoutService):
     * sólo con kitchen_stock_control activado, con la clave idempotente sale:order:{id} y
     * controlStock=true. Debe invocarse dentro de la transacción que crea la orden y descuenta
     * el inventario comercial para que un fallo de cocina revierta todo (SAVEPOINT anidado).
     */
    public function consumeKitchenForOrder(Order $order): void
    {
        if (!$this->kitchenStockControl->enabled()) {
            return;
        }

        $lines = $this->kitchenStockControl->consumptionLinesFor($order);
        if ($lines === []) {
            return;
        }

        $this->kitchenInventoryService->recordSale(
            'sale:order:' . $order->id,
            $lines,
            true,
            Auth::id()
        );
    }

    /**
     * Devuelve el inventario de cocina realmente consumido y aún no devuelto por una orden.
     * Cancelación comercial: tipo 'cancellation' con clave idempotente cancel:order:{id}.
     * Si nunca hubo consumo registrado (flag desactivado al crear, o productos sin receta)
     * no se emite ningún movimiento de cocina.
     */
    public function returnKitchenForOrderIfConsumed(Order $order): void
    {
        $saleOperation = KitchenInventoryOperation::query()
            ->where('event_key', 'sale:order:' . $order->id)
            ->where('operation_type', 'sale')
            ->where('status', 'confirmed')
            ->first();

        if ($saleOperation === null) {
            return;
        }

        $lines = KitchenOrderConsumption::query()
            ->where('operation_id', $saleOperation->id)
            ->get()
            ->map(fn (KitchenOrderConsumption $consumption): array => [
                'consumption_id' => $consumption->id,
                'quantity' => (float) $consumption->covered_quantity - (float) $consumption->returned_quantity,
            ])
            ->filter(fn (array $line): bool => $line['quantity'] > self::EPSILON)
            ->values()
            ->all();

        if ($lines === []) {
            return;
        }

        $this->kitchenInventoryService->recordReturn(
            'cancel:order:' . $order->id,
            $lines,
            'cancellation',
            Auth::id()
        );
    }
}
