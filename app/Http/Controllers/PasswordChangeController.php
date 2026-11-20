<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PasswordChangeController extends Controller
{
    public function show()
    {
        return view('auth.force-password-change');
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:6', 'max:255', 'confirmed'],
        ], [
            'password.min' => 'La contraseña debe tener al menos 6 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        $user = $request->user();
        $user->password = $validated['password'];
        $user->password_change_required = false;
        $user->save();

        $destination = $user->role === 'admin' ? 'dashboard' : 'pos.index';

        return redirect()->route($destination)->with('success', 'Tu contraseña fue actualizada correctamente.');
    }
}
