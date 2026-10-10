<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Estacion;
use App\Models\Linea;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Administra el recorrido de una linea: que estaciones tiene, en que orden y a que distancia.
 */
class LineaEstacionController extends Controller
{
    public function store(Request $request, Linea $linea): RedirectResponse
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

        $orden = (int) ($datos['orden'] ?? $total + 1);

        DB::transaction(function () use ($linea, $datos, $orden) {
            // Recorre hacia atras para no chocar con la llave unica (linea_id, orden).
            DB::table('linea_estacion')
                ->where('linea_id', $linea->id)
                ->where('orden', '>=', $orden)
                ->orderByDesc('orden')
                ->increment('orden');

            $linea->estaciones()->attach($datos['estacion_id'], [
                'orden' => $orden,
                'distancia_anterior_km' => $datos['distancia_anterior_km'],
            ]);
        });

        $estacion = Estacion::find($datos['estacion_id']);
        Bitacora::registrar('agregar_estacion', $linea, "Estacion {$estacion->nombre} agregada a {$linea->codigo} en posicion {$orden}");

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

    public function destroy(Linea $linea, Estacion $estacion): RedirectResponse
    {
        $orden = $linea->estaciones()->whereKey($estacion->id)->first()?->pivot->orden;
        abort_if($orden === null, 404);

        DB::transaction(function () use ($linea, $estacion, $orden) {
            $linea->estaciones()->detach($estacion->id);

            // Cierra el hueco en la numeracion, de menor a mayor por la llave unica.
            DB::table('linea_estacion')
                ->where('linea_id', $linea->id)
                ->where('orden', '>', $orden)
                ->orderBy('orden')
                ->decrement('orden');

            // La primera estacion del recorrido no tiene distancia anterior.
            DB::table('linea_estacion')->where('linea_id', $linea->id)->where('orden', 1)->update(['distancia_anterior_km' => 0]);
        });

        Bitacora::registrar('quitar_estacion', $linea, "Estacion {$estacion->nombre} retirada de {$linea->codigo}");

        return back()->with('ok', "Estación {$estacion->nombre} retirada de la línea.");
    }
}
