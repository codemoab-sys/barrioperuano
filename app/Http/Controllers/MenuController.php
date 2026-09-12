<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class MenuController extends Controller
{
    public function index()
    {
        $currency = Setting::where('key', 'currency_symbol')->value('value') ?? 'S/';
        $companyName = Setting::where('key', 'company_name')->value('value') ?? 'Mi Restaurante';
        $companyLogo = Setting::where('key', 'company_logo')->value('value');

        // ── Colores de marca (desde configuración) ──
        $brand = Setting::whereIn('key', [
            'color_primary', 'color_primary_hover', 'color_primary_soft',
        ])->pluck('value', 'key')->toArray();
        $brandColor = [
            'primary'       => $brand['color_primary']       ?? '#ff8c00',
            'primary_hover' => $brand['color_primary_hover'] ?? '#e07b00',
            'primary_soft'  => $brand['color_primary_soft']  ?? '#fff4e6',
        ];

        $baseQuery = Product::where('is_active', true)->where('is_saleable', true);

        $promotions = (clone $baseQuery)->whereNotNull('promotional_price')->where('promotional_price', '>', 0)->get();
        $chefRecommendations = (clone $baseQuery)->where('is_chef_recommendation', true)->get();
        $newProducts = (clone $baseQuery)->where('is_new', true)->get();

        // Calcular lo más pedido
        $topProductsIds = DB::table('bape_order_details')
            ->join('bape_orders', 'bape_order_details.order_id', '=', 'bape_orders.id')
            ->where('bape_orders.status', 'completed')
            ->select('bape_order_details.product_id', DB::raw('SUM(bape_order_details.quantity) as total_qty'))
            ->groupBy('bape_order_details.product_id')
            ->orderByDesc('total_qty')
            ->limit(6)
            ->pluck('product_id')
            ->toArray();

        $mostPopular = Product::whereIn('id', $topProductsIds)
            ->where('is_active', true)
            ->where('is_saleable', true)
            ->get();

        // Categorías con sus productos (que no tengan los flags especiales obligatoriamente, pero se muestran todos)
        // Opcionalmente se muestran todos organizados
        $categories = Category::with(['products' => function($q) {
            $q->where('is_active', true)->where('is_saleable', true);
        }])->where('is_active', true)->get();

        return view('menu.index', compact(
            'currency', 'companyName', 'companyLogo',
            'promotions', 'chefRecommendations', 'newProducts', 'mostPopular', 'categories'
        ))->with('brandColor', $brandColor);
    }
}
