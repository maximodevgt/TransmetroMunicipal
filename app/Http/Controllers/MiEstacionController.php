<?php

namespace App\Http\Controllers;

use App\Models\Alerta;
use App\Models\Recorrido;
use App\Services\AlertaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Pantalla del operador: pasajeros en espera y llegada de los buses que pasan por su estación.
 */
class MiEstacionController extends Controller
{
    public function show(Request $request, AlertaService $alertas): View
    {
        $estacion = $request->user()->estacion;
        abort_if($estacion === null, 403, 'Su usuario no tiene una estación asignada.');

        $lineas = DB::table('linea_estacion')->where('estacion_id', $estacion->id)->pluck('linea_id');
        $recorridos = Recorrido::with(['linea', 'bus', 'piloto', 'visitas.estacion'])
            ->where('estado', 'en_curso')
            ->whereIn('linea_id', $lineas)
            ->orderBy('inicio')
            ->get()
            ->map(function (Recorrido $recorrido) use ($estacion) {
                $recorrido->setAttribute('siguiente', $recorrido->siguienteEstacion());
                $recorrido->setAttribute('yaPaso', $recorrido->visitas->contains('estacion_id', $estacion->id));

                return $recorrido;
            });

        return view('mi-estacion.show', [
            'estacion' => $estacion->load('lineas'),
            'recorridos' => $recorridos,
            'referencia' => $alertas->capacidadReferencia($estacion),
            'registros' => $estacion->registrosPasajeros()->with('user')->latest('registrado_en')->limit(5)->get(),
            'alertas' => Alerta::activasPara($request->user())->latest()->limit(5)->get(),
        ]);
    }

    public function registrarEspera(Request $request, AlertaService $alertas): RedirectResponse
    {
        $usuario = $request->user();
        abort_if($usuario->estacion === null, 403, 'Su usuario no tiene una estación asignada.');

        $datos = $request->validate(['cantidad' => ['required', 'integer', 'min:0', 'max:5000']]);

        // La estación es siempre la del operador, nunca la que venga en la petición.
        [, $alerta] = DB::transaction(fn () => $alertas->registrarEspera($usuario->estacion, $usuario, (int) $datos['cantidad']));

        $respuesta = back()->with('ok', "{$datos['cantidad']} pasajeros en espera registrados.");

        return $alerta ? $respuesta->with('advertencia', $alerta->mensaje) : $respuesta;
    }
}
