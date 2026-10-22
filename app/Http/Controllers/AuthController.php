<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credenciales = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Solo usuarios activos pueden iniciar sesión. Con "Recordarme" Laravel deja la cookie remember_web
        // (30 días, config/auth.php): vuelve a entrar aunque la sesión haya vencido. Un usuario desactivado
        // queda fuera igual (middleware "activo") y al cerrar sesión la cookie se borra.
        if (! Auth::attempt([...$credenciales, 'activo' => 1], $request->boolean('remember'))) {
            // Con la contraseña correcta se avisa que el usuario está desactivado; si no, solo que no son válidas.
            $desactivado = Auth::validate($credenciales) && ! User::where('email', $credenciales['email'])->value('activo');
            throw ValidationException::withMessages([
                'email' => $desactivado
                    ? 'Su usuario está desactivado. Comuníquese con el administrador.'
                    : 'Las credenciales no son válidas.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        // logout() también borra la cookie de "Recordarme" y cambia el remember_token del usuario.
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
