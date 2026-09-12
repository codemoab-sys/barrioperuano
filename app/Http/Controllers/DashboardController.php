<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Product;
use App\Models\Area;
use App\Models\Setting;
use App\Models\OrderDetail;
use App\Models\Category;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // ─── Config ───────────────────────────────────────────────────────────
        $currency = Setting::where('key', 'currency_symbol')->value('value') ?? 'S/';
        $today    = Carbon::today();
        $year     = Carbon::now()->year;

        // ─── KPIs del día ─────────────────────────────────────────────────────
        $totalSalesToday = Order::where('status', 'completed')
                                ->whereDate('created_at', $today)
                                ->sum('total');

        $ordersCountToday = Order::where('status', 'completed')
                                 ->whereDate('created_at', $today)
                                 ->count();

        $activeTables = Order::where('status', 'pending')->count();

        $lowStockProducts = Product::where('is_active', true)
                                   ->where('stock', '<=', 5)
                                   ->count();

        // ─── Ventas del mes actual ─────────────────────────────────────────────
        $totalSalesMonth = Order::where('status', 'completed')
                                ->whereYear('created_at', $year)
                                ->whereMonth('created_at', Carbon::now()->month)
                                ->sum('total');

        // ─── Meta mensual (de configuración o 5000 por defecto) ───────────────
        $monthlyGoal = (float) (Setting::where('key', 'monthly_goal')->value('value') ?? 5000);
        $goalPercent = $monthlyGoal > 0
            ? min(100, round(($totalSalesMonth / $monthlyGoal) * 100))
            : 0;

        // ─── Datos mensuales para el Line Chart (año actual) ──────────────────
        $monthlySalesRaw = Order::where('status', 'completed')
            ->whereYear('created_at', $year)
            ->select(DB::raw('MONTH(created_at) as month'), DB::raw('SUM(total) as total'))
            ->groupBy('month')
            ->pluck('total', 'month')
            ->toArray();

        $monthlyOrdersRaw = Order::where('status', 'completed')
            ->whereYear('created_at', $year)
            ->select(DB::raw('MONTH(created_at) as month'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('month')
            ->pluck('cnt', 'month')
            ->toArray();

        $monthlyReservationsRaw = Reservation::whereYear('created_at', $year)
            ->select(DB::raw('MONTH(created_at) as month'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('month')
            ->pluck('cnt', 'month')
            ->toArray();

        // Rellenar los 12 meses con 0 donde no haya datos
        $monthlySales        = [];
        $monthlyOrders       = [];
        $monthlyReservations = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlySales[]        = round($monthlySalesRaw[$m] ?? 0, 2);
            $monthlyOrders[]       = $monthlyOrdersRaw[$m] ?? 0;
            $monthlyReservations[] = $monthlyReservationsRaw[$m] ?? 0;
        }

        // ─── Datos para el Radar Chart (por categoría) ────────────────────────
        $categoryStats = DB::table('bape_order_details')
            ->join('bape_orders', 'bape_order_details.order_id', '=', 'bape_orders.id')
            ->join('bape_products', 'bape_order_details.product_id', '=', 'bape_products.id')
            ->join('bape_categories', 'bape_products.category_id', '=', 'bape_categories.id')
            ->where('bape_orders.status', 'completed')
            ->select('bape_categories.name', DB::raw('SUM(bape_order_details.quantity) as total_qty'))
            ->groupBy('bape_categories.id', 'bape_categories.name')
            ->orderByDesc('total_qty')
            ->limit(6)
            ->get();

        $radarLabels = $categoryStats->pluck('name')->toArray();
        $radarData   = $categoryStats->pluck('total_qty')->map(fn($v) => (int)$v)->toArray();

        // Si no hay datos suficientes, usar placeholders
        if (count($radarLabels) < 3) {
            $radarLabels = ['Entradas', 'Platos', 'Bebidas', 'Postres', 'Especiales'];
            $radarData   = [0, 0, 0, 0, 0];
        }

        // ─── Monitor de Mesas ─────────────────────────────────────────────────
        $areas = Area::with(['tables' => function ($q) {
            $q->with(['orders' => function ($o) {
                $o->where('status', 'pending');
            }]);
        }])->get();

        // ─── Top Productos ────────────────────────────────────────────────────
        $topProducts = DB::table('bape_order_details')
            ->join('bape_orders', 'bape_order_details.order_id', '=', 'bape_orders.id')
            ->join('bape_products', 'bape_order_details.product_id', '=', 'bape_products.id')
            ->where('bape_orders.status', 'completed')
            ->select('bape_products.name', 'bape_products.image', DB::raw('SUM(bape_order_details.quantity) as total_qty'))
            ->groupBy('bape_products.id', 'bape_products.name', 'bape_products.image')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        // ─── Datos legacy (por si alguna vista los usa) ───────────────────────
        $chartLabels = [];
        $chartValues = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $chartLabels[] = $date->locale('es')->isoFormat('dd D');
            $chartValues[] = Order::where('status', 'completed')
                                   ->whereDate('created_at', $date->format('Y-m-d'))
                                   ->sum('total');
        }

        return view('dashboard', compact(
            'currency',
            'totalSalesToday',
            'ordersCountToday',
            'activeTables',
            'lowStockProducts',
            'totalSalesMonth',
            'monthlyGoal',
            'goalPercent',
            'monthlySales',
            'monthlyOrders',
            'monthlyReservations',
            'radarLabels',
            'radarData',
            'areas',
            'topProducts',
            'chartLabels',
            'chartValues'
        ));
    }
}