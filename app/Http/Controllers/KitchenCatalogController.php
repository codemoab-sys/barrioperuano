<?php

namespace App\Http\Controllers;

use App\Exceptions\KitchenIdempotencyConflict;
use App\Models\Kitchen\KitchenCategory;
use App\Models\Kitchen\KitchenInventoryMovement;
use App\Models\Kitchen\KitchenRecipeItem;
use App\Models\Kitchen\KitchenSupply;
use App\Services\KitchenInventoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class KitchenCatalogController extends Controller
{
    public function categoriesIndex()
    {
        $categories = KitchenCategory::query()
            ->withCount('supplies')
            ->orderBy('name')
            ->paginate(50);

        return view('kitchen-inventory.categories.index', compact('categories'));
    }

    public function categoryStore(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ], [], ['name' => 'nombre']);

        $data['name'] = trim($data['name']);
        $duplicate = KitchenCategory::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['name' => 'Ya existe una categoría con ese nombre.']);
        }

        KitchenCategory::create(['name' => $data['name'], 'is_active' => true]);

        return redirect()
            ->route('kitchen_inventory.categories.index')
            ->with('success', 'Categoría creada.');
    }

    public function categoryUpdate(Request $request, KitchenCategory $category)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ], [], ['name' => 'nombre']);

        $data['name'] = trim($data['name']);
        $duplicate = KitchenCategory::query()
            ->where('id', '!=', $category->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['name' => 'Ya existe una categoría con ese nombre.']);
        }

        $category->update([
            'name' => $data['name'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('kitchen_inventory.categories.index')
            ->with('success', 'Categoría actualizada.');
    }

    public function categoryDestroy(KitchenCategory $category)
    {
        if ($category->supplies()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'La categoría tiene insumos y no puede eliminarse. Desactívela para impedir su uso futuro.',
            ]);
        }

        $category->delete();

        return redirect()
            ->route('kitchen_inventory.categories.index')
            ->with('success', 'Categoría eliminada.');
    }

    public function index(Request $request)
    {
        $query = KitchenSupply::query()->with('category')->orderBy('name');

        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->input('category_id'));
        }
        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.trim((string) $request->input('search')).'%');
        }
        if ($request->input('status') === 'inactive') {
            $query->where('is_active', false);
        } elseif ($request->input('status') !== 'all') {
            $query->where('is_active', true);
        }

        $supplies = $query->paginate(50)->withQueryString();
        $categories = KitchenCategory::query()->orderBy('name')->get();

        return view('kitchen-inventory.supplies.index', compact('supplies', 'categories'));
    }

    public function create()
    {
        $categories = KitchenCategory::query()->where('is_active', true)->orderBy('name')->get();

        return view('kitchen-inventory.supplies.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:bape_kitchen_categories,id'],
            'name' => ['required', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:30'],
        ], [], [
            'category_id' => 'categoría',
            'name' => 'nombre',
            'unit' => 'unidad',
        ]);

        $category = KitchenCategory::query()->findOrFail($data['category_id']);
        if (! $category->is_active) {
            throw ValidationException::withMessages(['category_id' => 'La categoría seleccionada está desactivada.']);
        }

        $data['name'] = trim($data['name']);
        $duplicate = KitchenSupply::query()
            ->where('category_id', $category->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['name' => 'Ya existe un insumo con ese nombre en la categoría seleccionada.']);
        }

        KitchenSupply::create([
            'category_id' => $category->id,
            'name' => $data['name'],
            'unit' => trim($data['unit']),
            'stock_current' => 0,
            'is_active' => true,
        ]);

        $message = 'Insumo creado con stock 0. Registre la entrada inicial desde "Ajustar stock".';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect()
            ->route('kitchen_inventory.supplies.index')
            ->with('success', $message);
    }

    public function edit(KitchenSupply $supply)
    {
        $categories = KitchenCategory::query()
            ->where(function ($query) use ($supply) {
                $query->where('is_active', true)
                    ->orWhere('id', $supply->category_id);
            })
            ->orderBy('name')
            ->get();

        return view('kitchen-inventory.supplies.edit', compact('supply', 'categories'));
    }

    public function update(Request $request, KitchenSupply $supply)
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:bape_kitchen_categories,id'],
            'name' => ['required', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:30'],
            'is_active' => ['nullable', 'boolean'],
        ], [], [
            'category_id' => 'categoría',
            'name' => 'nombre',
            'unit' => 'unidad',
        ]);

        $category = KitchenCategory::query()->findOrFail($data['category_id']);
        if (! $category->is_active && $supply->category_id !== $category->id) {
            throw ValidationException::withMessages(['category_id' => 'La categoría seleccionada está desactivada.']);
        }

        $data['name'] = trim($data['name']);
        $duplicate = KitchenSupply::query()
            ->where('category_id', $category->id)
            ->where('id', '!=', $supply->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['name' => 'Ya existe un insumo con ese nombre en la categoría seleccionada.']);
        }

        $supply->update([
            'category_id' => $category->id,
            'name' => $data['name'],
            'unit' => trim($data['unit']),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('kitchen_inventory.supplies.index')
            ->with('success', 'Insumo actualizado.');
    }

    public function destroy(KitchenSupply $supply)
    {
        $hasMovements = KitchenInventoryMovement::query()->where('kitchen_supply_id', $supply->id)->exists();
        $hasRecipeItems = KitchenRecipeItem::query()->where('kitchen_supply_id', $supply->id)->exists();

        if ($hasMovements || $hasRecipeItems) {
            throw ValidationException::withMessages([
                'supply' => 'El insumo tiene historial de movimientos o recetas y no puede eliminarse. Desactívelo para impedir su uso futuro.',
            ]);
        }

        $supply->delete();

        return redirect()
            ->route('kitchen_inventory.supplies.index')
            ->with('success', 'Insumo eliminado.');
    }

    public function adjustForm(KitchenSupply $supply)
    {
        return view('kitchen-inventory.supplies.adjust', compact('supply'));
    }

    public function adjust(Request $request, KitchenSupply $supply, KitchenInventoryService $inventory)
    {
        $data = $request->validate([
            'form_uuid' => ['required', 'uuid'],
            'type' => ['required', 'in:entry,adjustment_in,adjustment_out,waste'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'note' => ['required', 'string', 'min:5', 'max:500'],
        ], [], [
            'type' => 'tipo',
            'quantity' => 'cantidad',
            'note' => 'motivo',
            'form_uuid' => 'formulario',
        ]);

        try {
            $inventory->adjustStock(
                'ui-adjust:'.$data['form_uuid'],
                $data['type'],
                $supply->id,
                (float) $data['quantity'],
                $request->user()?->id,
                trim($data['note']),
            );
        } catch (KitchenIdempotencyConflict) {
            throw ValidationException::withMessages([
                'form_uuid' => 'Este formulario ya fue procesado con otros datos. Recargue la página e intente de nuevo.',
            ]);
        }

        return redirect()
            ->route('kitchen_inventory.supplies.index')
            ->with('success', 'Movimiento registrado en el kardex.');
    }
}
