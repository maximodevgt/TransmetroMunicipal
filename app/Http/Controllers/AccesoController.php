<?php

namespace App\Http\Controllers;

use App\Models\Acceso;
use App\Models\Bitacora;
use App\Models\Estacion;
use App\Models\Guardia;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Accesos de cada estación. Un acceso nace con su primer guardia (RN-11).
 */
class AccesoController extends Controller
{
    private const MENSAJES = ['nombre.unique' => 'La estación ya tiene un acceso con ese nombre.'];

    private const CAMPOS = ['nombre' => 'nombre del acceso'];

    public function create(Estacion $estacion): View
    {
        return view('accesos.form', ['estacion' => $estacion, 'acceso' => new Acceso, 'guardia' => new Guardia(['turno' => 'matutino'])]);
    }

    public function store(Request $request, Estacion $estacion): RedirectResponse
    {
        // Acceso y primer guardia se validan juntos para mostrar todos los errores a la vez.
        [$reglas, $mensajes, $campos] = GuardiaController::reglas(prefijo: 'guardia_');
        $datos = $request->validate(
            [...$this->reglasNombre($estacion), ...$reglas],
            [...self::MENSAJES, ...$mensajes],
            [...self::CAMPOS, ...$campos],
        );

        try {
            [$acceso, $guardia] = DB::transaction(function () use ($estacion, $datos) {
                $acceso = $estacion->accesos()->create(['nombre' => $datos['nombre']]);
                $guardia = $acceso->guardias()->create([
                    'nombre' => $datos['guardia_nombre'],
                    'dpi' => $datos['guardia_dpi'],
                    'telefono' => $datos['guardia_telefono'] ?? null,
                    'turno' => $datos['guardia_turno'],
                ]);

                return [$acceso, $guardia];
            });
        } catch (QueryException $e) {
            return $this->volverConErrorBd($e);
        }
        Bitacora::registrar('crear', $acceso, "Acceso creado en la estación {$estacion->nombre}: {$acceso->nombre}, con el guardia {$guardia->nombre}");

        return redirect()->route('estaciones.show', $estacion)->with('ok', "Acceso creado: {$acceso->nombre}, con el guardia {$guardia->nombre}.");
    }

    public function edit(Acceso $acceso): View
    {
        return view('accesos.form', ['estacion' => $acceso->estacion, 'acceso' => $acceso, 'guardia' => null]);
    }

    public function update(Request $request, Acceso $acceso): RedirectResponse
    {
        $anterior = $acceso->nombre;

        try {
            $acceso->update($request->validate($this->reglasNombre($acceso->estacion, $acceso), self::MENSAJES, self::CAMPOS));
        } catch (QueryException $e) {
            return $this->volverConErrorBd($e);
        }
        Bitacora::registrar('editar', $acceso, "Acceso renombrado en la estación {$acceso->estacion->nombre}: {$anterior} → {$acceso->nombre}");

        return redirect()->route('estaciones.show', $acceso->estacion_id)->with('ok', 'Acceso actualizado.');
    }

    public function destroy(Acceso $acceso): RedirectResponse
    {
        $guardias = $acceso->guardias()->count();

        try {
            // Los guardias pertenecen al acceso: se retiran junto con él.
            DB::transaction(function () use ($acceso) {
                $acceso->guardias()->delete();
                $acceso->delete();
            });
        } catch (QueryException $e) {
            return back()->with('error', $this->mensajeErrorBd($e));
        }
        Bitacora::registrar('eliminar', $acceso, "Acceso eliminado de la estación {$acceso->estacion->nombre}: {$acceso->nombre}, con {$guardias} ".($guardias === 1 ? 'guardia' : 'guardias'));

        return redirect()->route('estaciones.show', $acceso->estacion_id)->with('ok', "Acceso eliminado: {$acceso->nombre}.");
    }

    private function reglasNombre(Estacion $estacion, ?Acceso $acceso = null): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100', Rule::unique('accesos', 'nombre')->where('estacion_id', $estacion->id)->ignore($acceso)],
        ];
    }
}
