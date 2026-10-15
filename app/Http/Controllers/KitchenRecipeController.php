<?php

namespace App\Http\Controllers;

use App\Models\Kitchen\KitchenRecipe;
use App\Models\Kitchen\KitchenRecipeItem;
use App\Models\Kitchen\KitchenRecipeVersion;
use App\Models\Kitchen\KitchenSupply;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KitchenRecipeController extends Controller
{
    public function index(Request $request)
    {
        $query = KitchenRecipe::query()->with('product')->orderBy('id');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$search}%"));
            });
        }
        if ($request->input('status') === 'inactive') {
            $query->where('is_active', false);
        } elseif ($request->input('status') !== 'all') {
            $query->where('is_active', true);
        }

        $recipes = $query->paginate(50)->withQueryString();
        $recipes->getCollection()->load(['versions' => fn ($q) => $q->where('status', 'published')->orderByDesc('version')]);
        $recipes->getCollection()->each->loadCount('versions');

        $pendingByProduct = $this->pendingOrderCounts($recipes->pluck('product_id')->filter()->unique()->values()->all());

        return view('kitchen-inventory.recipes.index', compact('recipes', 'pendingByProduct'));
    }

    public function create()
    {
        $recipeProductIds = KitchenRecipe::query()->pluck('product_id');
        $products = Product::query()
            ->whereNotIn('id', $recipeProductIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('kitchen-inventory.recipes.create', compact('products'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:bape_products,id'],
            'name' => ['required', 'string', 'max:100'],
        ], [], ['product_id' => 'producto', 'name' => 'nombre']);

        $exists = KitchenRecipe::query()->where('product_id', $data['product_id'])->exists();
        if ($exists) {
            throw ValidationException::withMessages(['product_id' => 'El producto ya tiene una receta registrada.']);
        }

        $recipe = KitchenRecipe::create([
            'product_id' => $data['product_id'],
            'name' => trim($data['name']),
            'is_active' => true,
        ]);

        return redirect()
            ->route('kitchen_inventory.recipes.show', $recipe)
            ->with('success', 'Receta creada. Cree una versión borrador para definir sus insumos.');
    }

    public function show(KitchenRecipe $recipe)
    {
        $recipe->load(['product', 'versions' => fn ($q) => $q->orderByDesc('version')]);
        $recipe->versions->each->loadCount('items');

        $pending = $this->pendingOrderCounts([$recipe->product_id])[$recipe->product_id] ?? 0;

        return view('kitchen-inventory.recipes.show', compact('recipe', 'pending'));
    }

    public function update(Request $request, KitchenRecipe $recipe)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ], [], ['name' => 'nombre']);

        $recipe->update(['name' => trim($data['name'])]);

        return redirect()
            ->route('kitchen_inventory.recipes.show', $recipe)
            ->with('success', 'Receta actualizada.');
    }

    public function storeVersion(KitchenRecipe $recipe)
    {
        $version = DB::transaction(function () use ($recipe) {
            $locked = KitchenRecipe::query()->lockForUpdate()->findOrFail($recipe->id);

            $next = ((int) KitchenRecipeVersion::query()->where('recipe_id', $locked->id)->max('version')) + 1;

            return KitchenRecipeVersion::create([
                'recipe_id' => $locked->id,
                'version' => $next,
                'status' => 'draft',
                'published_at' => null,
            ]);
        });

        return redirect()
            ->route('kitchen_inventory.versions.edit', $version)
            ->with('success', "Versión borrador #{$version->version} creada. Agregue al menos un insumo antes de publicarla.");
    }

    public function versionEdit(KitchenRecipeVersion $version)
    {
        if ($version->status !== 'draft') {
            throw ValidationException::withMessages(['version' => 'Solo los borradores pueden editarse. Las versiones publicadas son inmutables.']);
        }

        $version->load(['recipe.product', 'items.supply']);
        $supplies = KitchenSupply::query()->where('is_active', true)->orderBy('name')->get();

        return view('kitchen-inventory.recipes.edit-draft', compact('version', 'supplies'));
    }

    public function versionUpdate(Request $request, KitchenRecipeVersion $version)
    {
        if ($version->status !== 'draft') {
            throw ValidationException::withMessages(['version' => 'Solo los borradores pueden editarse. Las versiones publicadas son inmutables.']);
        }

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.kitchen_supply_id' => ['required', 'integer', 'exists:bape_kitchen_supplies,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
        ], [], [
            'items' => 'insumos',
            'items.*.kitchen_supply_id' => 'insumo',
            'items.*.quantity' => 'cantidad',
        ]);

        $supplyIds = array_map('intval', array_column($data['items'], 'kitchen_supply_id'));
        if (count($supplyIds) !== count(array_unique($supplyIds))) {
            throw ValidationException::withMessages(['items' => 'Un mismo insumo no puede repetirse en la versión.']);
        }

        $activeCount = KitchenSupply::query()
            ->whereKey($supplyIds)
            ->where('is_active', true)
            ->count();
        if ($activeCount !== count($supplyIds)) {
            throw ValidationException::withMessages(['items' => 'Uno de los insumos seleccionados está desactivado.']);
        }

        DB::transaction(function () use ($version, $data) {
            KitchenRecipeItem::query()->where('recipe_version_id', $version->id)->delete();

            foreach ($data['items'] as $item) {
                KitchenRecipeItem::create([
                    'recipe_version_id' => $version->id,
                    'kitchen_supply_id' => (int) $item['kitchen_supply_id'],
                    'quantity' => (float) $item['quantity'],
                ]);
            }
        });

        return redirect()
            ->route('kitchen_inventory.recipes.show', $version->recipe_id)
            ->with('success', 'Borrador guardado. Publíquelo para que surta efecto en el consumo.');
    }

    public function publish(KitchenRecipeVersion $version)
    {
        $published = DB::transaction(function () use ($version) {
            $locked = KitchenRecipeVersion::query()->lockForUpdate()->findOrFail($version->id);

            if ($locked->status === 'published') {
                return false;
            }

            $hasItems = KitchenRecipeItem::query()->where('recipe_version_id', $locked->id)->exists();
            if (! $hasItems) {
                throw ValidationException::withMessages(['version' => 'No se puede publicar una versión sin insumos.']);
            }

            $hasInactive = KitchenRecipeItem::query()
                ->where('recipe_version_id', $locked->id)
                ->whereHas('supply', fn ($q) => $q->where('is_active', false))
                ->exists();
            if ($hasInactive) {
                throw ValidationException::withMessages(['version' => 'La versión contiene insumos desactivados. Edite el borrador antes de publicar.']);
            }

            $locked->update(['status' => 'published', 'published_at' => now()]);

            return true;
        });

        return redirect()
            ->route('kitchen_inventory.recipes.show', $version->recipe_id)
            ->with('success', $published
                ? 'Versión publicada. A partir de ahora el consumo usará esta versión.'
                : 'La versión ya estaba publicada.');
    }

    public function deactivate(KitchenRecipe $recipe)
    {
        try {
            DB::transaction(function () use ($recipe) {
                $locked = KitchenRecipe::query()->lockForUpdate()->findOrFail($recipe->id);

                if (! $locked->is_active) {
                    return;
                }

                $pending = Order::query()
                    ->where('status', 'pending')
                    ->whereHas('details', fn ($q) => $q->where('product_id', $locked->product_id))
                    ->count();

                if ($pending > 0) {
                    throw ValidationException::withMessages([
                        'recipe' => "No es posible desactivar: {$pending} pedido(s) pendiente(s) consumirán esta receta al cobrarse. Cobre o cancele esos pedidos e intente de nuevo.",
                    ]);
                }

                $locked->update(['is_active' => false]);
            });
        } catch (ValidationException $exception) {
            throw $exception;
        }

        return redirect()
            ->route('kitchen_inventory.recipes.show', $recipe)
            ->with('success', 'Receta desactivada. Dejará de validar y consumir stock de cocina en los próximos checkouts.');
    }

    public function activate(KitchenRecipe $recipe)
    {
        $recipe->update(['is_active' => true]);

        return redirect()
            ->route('kitchen_inventory.recipes.show', $recipe)
            ->with('success', 'Receta activada. Volverá a validar y consumir stock de cocina.');
    }

    /**
     * Cuenta órdenes pendientes (mesa y delivery) que contengan cada producto.
     *
     * Nota: la guardia de desactivación reduce el riesgo de que una receta se
     * desactive con pedidos en curso, pero no elimina la carrera residual entre
     * el conteo y el commit de la desactivación y la creación de nuevos pedidos
     * pendientes (el alta en POS no toma lock de receta). La ventana es mínima
     * y el resultado, si ocurriera, sería idéntico al comportamiento histórico.
     */
    private function pendingOrderCounts(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $rows = Order::query()
            ->where('status', 'pending')
            ->whereHas('details', fn ($q) => $q->whereIn('product_id', $productIds))
            ->with('details')
            ->get(['id']);

        $counts = array_fill_keys($productIds, 0);
        foreach ($rows as $order) {
            foreach ($order->details as $detail) {
                $productId = (int) $detail->product_id;
                if (isset($counts[$productId])) {
                    $counts[$productId]++;
                }
            }
        }

        return $counts;
    }
}
