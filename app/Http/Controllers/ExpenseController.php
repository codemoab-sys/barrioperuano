<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExpenseController extends Controller
{
    public function store(Request $request)
    {
        $cashRegister = Auth::user()?->activeCashRegister;
        if (!$cashRegister) {
            return redirect()->back()->with('error', 'Debes abrir tu turno de caja antes de registrar gastos.');
        }

        $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.1',
        ]);

        Expense::create([
            'description' => $request->description,
            'amount' => $request->amount,
            'user_id' => Auth::id(),
            'cash_register_id' => $cashRegister->id,
        ]);

        return redirect()->back()->with('success', 'Gasto registrado correctamente.');
    }

    public function destroy(Expense $expense)
    {
        if (Auth::user()?->role !== 'admin') {
            return redirect()->back()->with('error', 'Solo un administrador puede eliminar gastos registrados.');
        }

        $expense->delete();
        return redirect()->back()->with('success', 'Gasto eliminado.');
    }
}