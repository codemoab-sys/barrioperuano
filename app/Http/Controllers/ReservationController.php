<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Table;
use App\Models\Client;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ReservationController extends Controller
{
    public function index()
    {
        // Traemos reservas futuras y las de hoy (incluso si pasaron hace unas horas)
        $reservations = Reservation::where('reservation_time', '>=', Carbon::now()->startOfDay())
            ->orderBy('reservation_time', 'asc')
            ->with('table')
            ->get();

        $tables = Table::where('status', 'available')->get();
        $clients = Client::select('id', 'name', 'document_number', 'phone')->orderBy('name')->get();
        $clientsData = $clients->map(fn ($c) => [
            'id'       => $c->id,
            'name'     => $c->name,
            'document' => (string) ($c->document_number ?? ''),
            'phone'    => (string) ($c->phone ?? ''),
        ])->values();

        return view('reservations.index', compact('reservations', 'tables', 'clients', 'clientsData'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'client_name' => 'required',
            // CORRECCIÓN: Quitamos 'after:now' para evitar problemas de zona horaria
            'reservation_time' => 'required|date',
            'people' => 'required|integer|min:1',
            'table_id' => 'nullable|exists:bape_tables,id'
        ]);

        if ($request->filled('table_id') && Reservation::where('table_id', $request->table_id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('reservation_time', Carbon::parse($request->reservation_time))
            ->exists()) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'La mesa ya tiene una reserva activa para ese horario.');
        }

        Reservation::create($request->all());

        return redirect()->back()->with('success', 'Reserva agendada correctamente.');
    }

    public function updateStatus(Request $request, Reservation $reservation)
    {
        $request->validate(['status' => 'required|in:confirmed,cancelled']);
        $reservation->update(['status' => $request->status]);

        $msg = $request->status == 'confirmed' ? 'Reserva confirmada.' : 'Reserva cancelada.';
        return redirect()->back()->with('success', $msg);
    }

    public function destroy(Reservation $reservation)
    {
        $reservation->delete();
        return redirect()->back()->with('success', 'Reserva eliminada.');
    }
}
