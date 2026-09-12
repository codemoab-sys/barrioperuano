<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Category;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        // Filtro de Fechas (Default: Inicio de mes hasta hoy)
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));

        // 1. VENTAS POR CATEGORÍA (Gráfico de Dona)
        $salesByCategory = DB::table('bape_order_details')
            ->join('bape_orders', 'bape_order_details.order_id', '=', 'bape_orders.id')
            ->join('bape_products', 'bape_order_details.product_id', '=', 'bape_products.id')
            ->join('bape_categories', 'bape_products.category_id', '=', 'bape_categories.id')
            ->select('bape_categories.name', DB::raw('SUM(bape_order_details.quantity * bape_order_details.price) as total'))
            ->where('bape_orders.status', 'completed')
            ->whereDate('bape_orders.created_at', '>=', $startDate)
            ->whereDate('bape_orders.created_at', '<=', $endDate)
            ->groupBy('bape_categories.name')
            ->get();

        $catLabels = $salesByCategory->pluck('name');
        $catValues = $salesByCategory->pluck('total');

        // 2. RENDIMIENTO DE PERSONAL (Gráfico de Barras)
        $salesByWaiter = Order::select('bape_users.name', DB::raw('SUM(bape_orders.total) as total_sales'), DB::raw('COUNT(bape_orders.id) as orders_count'))
            ->join('bape_users', 'bape_orders.user_id', '=', 'bape_users.id')
            ->where('bape_orders.status', 'completed')
            ->whereDate('bape_orders.created_at', '>=', $startDate)
            ->whereDate('bape_orders.created_at', '<=', $endDate)
            ->groupBy('bape_users.name')
            ->orderByDesc('total_sales')
            ->get();

        $waiterLabels = $salesByWaiter->pluck('name');
        $waiterValues = $salesByWaiter->pluck('total_sales');

        // 3. TOP 5 PLATOS MÁS VENDIDOS
        $topProducts = DB::table('bape_order_details')
            ->join('bape_products', 'bape_order_details.product_id', '=', 'bape_products.id')
            ->join('bape_orders', 'bape_order_details.order_id', '=', 'bape_orders.id')
            ->select('bape_products.name', DB::raw('SUM(bape_order_details.quantity) as qty'), DB::raw('SUM(bape_order_details.quantity * bape_order_details.price) as revenue'))
            ->where('bape_orders.status', 'completed')
            ->whereDate('bape_orders.created_at', '>=', $startDate)
            ->whereDate('bape_orders.created_at', '<=', $endDate)
            ->groupBy('bape_products.name')
            ->orderByDesc('qty')
            ->limit(5)
            ->get();

        // 4. TOP 5 PLATOS MENOS VENDIDOS (Para tomar acción)
        $worstProducts = DB::table('bape_order_details')
            ->join('bape_products', 'bape_order_details.product_id', '=', 'bape_products.id')
            ->join('bape_orders', 'bape_order_details.order_id', '=', 'bape_orders.id')
            ->select('bape_products.name', DB::raw('SUM(bape_order_details.quantity) as qty'))
            ->where('bape_orders.status', 'completed')
            ->whereDate('bape_orders.created_at', '>=', $startDate)
            ->whereDate('bape_orders.created_at', '<=', $endDate)
            ->groupBy('bape_products.name')
            ->orderBy('qty', 'asc')
            ->limit(5)
            ->get();

        // Moneda
        $currency = \App\Models\Setting::where('key', 'currency_symbol')->value('value') ?? 'S/';

        return view('reports.index', compact(
            'startDate', 'endDate', 
            'catLabels', 'catValues', 
            'waiterLabels', 'waiterValues',
            'topProducts', 'worstProducts', 'salesByWaiter', 'currency'
        ));
    }
}