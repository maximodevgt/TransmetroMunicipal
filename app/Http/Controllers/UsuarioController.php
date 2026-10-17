<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Estacion;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Administración de usuarios: un administrador municipal y operadores asignados a estaciones.
 * Los usuarios no se eliminan (tienen historial en bitácora y transacciones): se desactivan.
 */
class UsuarioController extends Controller
{
    public function index(Request $request): View
    {
        $rol = $request->query('rol');

        return view('usuarios.index', [
            'rol' => $rol,
            'usuarios' => User::with('estacion')
                ->when(in_array($rol, [User::ROL_ADMINISTRADOR, User::ROL_OPERADOR], true), fn ($q) => $q->where('rol', $rol))
                ->orderBy('id')
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('usuarios.form', [
            'usuario' => new User(['rol' => User::ROL_OPERADOR, 'activo' => true]),
            'estaciones' => Estacion::orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $usuario = User::create($this->validar($request));
        Bitacora::registrar('crear', $usuario, "Registro del usuario {$usuario->email} ({$usuario->rol})");

        return redirect()->route('usuarios.index')->with('ok', "Usuario {$usuario->name} creado.");
    }

    public function edit(User $usuario): View
    {
        return view('usuarios.form', ['usuario' => $usuario, 'estaciones' => Estacion::orderBy('nombre')->get()]);
    }

    public function update(Request $request, User $usuario): RedirectResponse
    {
        $datos = $this->validar($request, $usuario);

        if ($usuario->is($request->user()) && ($datos['rol'] !== User::ROL_ADMINISTRADOR || ! $datos['activo'])) {
            return back()->withInput()->with('error', 'No puede quitarse el rol de administrador ni desactivarse a sí mismo.');
        }

        if (blank($datos['password'] ?? null)) {
            unset($datos['password']);
        }

        $usuario->update($datos);
        Bitacora::registrar('editar', $usuario, "Actualización del usuario {$usuario->email}");

        return redirect()->route('usuarios.index')->with('ok', "Usuario {$usuario->name} actualizado.");
    }

    private function validar(Request $request, ?User $usuario = null): array
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($usuario)],
            'rol' => ['required', Rule::in([User::ROL_ADMINISTRADOR, User::ROL_OPERADOR])],
            // Cada operador pertenece a una estación; el administrador municipal no.
            'estacion_id' => ['nullable', 'required_if:rol,'.User::ROL_OPERADOR, 'exists:estaciones,id'],
            'password' => [$usuario ? 'nullable' : 'required', 'confirmed', Password::min(8)],
        ], [
            'estacion_id.required_if' => 'Un operador debe estar asignado a una estación.',
        ]);

        $datos['activo'] = $request->boolean('activo');
        if ($datos['rol'] === User::ROL_ADMINISTRADOR) {
            $datos['estacion_id'] = null;
        }

        return $datos;
    }
}
