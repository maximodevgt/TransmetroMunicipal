<?php

namespace App\Http\Controllers;

use App\Models\Transaccion;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Movimientos de las tarjetas (recargas y pasajes) y su comprobante imprimible.
 * El operador ve los de su estación.
 */
class TransaccionController extends Controller
{
    public function index(Request $request): View
    {
        $usuario = $request->user();
        $filtros = $request->only('tipo', 'estado', 'metodo', 'periodo');
        $periodo = $filtros['periodo'] ?? 'todas';

        return view('transacciones.index', [
            'filtros' => [...$filtros, 'periodo' => $periodo],
            'transacciones' => Transaccion::with(['tarjeta', 'acceso', 'estacion', 'user'])
                ->visiblesPara($usuario)
                ->when(in_array($filtros['tipo'] ?? '', ['recarga', 'pago'], true), fn ($q) => $q->where('tipo', $filtros['tipo']))
                ->when(in_array($filtros['estado'] ?? '', ['aprobada', 'rechazada'], true), fn ($q) => $q->where('estado', $filtros['estado']))
                ->when(in_array($filtros['metodo'] ?? '', ['efectivo', 'tarjeta'], true), fn ($q) => $q->where('metodo', $filtros['metodo']))
                ->when($periodo === 'hoy', fn ($q) => $q->where('created_at', '>=', today()))
                ->when($periodo === '7dias', fn ($q) => $q->where('created_at', '>=', today()->subDays(6)))
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    /**
     * Comprobante imprimible de una recarga (o constancia del rechazo).
     */
    public function show(Request $request, Transaccion $transaccion): View
    {
        abort_unless($transaccion->puedeVerla($request->user()), 403, 'Solo puede ver los movimientos de su estación.');

        return view('transacciones.show', ['transaccion' => $transaccion->load(['tarjeta.pasajero', 'acceso.estacion', 'estacion', 'user'])]);
    }
}
