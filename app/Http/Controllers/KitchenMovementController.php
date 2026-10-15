<?php

namespace App\Http\Controllers;

use App\Exceptions\KitchenIdempotencyConflict;
use App\Models\Kitchen\KitchenInventoryMovement;
use App\Models\Kitchen\KitchenOrderConsumption;
use App\Models\Kitchen\KitchenShortageRegularization;
use App\Models\Kitchen\KitchenSupply;
use App\Services\KitchenInventoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class KitchenMovementController extends Controller
{
    private const TYPE_LABELS = [
        'sale' => 'Venta',
        'return' => 'Devolución',
        'cancellation' => 'Cancelación',
        'entry' => 'Entrada',
        'adjustment_in' => 'Ajuste (+)',
        'adjustment_out' => 'Ajuste (-)',
        'waste' => 'Merma',
    ];

    public function movements(Request $request)
    {
        $query = KitchenInventoryMovement::query()
            ->with(['supply', 'user'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($request->filled('supply_id')) {
            $query->where('kitchen_supply_id', (int) $request->input('supply_id'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $movements = $query->paginate(50)->withQueryString();
        $supplies = KitchenSupply::query()->orderBy('name')->get(['id', 'name', 'unit']);

        return view('kitchen-inventory.movements.index', [
            'movements' => $movements,
            'supplies' => $supplies,
            'typeLabels' => self::TYPE_LABELS,
        ]);
    }

    public function regularizations(Request $request)
    {
        $query = KitchenOrderConsumption::query()
            ->with(['supply', 'order'])
            ->whereRaw(
                'bape_kitchen_order_consumptions.shortage_quantity > COALESCE((
                    SELECT SUM(r.quantity)
                    FROM bape_kitchen_shortage_regularizations r
                    WHERE r.kitchen_order_consumption_id = bape_kitchen_order_consumptions.id
                ), 0)'
            )
            ->orderBy('id');

        $consumptions = $query->paginate(50)->withQueryString();

        $regularizedTotals = KitchenShortageRegularization::query()
            ->selectRaw('kitchen_order_consumption_id, SUM(quantity) as total')
            ->whereIn('kitchen_order_consumption_id', $consumptions->pluck('id'))
            ->groupBy('kitchen_order_consumption_id')
            ->pluck('total', 'kitchen_order_consumption_id');

        return view('kitchen-inventory.regularizations.index', compact('consumptions', 'regularizedTotals'));
    }

    public function regularizationStore(Request $request, KitchenInventoryService $inventory)
    {
        $data = $request->validate([
            'form_uuid' => ['required', 'uuid'],
            'consumption_id' => ['required', 'integer', 'exists:bape_kitchen_order_consumptions,id'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'note' => ['required', 'string', 'min:5', 'max:500'],
        ], [], [
            'consumption_id' => 'consumo',
            'quantity' => 'cantidad',
            'note' => 'motivo',
            'form_uuid' => 'formulario',
        ]);

        try {
            $inventory->regularizeShortage(
                'ui-regularize:'.$data['form_uuid'],
                [[
                    'consumption_id' => (int) $data['consumption_id'],
                    'quantity' => (float) $data['quantity'],
                    'note' => trim($data['note']),
                ]],
                $request->user()?->id,
            );
        } catch (KitchenIdempotencyConflict) {
            throw ValidationException::withMessages([
                'form_uuid' => 'Este formulario ya fue procesado con otros datos. Recargue la página e intente de nuevo.',
            ]);
        }

        return redirect()
            ->route('kitchen_inventory.regularizations.index')
            ->with('success', 'Regularización registrada en el kardex.');
    }
}
