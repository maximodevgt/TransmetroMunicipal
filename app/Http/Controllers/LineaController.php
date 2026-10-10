<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Estacion;
use App\Models\Linea;
use App\Models\Municipalidad;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LineaController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('rol:administrador', except: ['index', 'show'])];
    }

    public function index(): View
    {
        return view('lineas.index', [
            'lineas' => Linea::with('municipalidad')->withCount(['estaciones', 'buses'])->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('lineas.form', ['linea' => new Linea(['estado' => 'activa']), 'municipalidades' => Municipalidad::orderBy('nombre')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $linea = Linea::create($this->validar($request));
        } catch (QueryException $e) {
            return $this->volverConErrorBd($e);
        }
        Bitacora::registrar('crear', $linea, "Registro de la linea {$linea->codigo} - {$linea->nombre}");

        return redirect()->route('lineas.show', $linea)->with('ok', 'Línea creada. Ahora agregue sus estaciones.');
    }

    public function show(Linea $linea): View
    {
        $linea->load(['municipalidad', 'estaciones', 'buses.parqueo']);
        $totalEstaciones = $linea->estaciones->count();

        return view('lineas.show', [
            'linea' => $linea,
            'distanciaTotal' => $linea->estaciones->sum('pivot.distancia_anterior_km'),
            'minBuses' => $totalEstaciones,
            'maxBuses' => $totalEstaciones * 2,
            'estacionesDisponibles' => Estacion::whereNotIn('id', $linea->estaciones->pluck('id'))->orderBy('nombre')->get(),
        ]);
    }

    public function edit(Linea $linea): View
    {
        return view('lineas.form', ['linea' => $linea, 'municipalidades' => Municipalidad::orderBy('nombre')->get()]);
    }

    public function update(Request $request, Linea $linea): RedirectResponse
    {
        try {
            $linea->update($this->validar($request, $linea));
        } catch (QueryException $e) {
            return $this->volverConErrorBd($e);
        }
        Bitacora::registrar('editar', $linea, "Actualizacion de la linea {$linea->codigo}");

        return redirect()->route('lineas.show', $linea)->with('ok', 'Línea actualizada.');
    }

    public function destroy(Linea $linea): RedirectResponse
    {
        try {
            $linea->delete();
        } catch (QueryException $e) {
            return back()->with('error', $this->mensajeErrorBd($e));
        }
        Bitacora::registrar('eliminar', $linea, "Eliminacion de la linea {$linea->codigo}");

        return redirect()->route('lineas.index')->with('ok', 'Línea eliminada.');
    }

    private function validar(Request $request, ?Linea $linea = null): array
    {
        return $request->validate([
            'municipalidad_id' => ['required', 'exists:municipalidades,id'],
            'codigo' => ['required', 'string', 'max:10', Rule::unique('lineas', 'codigo')->ignore($linea)],
            'nombre' => ['required', 'string', 'max:100'],
            'estado' => ['required', Rule::in(['activa', 'inactiva'])],
        ]);
    }
}
