<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Parqueo;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ParqueoController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('rol:administrador', except: ['index', 'show'])];
    }

    public function index(): View
    {
        return view('parqueos.index', [
            'parqueos' => Parqueo::withCount('buses')->orderBy('nombre')->get(),
        ]);
    }

    public function create(): View
    {
        return view('parqueos.form', ['parqueo' => new Parqueo]);
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $parqueo = Parqueo::create($this->validar($request));
        } catch (QueryException $e) {
            return $this->volverConErrorBd($e);
        }
        Bitacora::registrar('crear', $parqueo, "Registro del parqueo {$parqueo->nombre} con capacidad para {$parqueo->capacidad} buses");

        return redirect()->route('parqueos.show', $parqueo)->with('ok', 'Parqueo registrado.');
    }

    public function show(Parqueo $parqueo): View
    {
        $parqueo->loadCount('buses')->load(['buses' => fn ($q) => $q->with('linea')->orderBy('codigo')]);

        return view('parqueos.show', ['parqueo' => $parqueo]);
    }

    public function edit(Parqueo $parqueo): View
    {
        return view('parqueos.form', ['parqueo' => $parqueo->loadCount('buses')]);
    }

    public function update(Request $request, Parqueo $parqueo): RedirectResponse
    {
        try {
            $parqueo->update($this->validar($request, $parqueo));
        } catch (QueryException $e) {
            return $this->volverConErrorBd($e);
        }
        Bitacora::registrar('editar', $parqueo, "Actualización del parqueo {$parqueo->nombre}");

        return redirect()->route('parqueos.show', $parqueo)->with('ok', 'Parqueo actualizado.');
    }

    public function destroy(Parqueo $parqueo): RedirectResponse
    {
        $buses = $parqueo->buses()->count();
        if ($buses > 0) {
            return back()->with('error', "No se puede eliminar el parqueo {$parqueo->nombre}: tiene {$buses} ".($buses === 1 ? 'bus asignado' : 'buses asignados').'. Reubíquelos primero.');
        }

        try {
            $parqueo->delete();
        } catch (QueryException $e) {
            return back()->with('error', $this->mensajeErrorBd($e));
        }
        Bitacora::registrar('eliminar', $parqueo, "Eliminación del parqueo {$parqueo->nombre}");

        return redirect()->route('parqueos.index')->with('ok', 'Parqueo eliminado.');
    }

    private function validar(Request $request, ?Parqueo $parqueo = null): array
    {
        // La capacidad no puede quedar por debajo de los buses que ya están en el parqueo.
        $minimo = max(1, $parqueo?->buses()->count() ?? 0);

        return $request->validate([
            'nombre' => ['required', 'string', 'max:100', Rule::unique('parqueos', 'nombre')->ignore($parqueo)],
            'direccion' => ['nullable', 'string', 'max:200'],
            'capacidad' => ['required', 'integer', "min:{$minimo}", 'max:1000'],
        ], [
            'capacidad.min' => $minimo > 1
                ? "La capacidad no puede ser menor que los {$minimo} buses que ya tiene el parqueo."
                : 'La capacidad debe ser al menos 1.',
        ]);
    }
}
