<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\CashRegister;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireOpenCashRegister
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && in_array($user->role, ['admin', 'cashier', 'cashier_waiter']) && !CashRegister::current()) {
            return redirect()->route('cash_registers.create')
                ->with('warning', 'Debes abrir un turno de caja antes de realizar operaciones de cobro o venta.');
        }

        return $next($request);
    }
}
