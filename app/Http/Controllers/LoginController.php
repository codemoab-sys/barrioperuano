<?php

namespace App\Http\Controllers;

use App\Models\LoginAudit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function show()
    {
        if (Auth::check()) {
            return $this->redirectUser();
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $lockoutMinutes = 15;
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $email = strtolower(trim($credentials['email']));
        $ipAddress = $request->ip();
        $user = User::where('email', $email)->first();
        $failedAttempts = LoginAudit::where('email', $email)
            ->where('success', false)
            ->where('created_at', '>=', now()->subMinutes($lockoutMinutes))
            ->count();

        if ($user?->locked_at && $user->locked_at->lte(now()->subMinutes($lockoutMinutes))) {
            $user->update(['failed_login_attempts' => 0, 'locked_at' => null]);
            $failedAttempts = 0;
        }

        if (($user && $user->locked_at) || $failedAttempts >= 5) {
            if ($user && !$user->locked_at) {
                $user->update(['locked_at' => now()]);
            }

            $this->recordLoginAttempt($email, $ipAddress, $user, 'Cuenta bloqueada por demasiados intentos fallidos.');

            return back()->withErrors([
                'email' => 'Acceso bloqueado temporalmente. Comuníquese con el administrador de TI.',
            ])->onlyInput('email');
        }

        $credentials['email'] = $email;

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            $user->update(['failed_login_attempts' => 0, 'locked_at' => null]);
            $this->recordLoginAttempt($email, $ipAddress, $user, null, true);
            $request->session()->regenerate();
            return $this->redirectUser();
        }

        $failedAttempts++;
        $this->recordLoginAttempt($email, $ipAddress, $user, 'Credenciales incorrectas.');

        if ($user) {
            $user->increment('failed_login_attempts');
            if ($failedAttempts >= 5) {
                $user->update(['locked_at' => now()]);
            }
        }

        $message = $failedAttempts >= 5
            ? 'Acceso bloqueado temporalmente. Comuníquese con el administrador de TI.'
            : 'Las credenciales no coinciden con nuestros registros.';

        return back()->withErrors([
            'email' => $message,
        ])->onlyInput('email');
    }

    protected function recordLoginAttempt(string $email, ?string $ipAddress, ?User $user, ?string $reason, bool $success = false): void
    {
        LoginAudit::create([
            'user_id' => $user?->id,
            'email' => $email,
            'ip_address' => $ipAddress,
            'success' => $success,
            'failure_reason' => $reason,
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }

    // Función auxiliar para redirigir inteligentemente
    protected function redirectUser()
    {
        $role = Auth::user()->role;

        if ($role === 'admin') {
            return redirect()->route('dashboard'); // El jefe va al panel de control
        }

        // Cajeros y Meseros van directo al trabajo (POS)
        return redirect()->route('pos.index');
    }
}
