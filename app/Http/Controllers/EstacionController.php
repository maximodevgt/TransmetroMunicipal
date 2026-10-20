<?php

namespace App\Http\Controllers;

use App\Exceptions\ReglaNegocioException;
use App\Models\Bitacora;
use App\Models\Estacion;
use App\Models\Linea;
use App\Models\Municipalidad;
use App\Services\AsignacionService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
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
        return view('estaciones.form', [
            'estacion' => new Estacion(['estado' => 'activa']),
            'municipalidades' => Municipalidad::orderBy('nombre')->get(),
            'lineas' => Linea::orderBy('id')->get(),
        ]);
    }

    /**
     * RN-02: la estación nace dentro de una línea (al final de su recorrido); después se puede mover o agregar a otras.
     */
    public function store(Request $request, AsignacionService $asignacion): RedirectResponse
    {
        $datos = $this->validar($request);
        $enLinea = $request->validate([
            'linea_id' => ['required', 'exists:lineas,id'],
            'distancia_anterior_km' => ['required', 'numeric', 'min:0', 'max:9999.99'],
        ], [
            'linea_id.required' => 'Elija la línea a la que pertenece la estación (cada estación debe estar al menos en una).',
        ], ['linea_id' => 'línea', 'distancia_anterior_km' => 'distancia desde la estación anterior']);
        $linea = Linea::findOrFail($enLinea['linea_id']);

        try {
            [$estacion, $orden] = DB::transaction(function () use ($datos, $linea, $enLinea, $asignacion) {
                $estacion = Estacion::create($datos);

                return [$estacion, $asignacion->agregarEstacion($linea, $estacion, (float) $enLinea['distancia_anterior_km'])];
            });
        } catch (QueryException $e) {
            return $this->volverConErrorBd($e);
        }
        Bitacora::registrar('crear', $estacion, "Registro de la estación {$estacion->nombre}");
        Bitacora::registrar('agregar_estacion', $linea, "Estación {$estacion->nombre} agregada a {$linea->codigo} en la posición {$orden}");

        return redirect()->route('estaciones.show', $estacion)->with('ok', 'Estación creada.');
    }

    public function show(Request $request, Estacion $estacion): View
    {
        abort_unless($estacion->puedeVerla($request->user()), 403, 'Solo puede ver la ficha de su estación.');

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

    /**
     * Al eliminarla se retira de sus líneas (con la revisión del máximo de buses de cada una) y se borra;
     * si algo lo impide (máximo de buses, accesos con guardias), no cambia nada.
     */
    public function destroy(Estacion $estacion, AsignacionService $asignacion): RedirectResponse
    {
        $lineas = $estacion->lineas()->get();
        try {
            DB::transaction(function () use ($estacion, $lineas, $asignacion) {
                foreach ($lineas as $linea) {
                    if ($motivo = $asignacion->retirarEstacion($linea, $estacion, eliminandoEstacion: true)) {
                        throw new ReglaNegocioException($motivo);
                    }
                }
                $estacion->delete();
            });
        } catch (QueryException $e) {
            return back()->with('error', $this->mensajeErrorBd($e));
        }
        $lineas->each(fn (Linea $linea) => $asignacion->revisarMinimoRecuperado($linea));
        Bitacora::registrar('eliminar', $estacion, "Eliminación de la estación {$estacion->nombre}"
            .($lineas->isEmpty() ? '' : ' (retirada de '.$lineas->pluck('codigo')->join(', ').')'));

        return redirect()->route('estaciones.index')->with('ok', 'Estación eliminada.');
    }

    private function validar(Request $request, ?Estacion $estacion = null): array
    {
        return $request->validate([
            'municipalidad_id' => ['required', 'exists:municipalidades,id'],
            'nombre' => ['required', 'string', 'max:100', Rule::unique('estaciones', 'nombre')->ignore($estacion)],
            'direccion' => ['nullable', 'string', 'max:200'],
            // Coordenadas para el mapa de seguimiento: van juntas o ninguna.
            'latitud' => ['nullable', 'required_with:longitud', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'required_with:latitud', 'numeric', 'between:-180,180'],
            'estado' => ['required', Rule::in(['activa', 'inactiva'])],
        ]);
    }
}
