<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Bitácora completa con filtros (solo administrador).
 */
class BitacoraController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->only('user_id', 'tabla', 'accion', 'fecha');
        $fecha = rescue(fn () => filled($filtros['fecha'] ?? null) ? Carbon::createFromFormat('Y-m-d', $filtros['fecha']) : null, null, false);

        $registros = Bitacora::with('user')
            ->when(ctype_digit((string) ($filtros['user_id'] ?? '')), fn ($q) => $q->where('user_id', $filtros['user_id']))
            ->when(filled($filtros['tabla'] ?? null), fn ($q) => $q->where('tabla', $filtros['tabla']))
            ->when(filled($filtros['accion'] ?? null), fn ($q) => $q->where('accion', $filtros['accion']))
            ->when($fecha, fn ($q) => $q->whereDate('created_at', $fecha))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('bitacora.index', [
            'filtros' => $filtros,
            'registros' => $registros,
            'enlaces' => Bitacora::enlaces($registros->getCollection()),
            'usuarios' => User::whereIn('id', Bitacora::select('user_id')->distinct())->orderBy('name')->get(['id', 'name']),
            'tablas' => Bitacora::distinct()->orderBy('tabla')->pluck('tabla'),
            'acciones' => Bitacora::distinct()->orderBy('accion')->pluck('accion'),
        ]);
    }
}
