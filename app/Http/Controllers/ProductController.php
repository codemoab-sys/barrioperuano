<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\InventoryLog;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index()
    {
        // Listamos productos con su categoría, ordenados por los más nuevos
        $products = Product::with('category')->orderBy('created_at', 'desc')->paginate(10);
        $currencySymbol = Setting::where('key', 'currency_symbol')->value('value') ?? 'S/';

        return view('products.index', compact('products', 'currencySymbol'));
    }

    public function create()
    {
        $categories = Category::where('is_active', true)->get();
        $ingredients = Product::where('is_active', true)->get();

        return view('products.create', compact('categories', 'ingredients'));
    }

    public function store(Request $request)
    {
        // 1. Validación (Incluye el barcode único)
        $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:bape_categories,id',
            'price' => 'required|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'promotional_price' => 'nullable|numeric|min:0',
            'barcode' => 'nullable|string|max:50|unique:bape_products,barcode', // <--- NUEVO
            'image' => 'nullable|image|max:2048',
            'stock' => 'nullable|integer|min:0',
            'required_sauce_count' => $this->requiredSauceCountRules($request),
            'ingredients' => 'sometimes|array',
            'ingredients.*' => 'nullable|numeric|min:0.01',
        ]);

        $data = $request->all();

        // 2. Manejo de Imagen
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        // Checkbox de "Disponible en POS" (si no viene, es false)
        $data['is_saleable'] = $request->has('is_saleable');
        $data['is_chef_recommendation'] = $request->has('is_chef_recommendation');
        $data['is_new'] = $request->has('is_new');
        $data['is_active'] = true;
        $data['cost'] = $request->cost ?? 0;

        // 3. Crear Producto
        $product = Product::create($data);

        // 4. Guardar receta/insumos si se enviaron
        $recipeIngredients = $this->normalizeRecipeIngredients($request->input('ingredients', []));
        if (!empty($recipeIngredients)) {
            $product->ingredients()->sync($recipeIngredients);
        }

        // 5. Registro inicial en Kardex si hay stock
        if($request->stock > 0) {
            InventoryLog::create([
                'product_id' => $product->id,
                'user_id' => Auth::id(),
                'type' => 'entry',
                'quantity' => $request->stock,
                'old_stock' => 0,
                'new_stock' => $request->stock,
                'note' => 'Inventario Inicial'
            ]);
        }

        return redirect()->route('products.index')->with('success', 'Producto creado correctamente.');
    }

    public function edit(Product $product)
    {
        $categories = Category::where('is_active', true)->get();
        // Productos que pueden ser insumos (todos menos él mismo)
        $ingredients = Product::where('id', '!=', $product->id)->where('is_active', true)->get();

        return view('products.edit', compact('product', 'categories', 'ingredients'));
    }

    public function update(Request $request, Product $product)
    {
        // 1. Validación (Barcode único excepto para este producto)
        $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:bape_categories,id',
            'price' => 'required|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'promotional_price' => 'nullable|numeric|min:0',
            'barcode' => 'nullable|string|max:50|unique:bape_products,barcode,' . $product->id, // <--- NUEVO
            'image' => 'nullable|image|max:2048',
            'required_sauce_count' => $this->requiredSauceCountRules($request),
            'ingredients' => 'sometimes|array',
            'ingredients.*' => 'nullable|numeric|min:0.01',
        ]);

        $data = $request->all();

        // 2. Manejo de Imagen
        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $data['is_saleable'] = $request->has('is_saleable');
        $data['is_chef_recommendation'] = $request->has('is_chef_recommendation');
        $data['is_new'] = $request->has('is_new');
        $data['cost'] = $request->cost ?? 0;

        // 3. Actualizar
        $product->update($data);

        // Actualizar receta/insumos (Si enviaste array de ingredientes)
        $recipeIngredients = $this->normalizeRecipeIngredients($request->input('ingredients', []));
        $product->ingredients()->sync($recipeIngredients);

        return redirect()->route('products.index')->with('success', 'Producto actualizado.');
    }

    private function requiredSauceCountRules(Request $request): array
    {
        $categoryName = Category::whereKey($request->input('category_id'))->value('name');
        $supportsSauceSelection = in_array(
            mb_strtolower(trim((string) $categoryName)),
            ['alitas', 'barcos'],
            true
        );

        return [
            Rule::prohibitedIf(!$supportsSauceSelection),
            'nullable',
            'integer',
            'min:1',
            'max:10',
        ];
    }

    private function normalizeRecipeIngredients(array $ingredients): array
    {
        $normalized = [];

        foreach ($ingredients as $ingredientId => $quantity) {
            if (!is_numeric($quantity) || (float) $quantity <= 0) {
                continue;
            }

            $normalized[(int) $ingredientId] = ['quantity' => (float) $quantity];
        }

        return $normalized;
    }

    public function destroy(Product $product)
    {
        $product->update(['is_active' => false]);
        if (request()->expectsJson() || request()->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json(['success' => true, 'message' => 'Producto eliminado (desactivado).']);
        }
        return redirect()->route('products.index')->with('success', 'Producto eliminado (desactivado).');
    }

    // Funciones extra para ajustes rápidos
    public function toggleStatus(Product $product)
    {
        $product->update(['is_active' => !$product->is_active]);
        if (request()->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json(['success' => true, 'message' => $product->is_active ? 'Producto activado.' : 'Producto desactivado.']);
        }
        return back();
    }

    public function adjustStock(Request $request, Product $product)
    {
        $request->validate(['quantity' => 'required|integer|min:0', 'type' => 'required|in:add,sub,set']);

        [$oldStock, $newStock, $qty] = DB::transaction(function () use ($request, $product) {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            $oldStock = $product->stock ?? 0;
            $qty = $request->quantity;

            if ($request->type === 'set') {
                $newStock = $qty;
            } elseif ($request->type === 'sub') {
                $newStock = max(0, $oldStock - $qty);
            } else {
                $newStock = $oldStock + $qty;
            }

            $updateData = ['stock' => $newStock];
            if ($newStock > 0 && !$product->is_saleable) {
                $updateData['is_saleable'] = true;
            }
            $product->update($updateData);

            if ($qty > 0) {
                InventoryLog::create([
                    'product_id' => $product->id,
                    'user_id' => Auth::id(),
                    'type' => $request->type === 'sub' ? 'adjustment_out' : 'adjustment_in',
                    'quantity' => ($request->type === 'set' ? $qty - $oldStock : ($request->type === 'sub' ? -$qty : $qty)),
                    'old_stock' => $oldStock,
                    'new_stock' => $newStock,
                    'note' => 'Ajuste manual desde panel'
                ]);
            }

            return [$oldStock, $newStock, $qty];
        });

        if (request()->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'success' => true,
                'message' => 'Stock ajustado.',
                'new_stock' => $newStock,
            ]);
        }
        return back()->with('success', 'Stock ajustado.');
    }
}
