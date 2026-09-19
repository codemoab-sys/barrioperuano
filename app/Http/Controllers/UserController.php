<?php

namespace App\Http\Controllers;

use App\Models\CashRegister;
use App\Models\Area;
use App\Models\Expense;
use App\Models\LoginAudit;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function index()
    {
        // Ordenamos: Primero Admins, luego Cajeros, luego Mozos
        $users = User::with('areas')->orderByRaw("FIELD(role, 'admin', 'cashier_waiter', 'cashier', 'waiter', 'kitchen')")->get();
        $areas = Area::where('is_active', true)->orderBy('name')->get();
        return view('users.index', compact('users', 'areas'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:bape_users,email',
            'password' => 'required|min:6',
            'role' => 'required|in:admin,cashier,cashier_waiter,waiter,kitchen',
            'all_areas' => 'nullable|boolean',
            'area_ids' => 'nullable|array',
            'area_ids.*' => 'integer|exists:bape_areas,id',
        ]);

        $allAreas = $request->boolean('all_areas');
        if (!$allAreas && !$request->filled('area_ids')) {
            return back()->withErrors(['area_ids' => 'Selecciona al menos una zona o marca acceso a todas.'])->withInput();
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'all_areas' => $allAreas,
        ]);

        $user->areas()->sync($allAreas ? [] : $request->input('area_ids', []));

        return redirect()->back()->with('success', 'Usuario registrado correctamente.');
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:bape_users,email,'.$user->id,
            'role' => 'required|in:admin,cashier,cashier_waiter,waiter,kitchen',
            'all_areas' => 'nullable|boolean',
            'area_ids' => 'nullable|array',
            'area_ids.*' => 'integer|exists:bape_areas,id',
        ]);

        $allAreas = $request->boolean('all_areas');
        if (!$allAreas && !$request->filled('area_ids')) {
            return back()->withErrors(['area_ids' => 'Selecciona al menos una zona o marca acceso a todas.'])->withInput();
        }

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'all_areas' => $allAreas,
        ];

        // Solo actualizamos contraseña si el campo no está vacío
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);
        $user->areas()->sync($allAreas ? [] : $request->input('area_ids', []));

        return redirect()->back()->with('success', 'Datos actualizados.');
    }

    public function destroy(User $user)
    {
        if ($user->id === Auth::id()) {
            return redirect()->back()->with('error', 'No puedes eliminar tu propia cuenta mientras estás conectado.');
        }

        $hasSensitiveHistory = Order::where('user_id', $user->id)->exists()
            || Expense::where('user_id', $user->id)->exists()
            || CashRegister::where('user_id', $user->id)->exists()
            || LoginAudit::where('user_id', $user->id)->exists();

        if ($hasSensitiveHistory) {
            return redirect()->back()->with('error', 'No se puede eliminar al usuario porque tiene historial operativo asociado: ventas, gastos, caja o auditoría de acceso.');
        }

        $user->delete();

        return redirect()->back()->with('success', 'Usuario eliminado del sistema.');
    }
}
