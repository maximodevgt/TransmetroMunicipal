<?php

namespace App\Http\Controllers;

use App\Models\Pasajero;
use App\Models\Tarjeta;
use App\Services\PagoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Tarjetas Transmetro (administrador y operador).
 */
class TarjetaController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->only('buscar', 'estado');
        $buscar = trim((string) ($filtros['buscar'] ?? ''));

        return view('tarjetas.index', [
            'filtros' => $filtros,
            'tarjetas' => Tarjeta::with('pasajero')
                ->when($buscar !== '', fn ($q) => $q->where(fn ($q) => $q->where('codigo', 'like', "%{$buscar}%")
                    ->orWhereHas('pasajero', fn ($p) => $p->where('nombre', 'like', "%{$buscar}%"))))
                ->when(array_key_exists($filtros['estado'] ?? '', Tarjeta::ESTADOS), fn ($q) => $q->where('estado', $filtros['estado']))
                ->orderBy('codigo')
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('tarjetas.create', ['pasajeros' => Pasajero::orderBy('nombre')->get(['id', 'nombre'])]);
    }

    /**
     * Emitir una tarjeta a un pasajero existente o a uno nuevo registrado en el mismo formulario.
     */
    public function store(Request $request, PagoService $pagos): RedirectResponse
    {
        $datos = $request->validate([
            'pasajero_id' => ['nullable', 'exists:pasajeros,id'],
            'nombre' => ['required_without:pasajero_id', 'nullable', 'string', 'max:120'],
            'dpi' => ['required_without:pasajero_id', 'nullable', 'digits:13', Rule::unique('pasajeros', 'dpi')],
            'telefono' => ['nullable', 'digits_between:8,15'],
        ], [
            'nombre.required_without' => 'Elija un pasajero o escriba el nombre del nuevo.',
            'dpi.required_without' => 'El DPI del nuevo pasajero es obligatorio.',
            'dpi.digits' => 'El DPI debe tener exactamente 13 dígitos.',
            'dpi.unique' => 'Ya hay un pasajero con ese DPI: elíjalo de la lista.',
        ]);

        $tarjeta = DB::transaction(function () use ($datos, $pagos) {
            $pasajero = filled($datos['pasajero_id'] ?? null)
                ? Pasajero::findOrFail($datos['pasajero_id'])
                : Pasajero::create(['nombre' => $datos['nombre'], 'dpi' => $datos['dpi'], 'telefono' => $datos['telefono'] ?? null]);

            return $pagos->emitir($pasajero);
        });

        return redirect()->route('tarjetas.show', $tarjeta)->with('ok', "Tarjeta {$tarjeta->codigo} emitida a {$tarjeta->pasajero->nombre}.");
    }

    public function show(Tarjeta $tarjeta): View
    {
        return view('tarjetas.show', [
            'tarjeta' => $tarjeta->load('pasajero'),
            'transacciones' => $tarjeta->transacciones()->with(['acceso.estacion', 'estacion', 'user'])->orderByDesc('id')->paginate(10),
        ]);
    }

    public function bloquear(Request $request, Tarjeta $tarjeta, PagoService $pagos): RedirectResponse
    {
        $pagos->bloquear($tarjeta, $this->motivo($request));

        return back()->with('ok', "Tarjeta {$tarjeta->codigo} bloqueada.");
    }

    public function desbloquear(Request $request, Tarjeta $tarjeta, PagoService $pagos): RedirectResponse
    {
        $pagos->desbloquear($tarjeta, $this->motivo($request));

        return back()->with('ok', "Tarjeta {$tarjeta->codigo} desbloqueada.");
    }

    private function motivo(Request $request): string
    {
        return $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:150']], [], ['motivo' => 'motivo'])['motivo'];
    }
}
