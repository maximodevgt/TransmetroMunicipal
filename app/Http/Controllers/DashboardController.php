<?php

namespace App\Http\Controllers;

use App\Models\Alerta;
use App\Models\Bitacora;
use App\Models\Bus;
use App\Models\Estacion;
use App\Models\Linea;
use App\Models\Piloto;
use App\Models\Transaccion;
use App\Services\ResolucionAlertaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $usuario = $request->user();

        $alertas = Alerta::with(['estacion', 'linea', 'bus'])
            ->activasPara($usuario)
            ->latest()
            ->get();

        $bitacora = $usuario->esAdministrador()
            ? Bitacora::with('user')->orderByDesc('id')->limit(6)->get()
            : collect();

        return view('dashboard', [
            'totales' => [
                'lineas' => Linea::count(),
                'estaciones' => Estacion::count(),
                'buses' => Bus::count(),
                'pilotos' => Piloto::where('estado', 'activo')->count(),
            ],
            'busesPorEstado' => Bus::select('estado', DB::raw('count(*) as total'))->groupBy('estado')->pluck('total', 'estado'),
            'resumenLineas' => DB::table('vw_resumen_lineas')->orderBy('id')->get(),
            'alertas' => $alertas,
            'resolver' => $alertas->mapWithKeys(fn (Alerta $a) => [$a->id => app(ResolucionAlertaService::class)->accion($a, $usuario)]),
            'estacion' => $usuario->estacion?->load('lineas', 'accesos.guardias'),
            'bitacora' => $bitacora,
            'enlacesBitacora' => Bitacora::enlaces($bitacora),
            'caja' => $this->cajaDeHoy($usuario),
        ]);
    }

    /**
     * Recaudación y rechazos de hoy (el operador ve los de su estación). Una sola consulta.
     */
    private function cajaDeHoy($usuario): array
    {
        $fila = Transaccion::visiblesPara($usuario)->where('created_at', '>=', today())->selectRaw(
            "COALESCE(SUM(CASE WHEN tipo = 'recarga' AND estado = 'aprobada' THEN monto END), 0) AS recargas,
             COALESCE(SUM(CASE WHEN tipo = 'pago' AND estado = 'aprobada' THEN monto END), 0) AS pasajes,
             SUM(tipo = 'pago' AND estado = 'aprobada') AS cantidad_pasajes,
             SUM(estado = 'rechazada') AS rechazados"
        )->first();

        return [
            'recargas' => (float) $fila->recargas,
            'pasajes' => (float) $fila->pasajes,
            'cantidad_pasajes' => (int) $fila->cantidad_pasajes,
            'rechazados' => (int) $fila->rechazados,
        ];
    }
}
