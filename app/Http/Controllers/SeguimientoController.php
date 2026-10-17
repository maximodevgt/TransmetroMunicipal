<?php

namespace App\Http\Controllers;

use App\Services\SeguimientoService;
use App\Services\SimuladorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Seguimiento en vivo: mapa con líneas, estaciones y buses en ruta (consultado cada 3 segundos).
 */
class SeguimientoController extends Controller
{
    public function index(): View
    {
        return view('seguimiento.index');
    }

    public function datos(Request $request, SeguimientoService $seguimiento): JsonResponse
    {
        return response()->json($seguimiento->datos($request->user()));
    }

    /**
     * Avanza un paso la simulación si está activa. Lo pide cada página con el mapa abierto;
     * el servicio ignora los pedidos que llegan antes de tiempo.
     */
    public function avanzar(SimuladorService $simulador): JsonResponse
    {
        $estado = $simulador->avanzar();

        return response()->json(['activa' => $estado['activo'], 'buses' => count($estado['propios'])]);
    }

    /**
     * Iniciar o detener la simulación (solo administrador).
     */
    public function simulacion(Request $request, SimuladorService $simulador): JsonResponse
    {
        $datos = $request->validate([
            'accion' => ['required', 'in:iniciar,detener'],
            'buses' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        if ($datos['accion'] === 'iniciar') {
            $estado = $simulador->iniciar((int) ($datos['buses'] ?? 6));

            return response()->json(['activa' => true, 'mensaje' => 'Simulación iniciada con '.count($estado['propios']).' buses en ruta.']);
        }

        $finalizados = $simulador->detener();

        return response()->json(['activa' => false, 'mensaje' => "Simulación detenida: {$finalizados} recorridos finalizados y sus buses quedaron disponibles."]);
    }
}
