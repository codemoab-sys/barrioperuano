<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CashRegister;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CashRegisterController extends Controller
{
    public function index()
    {
        $registers = CashRegister::with('user')->orderBy('created_at', 'desc')->paginate(15);
        return view('cash_registers.index', compact('registers'));
    }

    public function create()
    {
        if (auth()->user()->activeCashRegister) {
            return redirect()->route('dashboard')->with('info', 'Ya tienes un turno de caja abierto.');
        }
        return view('cash_registers.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'opening_amount' => 'required|numeric|min:0'
        ]);

        if (auth()->user()->activeCashRegister) {
            return redirect()->route('dashboard')->with('error', 'Ya tienes una caja abierta.');
        }

        DB::transaction(function () use ($request) {
            $user = auth()->user()->newQuery()->lockForUpdate()->findOrFail(auth()->id());
            if ($user->activeCashRegister()->exists()) {
                abort(409, 'Ya tienes una caja abierta.');
            }

            CashRegister::create([
                'user_id' => $user->id,
                'opening_time' => Carbon::now(),
                'opening_amount' => $request->opening_amount,
                'status' => 'open',
            ]);
        });

        return redirect()->route('pos.index')->with('success', 'Turno de caja abierto correctamente.');
    }

    public function close()
    {
        $cashRegister = auth()->user()->activeCashRegister;

        if (!$cashRegister) {
            return redirect()->route('dashboard')->with('error', 'No tienes ninguna caja abierta para cerrar.');
        }

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

        $cashRegister = auth()->user()->activeCashRegister;

        if (!$cashRegister) {
            return redirect()->route('dashboard')->with('error', 'No tienes ninguna caja abierta.');
        }

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
