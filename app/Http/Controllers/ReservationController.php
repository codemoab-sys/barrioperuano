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
        $validated = $request->validate([
            'client_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:30',
            'reservation_time' => 'required|date|after:now',
            'people' => 'required|integer|min:1',
            'table_id' => 'nullable|exists:bape_tables,id',
            'note' => 'nullable|string|max:1000',
        ]);

        $reservationTime = Carbon::parse($validated['reservation_time']);

        if (!empty($validated['table_id'])) {
            $table = Table::findOrFail($validated['table_id']);
            if ($validated['people'] > $table->seats) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'La mesa seleccionada solo tiene capacidad para ' . $table->seats . ' personas.');
            }

            $conflict = Reservation::where('table_id', $table->id)
                ->whereIn('status', ['pending', 'confirmed'])
                ->whereBetween('reservation_time', [
                    $reservationTime->copy()->subHours(2),
                    $reservationTime->copy()->addHours(2),
                ])
                ->exists();

            if ($conflict) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'La mesa ya tiene una reserva activa dentro de ese horario.');
            }
        }

        Reservation::create($validated);

        return redirect()->back()->with('success', 'Reserva agendada correctamente.');
    }

    public function updateStatus(Request $request, Reservation $reservation)
    {
        $validated = $request->validate(['status' => 'required|in:confirmed,cancelled']);

        if ($reservation->status === 'cancelled') {
            return redirect()->back()
                ->with('error', 'Una reserva cancelada no puede volver a confirmarse.');
        }

        $reservation->update(['status' => $validated['status']]);

        $msg = $validated['status'] === 'confirmed' ? 'Reserva confirmada.' : 'Reserva cancelada.';
        return redirect()->back()->with('success', $msg);
    }

    public function destroy(Reservation $reservation)
    {
        $reservation->delete();
        return redirect()->back()->with('success', 'Reserva eliminada.');
    }
}
