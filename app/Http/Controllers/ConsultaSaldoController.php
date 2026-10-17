<?php

namespace App\Http\Controllers;

use App\Models\Tarjeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Consulta pública de saldo (sin iniciar sesión). Pide el código y los últimos 4 dígitos del DPI
 * del titular, y no muestra datos personales. El límite de intentos está en la ruta.
 */
class ConsultaSaldoController extends Controller
{
    public function show(): View
    {
        return view('consulta-saldo', ['tarjeta' => null, 'movimientos' => collect()]);
    }

    public function consultar(Request $request): View|RedirectResponse
    {
        $datos = $request->validate([
            'codigo' => ['required', 'string', 'max:20'],
            'dpi4' => ['required', 'digits:4'],
        ], ['dpi4.digits' => 'Escriba los últimos 4 dígitos del DPI.'], ['codigo' => 'código de la tarjeta', 'dpi4' => 'últimos 4 dígitos del DPI']);

        $tarjeta = Tarjeta::with('pasajero:id,dpi')->where('codigo', mb_strtoupper(trim($datos['codigo'])))->first();

        // Mismo mensaje si el código no existe o el DPI no coincide: no revela qué tarjetas existen.
        if ($tarjeta === null || ! hash_equals(substr($tarjeta->pasajero->dpi, -4), $datos['dpi4'])) {
            return redirect()->route('consulta-saldo')
                ->withErrors(['codigo' => 'Los datos no coinciden con ninguna tarjeta.'])
                ->withInput($request->only('codigo'));
        }

        return view('consulta-saldo', [
            'tarjeta' => $tarjeta,
            'movimientos' => $tarjeta->transacciones()->with('estacion:id,nombre')->orderByDesc('id')->limit(5)
                ->get(['id', 'tipo', 'metodo', 'monto', 'saldo_resultante', 'estado', 'motivo_rechazo', 'estacion_id', 'created_at']),
        ]);
    }
}
