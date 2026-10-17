<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Estacion;
use App\Models\Municipalidad;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EstacionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('rol:administrador', except: ['index', 'show'])];
    }

    public function index(Request $request): View
    {
        $buscar = trim((string) $request->query('buscar'));

        return view('estaciones.index', [
            'buscar' => $buscar,
            'estaciones' => Estacion::with(['municipalidad', 'lineas'])
                ->withCount('accesos')
                ->when($buscar !== '', fn ($q) => $q->where('nombre', 'like', "%{$buscar}%"))
                ->orderBy('id')
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('estaciones.form', ['estacion' => new Estacion(['estado' => 'activa']), 'municipalidades' => Municipalidad::orderBy('nombre')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $estacion = Estacion::create($this->validar($request));
        } catch (QueryException $e) {
            return $this->volverConErrorBd($e);
        }
        Bitacora::registrar('crear', $estacion, "Registro de la estación {$estacion->nombre}");

        return redirect()->route('estaciones.show', $estacion)->with('ok', 'Estación creada.');
    }

    public function show(Estacion $estacion): View
    {
        $estacion->load([
            'municipalidad',
            'lineas',
            'accesos' => fn ($q) => $q->orderBy('nombre')->with(['guardias' => fn ($g) => $g->orderBy('nombre')]),
            'operadores',
        ]);

        return view('estaciones.show', ['estacion' => $estacion]);
    }

    public function edit(Estacion $estacion): View
    {
        return view('estaciones.form', ['estacion' => $estacion, 'municipalidades' => Municipalidad::orderBy('nombre')->get()]);
    }

    public function update(Request $request, Estacion $estacion): RedirectResponse
    {
        try {
            $estacion->update($this->validar($request, $estacion));
        } catch (QueryException $e) {
            return $this->volverConErrorBd($e);
        }
        Bitacora::registrar('editar', $estacion, "Actualización de la estación {$estacion->nombre}");

        return redirect()->route('estaciones.show', $estacion)->with('ok', 'Estación actualizada.');
    }

    public function destroy(Estacion $estacion): RedirectResponse
    {
        try {
            $estacion->delete();
        } catch (QueryException $e) {
            return back()->with('error', $this->mensajeErrorBd($e));
        }
        Bitacora::registrar('eliminar', $estacion, "Eliminación de la estación {$estacion->nombre}");

        return redirect()->route('estaciones.index')->with('ok', 'Estación eliminada.');
    }

    private function validar(Request $request, ?Estacion $estacion = null): array
    {
        return $request->validate([
            'municipalidad_id' => ['required', 'exists:municipalidades,id'],
            'nombre' => ['required', 'string', 'max:100', Rule::unique('estaciones', 'nombre')->ignore($estacion)],
            'direccion' => ['nullable', 'string', 'max:200'],
            'estado' => ['required', Rule::in(['activa', 'inactiva'])],
        ]);
    }
}
