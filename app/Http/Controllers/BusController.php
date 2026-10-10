<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Bus;
use App\Models\Linea;
use App\Models\Parqueo;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BusController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('rol:administrador', except: ['index', 'show'])];
    }

    public function index(Request $request): View
    {
        $filtros = $request->only('linea_id', 'estado');

        return view('buses.index', [
            'filtros' => $filtros,
            'lineas' => Linea::orderBy('id')->get(),
            'buses' => Bus::with(['linea', 'parqueo'])
                ->when(($filtros['linea_id'] ?? '') === 'sin', fn ($q) => $q->whereNull('linea_id'))
                ->when(ctype_digit((string) ($filtros['linea_id'] ?? '')), fn ($q) => $q->where('linea_id', $filtros['linea_id']))
                ->when(in_array($filtros['estado'] ?? '', Bus::ESTADOS, true), fn ($q) => $q->where('estado', $filtros['estado']))
                ->orderBy('id')
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('buses.form', $this->datosFormulario(new Bus(['estado' => 'disponible'])));
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $bus = Bus::create($this->validar($request));
        } catch (QueryException $e) {
            return $this->volverConErrorBd($e);
        }
        Bitacora::registrar('crear', $bus, "Registro del bus {$bus->codigo}".($bus->linea ? " en la linea {$bus->linea->codigo}" : ''));

        return redirect()->route('buses.show', $bus)->with('ok', 'Bus registrado.');
    }

    public function show(Bus $bus): View
    {
        $bus->load(['linea', 'parqueo']);

        return view('buses.show', ['bus' => $bus]);
    }

    public function edit(Bus $bus): View
    {
        return view('buses.form', $this->datosFormulario($bus));
    }

    public function update(Request $request, Bus $bus): RedirectResponse
    {
        try {
            $bus->update($this->validar($request, $bus));
        } catch (QueryException $e) {
            return $this->volverConErrorBd($e);
        }

        if ($bus->wasChanged('linea_id')) {
            Bitacora::registrar('asignar_linea', $bus, "{$bus->codigo} asignado a la linea ".($bus->load('linea')->linea?->codigo ?? 'ninguna'));
        } else {
            Bitacora::registrar('editar', $bus, "Actualizacion del bus {$bus->codigo}");
        }

        return redirect()->route('buses.show', $bus)->with('ok', 'Bus actualizado.');
    }

    public function destroy(Bus $bus): RedirectResponse
    {
        try {
            $bus->delete();
        } catch (QueryException $e) {
            return back()->with('error', $this->mensajeErrorBd($e));
        }
        Bitacora::registrar('eliminar', $bus, "Eliminacion del bus {$bus->codigo}");

        return redirect()->route('buses.index')->with('ok', 'Bus eliminado.');
    }

    private function datosFormulario(Bus $bus): array
    {
        return [
            'bus' => $bus,
            'lineas' => Linea::withCount(['estaciones', 'buses'])->orderBy('id')->get(),
            'parqueos' => Parqueo::withCount('buses')->orderBy('nombre')->get(),
        ];
    }

    private function validar(Request $request, ?Bus $bus = null): array
    {
        // El maximo de buses por linea (estaciones x 2) lo valida el trigger en MySQL.
        return $request->validate([
            'linea_id' => ['nullable', 'exists:lineas,id'],
            'parqueo_id' => ['required', 'exists:parqueos,id'],
            'placa' => ['required', 'string', 'max:10', Rule::unique('buses', 'placa')->ignore($bus)],
            'codigo' => ['required', 'string', 'max:20', Rule::unique('buses', 'codigo')->ignore($bus)],
            'capacidad_maxima' => ['required', 'integer', 'min:1', 'max:500'],
            'estado' => ['required', Rule::in(Bus::ESTADOS)],
        ]);
    }
}
