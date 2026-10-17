<?php

namespace App\Http\Controllers;

use App\Models\Tarjeta;
use App\Services\PagoService;
use App\Services\PasarelaSimulada;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Recargas: en efectivo (ventanilla del operador) y en línea (pasarela SIMULADA).
 */
class RecargaController extends Controller
{
    public function create(Request $request, Tarjeta $tarjeta): View
    {
        return view('recargas.create', [
            'tarjeta' => $tarjeta->load('pasajero'),
            // Una llave nueva por intento de pago en línea: si el formulario se envía dos veces, no se cobra dos veces.
            'llave' => (string) Str::uuid(),
            'puedeEfectivo' => ! $request->user()->esAdministrador() && $request->user()->estacion_id !== null,
            'metodo' => $request->query('metodo') === 'tarjeta' ? 'tarjeta' : 'efectivo',
        ]);
    }

    public function efectivo(Request $request, Tarjeta $tarjeta, PagoService $pagos): RedirectResponse
    {
        $usuario = $request->user();
        abort_if($usuario->esAdministrador() || $usuario->estacion_id === null, 403, 'La recarga en efectivo la registra el operador en la ventanilla de su estación.');

        $transaccion = $pagos->recargarEfectivo($tarjeta, $this->monto($request), $usuario);

        return $this->resultado($transaccion);
    }

    public function enLinea(Request $request, Tarjeta $tarjeta, PagoService $pagos): RedirectResponse
    {
        $monto = $this->monto($request);
        $datos = $request->validate([
            'llave' => ['required', 'uuid'],
            'numero' => ['required', 'string', function ($campo, $valor, $falla) {
                if (! PasarelaSimulada::luhn((string) $valor)) {
                    $falla('El número de tarjeta no es válido.');
                }
            }],
            'vencimiento' => ['required', 'regex:/^(0[1-9]|1[0-2])\/\d{2}$/', function ($campo, $valor, $falla) {
                [$mes, $anio] = explode('/', (string) $valor);
                if (now()->startOfMonth()->gt(now()->setDate(2000 + (int) $anio, (int) $mes, 1)->startOfMonth())) {
                    $falla('La tarjeta está vencida.');
                }
            }],
            'cvv' => ['required', 'regex:/^\d{3,4}$/'],
            'nombre' => ['required', 'string', 'max:80'],
        ], [
            'vencimiento.regex' => 'Use el formato MM/AA.',
            'cvv.regex' => 'El CVV tiene 3 o 4 dígitos.',
        ], ['numero' => 'número de tarjeta', 'cvv' => 'CVV', 'nombre' => 'nombre en la tarjeta']);

        [$transaccion, $repetida] = $pagos->recargarEnLinea($tarjeta, $monto, $datos['numero'], $datos['llave'], $request->user());

        if ($repetida) {
            return redirect()->route('transacciones.show', $transaccion)
                ->with('info', 'Este pago ya se había procesado: no se cobró otra vez.');
        }

        return $this->resultado($transaccion);
    }

    private function monto(Request $request): float
    {
        $minimo = config('transmetro.recarga.minimo');
        $maximo = config('transmetro.recarga.maximo');

        return (float) $request->validate(
            ['monto' => ['required', 'numeric', "min:{$minimo}", "max:{$maximo}", 'decimal:0,2']],
            ['monto.min' => "El monto mínimo es Q{$minimo}.", 'monto.max' => "El monto máximo es Q{$maximo}."],
        )['monto'];
    }

    private function resultado($transaccion): RedirectResponse
    {
        $respuesta = redirect()->route('transacciones.show', $transaccion);

        return $transaccion->aprobada()
            ? $respuesta->with('ok', "Recarga aprobada. Saldo nuevo: Q{$transaccion->saldo_resultante}.")
            : $respuesta->with('error', 'Recarga rechazada: '.rtrim($transaccion->motivo_rechazo, '.').'.');
    }
}
