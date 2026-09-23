<?php

namespace App\Http\Controllers;

use App\Models\CashRegister;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashRegisterController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', 'in:all,open,closed'],
            'user_id' => ['nullable', 'exists:users,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $query = CashRegister::query()
            ->with([
                'user:id,name',
                'orders' => fn ($query) => $query
                    ->where('status', 'completed')
                    ->where('payment_method', 'cash')
                    ->select('id', 'cash_register_id', 'total', 'payment_method', 'status'),
                'expenses' => fn ($query) => $query
                    ->select('id', 'cash_register_id', 'amount'),
            ]);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('opening_time', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('opening_time', '<=', $request->date_to);
        }

        $registers = $query
            ->orderByDesc('opening_time')
            ->paginate(15)
            ->appends($request->query());

        $currencySymbol = Setting::where('key', 'currency_symbol')->value('value') ?? 'S/';
        $users = User::orderBy('name')->get(['id', 'name']);

        return view('cash_registers.index', compact('registers', 'currencySymbol', 'users'));
    }

    public function create()
    {
        if ($cashRegister = CashRegister::current()) {
            return redirect()->route('dashboard')->with('info', 'Ya hay un turno de caja abierto por ' . $cashRegister->user->name . '.');
        }
        return view('cash_registers.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'opening_amount' => 'required|numeric|min:0'
        ]);

        if (CashRegister::current()) {
            return redirect()->route('dashboard')->with('error', 'Ya hay una caja abierta.');
        }

        DB::transaction(function () use ($request) {
            if (CashRegister::query()->where('status', 'open')->lockForUpdate()->exists()) {
                abort(409, 'Ya hay una caja abierta.');
            }

            CashRegister::create([
                'user_id' => auth()->id(),
                'opening_time' => Carbon::now(),
                'opening_amount' => $request->opening_amount,
                'status' => 'open',
            ]);
        });

        return redirect()->route('pos.index')->with('success', 'Turno de caja abierto correctamente.');
    }

    public function close()
    {
        $cashRegister = CashRegister::current();

        if (!$cashRegister) {
            return redirect()->route('dashboard')->with('error', 'No tienes ninguna caja abierta para cerrar.');
        }

        abort_unless($cashRegister->canBeClosedBy(auth()->user()), 403);

        // Calcular el esperado:
        // Monto inicial + Total ventas efectivo - Total gastos
        $totalSalesCash = $cashRegister->orders()->where('payment_method', 'cash')->where('status', 'completed')->sum('total');
        $totalExpenses = $cashRegister->expenses()->sum('amount');
        
        $expectedAmount = $cashRegister->opening_amount + $totalSalesCash - $totalExpenses;

        return view('cash_registers.close', compact('cashRegister', 'expectedAmount'));
    }

    public function processClose(Request $request)
    {
        $request->validate([
            'closing_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $cashRegister = CashRegister::current();

        if (!$cashRegister) {
            return redirect()->route('dashboard')->with('error', 'No tienes ninguna caja abierta.');
        }

        abort_unless($cashRegister->canBeClosedBy(auth()->user()), 403);

        $difference = DB::transaction(function () use ($request, $cashRegister) {
            $register = CashRegister::whereKey($cashRegister->id)->lockForUpdate()->firstOrFail();
            if ($register->status !== 'open') {
                abort(409, 'La caja ya fue cerrada.');
            }

            $totalSalesCash = $register->orders()->where('payment_method', 'cash')->where('status', 'completed')->sum('total');
            $totalExpenses = $register->expenses()->sum('amount');
            $expectedAmount = (float) $register->opening_amount + $totalSalesCash - $totalExpenses;
            $difference = (float) $request->closing_amount - $expectedAmount;

            $register->update([
                'closing_time' => Carbon::now(),
                'closing_amount' => $request->closing_amount,
                'expected_amount' => $expectedAmount,
                'difference' => $difference,
                'status' => 'closed',
                'notes' => $request->input('notes'),
            ]);

            return $difference;
        });

        return redirect()->route('dashboard')->with('success', 'Turno de caja cerrado correctamente. Diferencia: S/ ' . number_format($difference, 2));
    }
}
