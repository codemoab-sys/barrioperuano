<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientController extends Controller
{
    public function index()
    {
        // Listamos clientes con conteo de órdenes
        $clients = Client::withCount('orders')->orderBy('name')->get();
        return view('clients.index', compact('clients'));
    }

    public function create()
    {
        return view('clients.create');
    }

    // Buscar RUC/DNI en apis.net.pe
    public function searchDocument(Request $request)
    {
        $request->validate(['document' => 'required|numeric']);

        $document = trim($request->document);
        $isRuc = strlen($document) === 11;
        $isDni = strlen($document) === 8;

        if (!$isRuc && !$isDni) {
            return response()->json([
                'success' => false,
                'message' => 'El documento debe tener 8 dígitos (DNI) u 11 dígitos (RUC).'
            ], 422);
        }

        $token = env('TOKEN_BUSCAR_CLIENTES');
        $endpoint = $isRuc ? 'ruc' : 'dni';

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => 'https://api.apis.net.pe/v1/' . $endpoint . '?numero=' . $document,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Referer: https://apis.net.pe/consulta-ruc-api'
            ]
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode !== 200 || !$response) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontraron datos. Consúltelo en SUNAT y regístrelo manualmente.',
                'link'    => 'https://e-consultaruc.sunat.gob.pe/cl-ti-itmrconsruc/FrameCriterioBusquedaWeb.jsp'
            ], $httpCode ?: 500);
        }

        $data = json_decode($response, true);

        return response()->json([
            'success'       => true,
            'document_type' => $isRuc ? 'RUC' : 'DNI',
            'name'          => $data['nombre'] ?? null,
            'address'       => $data['direccion'] ?? null,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required', 'document_number' => 'nullable|unique:bape_clients']);
        $client = Client::create($request->all());

        if ($request->expectsJson()) {
            return response()->json([
                'success'          => true,
                'id'               => $client->id,
                'name'             => $client->name,
                'document_number'  => $client->document_number,
                'message'          => 'Cliente registrado.'
            ]);
        }

        return redirect()->route('clients.index')->with('success', 'Cliente registrado.');
    }

    // --- NUEVA FUNCIÓN: PERFIL 360 ---
    public function show(Client $client)
    {
        // 1. Historial de Órdenes (Completadas)
        $orders = $client->orders()
                         ->where('status', 'completed')
                         ->orderBy('created_at', 'desc')
                         ->get();

        // 2. Estadísticas Financieras
        $totalSpent = $orders->sum('total');
        $visitCount = $orders->count();
        $lastVisit = $orders->first() ? $orders->first()->created_at : null;

        // 3. Calcular Nivel VIP
        $rank = 'Nuevo';
        $badgeColor = 'secondary';
        if ($totalSpent > 1000) { $rank = 'Oro (VIP)'; $badgeColor = 'warning'; }
        elseif ($totalSpent > 500) { $rank = 'Plata'; $badgeColor = 'secondary'; }
        elseif ($totalSpent > 100) { $rank = 'Bronce'; $badgeColor = 'danger'; }

        // 4. Plato Favorito (Query avanzada)
        $favoriteDish = DB::table('bape_order_details')
            ->join('bape_orders', 'bape_order_details.order_id', '=', 'bape_orders.id')
            ->join('bape_products', 'bape_order_details.product_id', '=', 'bape_products.id')
            ->select('bape_products.name', DB::raw('SUM(bape_order_details.quantity) as total_qty'))
            ->where('bape_orders.client_id', $client->id)
            ->groupBy('bape_products.name')
            ->orderByDesc('total_qty')
            ->first();

        $favoriteProduct = $favoriteDish ? $favoriteDish->name . ' (' . $favoriteDish->total_qty . ' veces)' : 'Aún sin datos';

        return view('clients.show', compact('client', 'orders', 'totalSpent', 'visitCount', 'lastVisit', 'rank', 'badgeColor', 'favoriteProduct'));
    }

    public function edit(Client $client)
    {
        return view('clients.edit', compact('client'));
    }

    public function update(Request $request, Client $client)
    {
        $request->validate(['name' => 'required', 'document_number' => 'nullable|unique:bape_clients,document_number,'.$client->id]);
        $client->update($request->all());
        return redirect()->route('clients.index')->with('success', 'Datos actualizados.');
    }

    public function destroy(Client $client)
    {
        $client->delete();
        return redirect()->route('clients.index')->with('success', 'Cliente eliminado.');
    }
}