<?php

namespace App\Http\Controllers;

use App\Exceptions\ReglaNegocioException;
use App\Models\Alerta;
use App\Models\Bitacora;
use App\Models\Estacion;
use App\Models\Linea;
use App\Models\User;
use App\Services\AlertaService;
use App\Services\ResolucionAlertaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Alertas del Transmetro. El operador solo ve y atiende las de su estación.
 */
class AlertaController extends Controller
{
    public function index(Request $request): View
    {
        $usuario = $request->user();
        $filtros = $request->only('tipo', 'estado', 'linea_id', 'estacion_id');

        return view('alertas.index', [
            'filtros' => $filtros,
            'lineas' => Linea::orderBy('id')->get(),
            'estaciones' => $usuario->esAdministrador() ? Estacion::orderBy('nombre')->get() : collect(),
            'alertas' => Alerta::with(['estacion', 'bus', 'linea'])
                ->visiblesPara($usuario)
                ->when(array_key_exists($filtros['tipo'] ?? '', Alerta::TIPOS), fn ($q) => $q->where('tipo', $filtros['tipo']))
                ->when(in_array($filtros['estado'] ?? '', ['activa', 'atendida'], true), fn ($q) => $q->where('estado', $filtros['estado']))
                ->when(ctype_digit((string) ($filtros['linea_id'] ?? '')), fn ($q) => $q->where('linea_id', $filtros['linea_id']))
                ->when(ctype_digit((string) ($filtros['estacion_id'] ?? '')), fn ($q) => $q->where('estacion_id', $filtros['estacion_id']))
                ->orderByRaw("estado = 'activa' desc")
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function show(Request $request, Alerta $alerta, ResolucionAlertaService $resolucion): View
    {
        abort_unless($alerta->puedeVerla($request->user()), 403, 'Solo puede ver las alertas de su estación.');

        return view('alertas.show', [
            'alerta' => $alerta->load(['estacion', 'bus', 'linea']),
            'accion' => $resolucion->accion($alerta, $request->user()),
            'resolviendo' => $request->boolean('resolver'),
            // Quién la atendió, cuándo y qué se hizo queda en la bitácora.
            'historial' => Bitacora::with('user')->where('tabla', 'alertas')->where('registro_id', $alerta->id)->orderBy('id')->get(),
        ]);
    }

    public function atender(Request $request, Alerta $alerta, AlertaService $servicio): RedirectResponse
    {
        abort_unless($alerta->puedeVerla($request->user()), 403, 'Solo puede atender las alertas de su estación.');
        $datos = $request->validate(['nota' => ['nullable', 'string', 'max:200']], [], ['nota' => 'qué se hizo']);

        if (! $alerta->activa()) {
            return back()->with('info', 'La alerta ya estaba atendida.');
        }
        $servicio->atender($alerta, $datos['nota'] ?? null);

        return back()->with('ok', 'Alerta atendida.');
    }

    /**
     * El operador no inicia recorridos: ante una alta demanda de su estación pide una unidad al administrador.
     */
    public function solicitarUnidad(Request $request, Alerta $alerta, AlertaService $servicio): RedirectResponse
    {
        abort_unless($alerta->puedeVerla($request->user()), 403, 'Solo puede resolver las alertas de su estación.');
        if (! $alerta->activa() || $alerta->tipo !== 'alta_demanda') {
            throw new ReglaNegocioException('Solo se solicita unidad para una alerta de alta demanda activa.');
        }

        $servicio->solicitarUnidad($alerta, $request->user());

        return redirect()->route('alertas.show', $alerta)->with('ok', 'Solicitud enviada: el administrador recibirá el aviso para enviar otro bus.');
    }

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
            'nuevas' => $nuevas->map(fn ($a) => $this->resumen($a, $usuario))->values(),
            'recientes' => $recientes->map(fn ($a) => $this->resumen($a, $usuario))->values(),
        ]);
    }

    private function resumen(Alerta $alerta, User $usuario): array
    {
        return [
            'resolver' => app(ResolucionAlertaService::class)->accion($alerta, $usuario),
            'id' => $alerta->id,
            'tipo' => $alerta->tipo,
            'tipo_texto' => $alerta->tipoTexto(),
            'mensaje' => $alerta->mensaje,
            'lugar' => $alerta->lugar(),
            'fecha' => $alerta->created_at?->format('d/m/Y H:i'),
            'url' => route('alertas.show', $alerta),
        ];
    }
}
