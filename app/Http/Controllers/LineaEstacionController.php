<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Estacion;
use App\Models\Linea;
use App\Services\AsignacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Administra el recorrido de una línea: qué estaciones tiene, en qué orden y a qué distancia.
 */
class LineaEstacionController extends Controller
{
    public function store(Request $request, Linea $linea, AsignacionService $asignacion): RedirectResponse
    {
        $total = $linea->estaciones()->count();

        $datos = $request->validate([
            'estacion_id' => [
                'required',
                'exists:estaciones,id',
                Rule::unique('linea_estacion')->where('linea_id', $linea->id),
            ],
            'orden' => ['nullable', 'integer', 'min:1', 'max:'.($total + 1)],
            'distancia_anterior_km' => ['required', 'numeric', 'min:0', 'max:9999.99'],
        ], [
            'estacion_id.unique' => 'La estación ya pertenece a esta línea.',
        ]);

        $estacion = Estacion::findOrFail($datos['estacion_id']);
        $orden = $asignacion->agregarEstacion($linea, $estacion, (float) $datos['distancia_anterior_km'], isset($datos['orden']) ? (int) $datos['orden'] : null);

        Bitacora::registrar('agregar_estacion', $linea, "Estación {$estacion->nombre} agregada a {$linea->codigo} en la posición {$orden}");

        return back()->with('ok', "Estación {$estacion->nombre} agregada en la posición {$orden}.");
    }

    public function update(Request $request, Linea $linea, Estacion $estacion): RedirectResponse
    {
        $datos = $request->validate([
            'distancia_anterior_km' => ['required', 'numeric', 'min:0', 'max:9999.99'],
        ]);

        $linea->estaciones()->updateExistingPivot($estacion->id, $datos);
        Bitacora::registrar('editar_distancia', $linea, "Distancia a {$estacion->nombre} en {$linea->codigo}: {$datos['distancia_anterior_km']} km");

        return back()->with('ok', 'Distancia actualizada.');
    }

    /**
     * RF-03: subir o bajar la estación en el recorrido de la línea.
     */
    public function mover(Request $request, Linea $linea, Estacion $estacion, AsignacionService $asignacion): RedirectResponse
    {
        abort_unless($linea->estaciones()->whereKey($estacion->id)->exists(), 404);
        $total = $linea->estaciones()->count();
        $datos = $request->validate(['posicion' => ['required', 'integer', 'min:1', 'max:'.$total]], [], ['posicion' => 'posición']);

        if ($motivo = $asignacion->moverEstacion($linea, $estacion, (int) $datos['posicion'])) {
            return back()->with('error', $motivo);
        }
        Bitacora::registrar('mover_estacion', $linea, "Estación {$estacion->nombre} movida a la posición {$datos['posicion']} de {$linea->codigo}");

        return back()->with('ok', "{$estacion->nombre} quedó en la posición {$datos['posicion']}.");
    }

    public function destroy(Linea $linea, Estacion $estacion, AsignacionService $asignacion): RedirectResponse
    {
        abort_unless($linea->estaciones()->whereKey($estacion->id)->exists(), 404);

        $motivo = $asignacion->retirarEstacion($linea, $estacion);

        if ($motivo !== null) {
            return back()->with('error', $motivo);
        }
        Bitacora::registrar('quitar_estacion', $linea, "Estación {$estacion->nombre} retirada de {$linea->codigo}");

        // Con una estación menos el mínimo baja: si la línea ya queda cubierta, su alerta de insuficiencia se atiende.
        $asignacion->revisarMinimoRecuperado($linea);

        return back()->with('ok', "Estación {$estacion->nombre} retirada de la línea.");
    }
}
