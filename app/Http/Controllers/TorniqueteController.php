<?php

namespace App\Http\Controllers;

use App\Models\Acceso;
use App\Models\Tarjeta;
use App\Models\User;
use App\Services\PagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Validador de torniquete (simular pasaje). El operador solo usa los accesos de su estación.
 */
class TorniqueteController extends Controller
{
    public function index(Request $request): View
    {
        return view('torniquete.index', [
            'accesos' => $this->accesosPermitidos($request->user())->with('estacion')->get()->groupBy(fn ($a) => $a->estacion->nombre),
            'codigo' => $request->query('codigo'),
        ]);
    }

    public function show(Request $request, Acceso $acceso): View
    {
        $this->autorizar($request->user(), $acceso);

        return view('torniquete.show', [
            'acceso' => $acceso->load('estacion'),
            'pasaje' => config('transmetro.pasaje'),
            'codigo' => $request->query('codigo'),
            // Algunas tarjetas para "pasar" con un clic durante la demostración.
            'ejemplos' => Tarjeta::orderBy('codigo')->limit(8)->get(['codigo', 'estado']),
        ]);
    }

    public function validar(Request $request, Acceso $acceso, PagoService $pagos): JsonResponse
    {
        $this->autorizar($request->user(), $acceso);
        $codigo = $request->validate(['codigo' => ['required', 'string', 'max:20']], [], ['codigo' => 'código de la tarjeta'])['codigo'];

        $transaccion = $pagos->cobrarPasaje($codigo, $acceso, $request->user());

        if ($transaccion === null) {
            return response()->json(['aprobado' => false, 'titulo' => 'Tarjeta inexistente', 'mensaje' => 'El código '.mb_strtoupper(trim($codigo)).' no corresponde a ninguna tarjeta.']);
        }

        return response()->json($transaccion->aprobada()
            ? ['aprobado' => true, 'titulo' => 'Bienvenido', 'mensaje' => "Saldo: Q{$transaccion->saldo_resultante}", 'codigo' => $transaccion->tarjeta->codigo]
            : ['aprobado' => false, 'titulo' => $this->tituloRechazo($transaccion->motivo_rechazo), 'mensaje' => $transaccion->motivo_rechazo.' Saldo: Q'.$transaccion->saldo_resultante, 'codigo' => $transaccion->tarjeta->codigo]);
    }

    private function tituloRechazo(?string $motivo): string
    {
        return match (true) {
            str_contains((string) $motivo, 'Saldo insuficiente') => 'Saldo insuficiente',
            str_contains((string) $motivo, 'no se encuentra activa') => 'Tarjeta bloqueada',
            default => 'Pago rechazado',
        };
    }

    private function accesosPermitidos(User $usuario)
    {
        return Acceso::query()
            ->when(! $usuario->esAdministrador(), fn ($q) => $q->where('estacion_id', $usuario->estacion_id))
            ->orderBy('estacion_id')->orderBy('nombre');
    }

    private function autorizar(User $usuario, Acceso $acceso): void
    {
        abort_if(! $usuario->esAdministrador() && $acceso->estacion_id !== $usuario->estacion_id, 403, 'Solo puede usar los accesos de su estación.');
    }
}
