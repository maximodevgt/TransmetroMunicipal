<?php

namespace App\Http\Controllers;

use App\Models\Alerta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertaController extends Controller
{
    /**
     * Consulta ligera para la campana y los avisos en vivo (cada 15 segundos desde el navegador).
     * Sin "desde" solo devuelve el estado actual; con "desde" agrega las alertas activas posteriores a ese ID.
     */
    public function nuevas(Request $request): JsonResponse
    {
        $datos = $request->validate(['desde' => ['nullable', 'integer', 'min:0']]);
        $usuario = $request->user();
        $desde = isset($datos['desde']) ? (int) $datos['desde'] : null;

        $nuevas = $desde === null ? collect() : Alerta::with(['estacion', 'bus', 'linea'])
            ->activasPara($usuario)
            ->where('id', '>', $desde)
            ->orderBy('id')
            ->limit(10)
            ->get();

        $recientes = Alerta::with(['estacion', 'bus', 'linea'])
            ->activasPara($usuario)
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return response()->json([
            'activas' => Alerta::activasPara($usuario)->count(),
            'ultimo_id' => max($desde ?? 0, (int) Alerta::activasPara($usuario)->max('id')),
            'nuevas' => $nuevas->map($this->resumen(...))->values(),
            'recientes' => $recientes->map($this->resumen(...))->values(),
        ]);
    }

    private function resumen(Alerta $alerta): array
    {
        return [
            'id' => $alerta->id,
            'tipo' => $alerta->tipo,
            'tipo_texto' => $alerta->tipoTexto(),
            'mensaje' => $alerta->mensaje,
            'lugar' => $alerta->lugar(),
            'fecha' => $alerta->created_at?->format('d/m/Y H:i'),
            // El detalle de cada alerta llega en la parte 2; por ahora lleva al panel, donde se listan las activas.
            // El parámetro obliga a recargar el panel aunque ya esté abierto (un ancla sola no recarga la página).
            'url' => route('dashboard', ['alerta' => $alerta->id]).'#alerta-'.$alerta->id,
        ];
    }
}
