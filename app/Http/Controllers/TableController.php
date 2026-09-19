<?php

namespace App\Http\Controllers;

use App\Models\Table;
use App\Models\Area;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TableController extends Controller
{
    public function index()
    {
        $areas = Auth::user()->accessibleAreas()->with('tables')->orderByDesc('is_active')->orderBy('name')->get();
        return view('tables.index', compact('areas'));
    }

    public function storeArea(Request $request)
    {
        $request->validate(['name' => 'required|string|max:100']);
        Area::create([
            'name' => trim($request->name),
            'is_active' => true,
        ]);
        return redirect()->back()->with('success', 'Zona creada.');
    }

    public function updateArea(Request $request, Area $area)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:bape_areas,name,' . $area->id,
        ]);

        $area->update(['name' => trim($request->name)]);

        return redirect()->back()->with('success', 'Zona actualizada.');
    }

    public function deactivateArea(Area $area)
    {
        if ($area->tables()->whereHas('orders', function ($query) {
            $query->where('status', 'pending');
        })->exists()) {
            return redirect()->back()->with('error', 'No puedes desactivar una zona con órdenes pendientes.');
        }

        $area->update(['is_active' => false]);
        return redirect()->back()->with('success', 'Zona desactivada. Sus mesas se conservaron.');
    }

    public function activateArea(Area $area)
    {
        $area->update(['is_active' => true]);
        return redirect()->back()->with('success', 'Zona reactivada.');
    }

    public function destroyArea(Area $area)
    {
        if ($area->tables()->exists()) {
            return redirect()->back()->with('error', 'No puedes eliminar una zona que tiene mesas.');
        }

        $area->delete();
        return redirect()->back()->with('success', 'Zona eliminada.');
    }

    public function storeTable(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'area_id' => 'required|exists:bape_areas,id,is_active,1'
        ]);

        Table::create([
            'name' => $request->name,
            'area_id' => $request->area_id,
            'status' => 'available',
            'x_pos' => 50, // Posición por defecto visible
            'y_pos' => 50
        ]);

        return redirect()->back()->with('success', 'Mesa creada.');
    }

    public function updateTable(Request $request, Table $table)
    {
        $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $table->update(['name' => trim($request->name)]);

        return redirect()->back()->with('success', 'Mesa actualizada.');
    }

    public function destroyTable(Table $table)
    {
        $table->delete();
        return redirect()->back()->with('success', 'Mesa eliminada.');
    }

    // --- FUNCIÓN DE GUARDADO DE MAPA ---
    public function updatePositions(Request $request)
    {
        // Validamos que llegue un array
        $positions = $request->input('positions');

        if (!is_array($positions)) {
            return response()->json(['status' => 'error', 'message' => 'Datos inválidos'], 400);
        }

        foreach($positions as $pos) {
            if (!is_array($pos) || !isset($pos['id'], $pos['x'], $pos['y'])) {
                return response()->json(['status' => 'error', 'message' => 'Posición inválida'], 422);
            }

            // Buscamos la mesa y actualizamos
            $table = Table::find($pos['id']);
            if($table) {
                $table->x_pos = (int) $pos['x'];
                $table->y_pos = (int) $pos['y'];
                $table->save();
            }
        }

        return response()->json(['status' => 'success', 'message' => 'Mapa guardado correctamente']);
    }
}
