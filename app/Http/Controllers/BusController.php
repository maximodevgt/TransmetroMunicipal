<?php

namespace App\Http\Controllers;

use App\Exceptions\ReglaNegocioException;
use App\Models\Bitacora;
use App\Models\Bus;
use App\Models\Linea;
use App\Models\Parqueo;
use App\Services\AsignacionService;
use App\Services\ResolucionAlertaService;
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

    public function index(Request $request, ResolucionAlertaService $resolucion): View
    {
        $filtros = $request->only('linea_id', 'estado');

        // Con ?alerta=ID (insuficiencia de unidades) se muestra cuántos buses le faltan a la línea y se ofrece asignarlos.
        $alerta = $resolucion->enCurso($request, 'insuficiencia_unidades');
        $faltan = $alerta ? max(0, $alerta->linea->estaciones()->count() - $alerta->linea->buses()->count()) : 0;

        return view('buses.index', [
            'alerta' => $alerta,
            'faltan' => $faltan,
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

    public function store(Request $request, AsignacionService $asignacion): RedirectResponse
    {
        try {
            $bus = Bus::create($this->validar($request));
        } catch (QueryException $e) {
            return $this->volverConErrorBd($e);
        }
        Bitacora::registrar('crear', $bus, "Registro del bus {$bus->codigo}".($bus->linea ? " en la línea {$bus->linea->codigo}" : ''));

        return $this->conMinimoRecuperado(redirect()->route('buses.show', $bus)->with('ok', 'Bus registrado.'), $bus->linea, $asignacion);
    }

    public function show(Bus $bus): View
    {
        $bus->load(['linea', 'parqueo', 'recorridoEnCurso']);

        return view('buses.show', ['bus' => $bus]);
    }

    public function edit(Bus $bus): View
    {
        return view('buses.form', $this->datosFormulario($bus));
    }

    public function update(Request $request, Bus $bus, AsignacionService $asignacion): RedirectResponse
    {
        $lineaAnterior = $bus->linea;
        $datos = $this->validar($request, $bus);
        if ((string) ($datos['linea_id'] ?? '') !== (string) ($bus->linea_id ?? '')) {
            $asignacion->validarBusSinRecorrido($bus, 'quitar o reasignar');
        }

        try {
            $bus->update($datos);
        } catch (QueryException $e) {
            return $this->volverConErrorBd($e);
        }

        $aviso = null;
        $nueva = null;
        if ($bus->wasChanged('linea_id')) {
            $nueva = $bus->load('linea')->linea;
            Bitacora::registrar('asignar_linea', $bus, $nueva
                ? "{$bus->codigo} asignado a la línea {$nueva->codigo}"
                : "{$bus->codigo} retirado de la línea {$lineaAnterior?->codigo}");
            // Si la línea anterior queda con menos buses que estaciones, se guarda igual y se avisa.
            $aviso = $asignacion->revisarMinimo($lineaAnterior);
        } else {
            Bitacora::registrar('editar', $bus, "Actualización del bus {$bus->codigo}");
        }

        $respuesta = $this->conAviso(redirect()->route('buses.show', $bus)->with('ok', 'Bus actualizado.'), $aviso);

        return $this->conMinimoRecuperado($respuesta, $nueva, $asignacion);
    }

    public function destroy(Bus $bus, AsignacionService $asignacion): RedirectResponse
    {
        $asignacion->validarBusSinRecorrido($bus, 'eliminar');
        $linea = $bus->linea;

        try {
            $bus->delete();
        } catch (QueryException $e) {
            return back()->with('error', $this->mensajeErrorBd($e));
        }
        Bitacora::registrar('eliminar', $bus, "Eliminación del bus {$bus->codigo}");

        return $this->conAviso(redirect()->route('buses.index')->with('ok', 'Bus eliminado.'), $asignacion->revisarMinimo($linea));
    }

    /**
     * Asignar un bus sin línea a la línea de una alerta de insuficiencia. Si la línea llega a su mínimo,
     * la alerta queda atendida y se vuelve a su detalle; si no, se sigue en la lista con lo que falta.
     */
    public function asignarLinea(Request $request, Bus $bus, ResolucionAlertaService $resolucion, AsignacionService $asignacion): RedirectResponse
    {
        $alerta = $resolucion->exigir($request, 'insuficiencia_unidades')
            ?? throw new ReglaNegocioException('Indique la alerta de insuficiencia de unidades que se resuelve.');
        if ($bus->linea_id !== null) {
            throw new ReglaNegocioException("El bus {$bus->codigo} ya está asignado a una línea; elija uno sin línea.");
        }
        $linea = $alerta->linea;

        try {
            $bus->update(['linea_id' => $linea->id]);
        } catch (QueryException $e) {
            return $this->volverConErrorBd($e);
        }
        Bitacora::registrar('asignar_linea', $bus, "{$bus->codigo} asignado a la línea {$linea->codigo}");

        $faltan = $linea->estaciones()->count() - $linea->buses()->count();
        if ($faltan <= 0) {
            $hecho = "se asignó {$bus->codigo} a la línea {$linea->codigo}, que recuperó su mínimo de buses";
            $resolucion->resolver($alerta, $hecho);
            $asignacion->revisarMinimoRecuperado($linea);

            return redirect()->route('alertas.show', $alerta)->with('ok', "Alerta #{$alerta->id} resuelta: {$hecho}.");
        }

        // Todavía falta: se actualiza el mensaje de la alerta y se sigue resolviendo.
        $asignacion->revisarMinimo($linea);

        return redirect()->route('buses.index', ['linea_id' => 'sin', 'alerta' => $alerta->id])
            ->with('ok', "{$bus->codigo} asignado a la línea {$linea->codigo}. ".($faltan === 1 ? 'Falta 1 bus.' : "Faltan {$faltan} buses."));
    }

    private function conAviso(RedirectResponse $respuesta, ?string $aviso): RedirectResponse
    {
        return $aviso ? $respuesta->with('advertencia', $aviso) : $respuesta;
    }

    private function conMinimoRecuperado(RedirectResponse $respuesta, ?Linea $linea, AsignacionService $asignacion): RedirectResponse
    {
        return $asignacion->revisarMinimoRecuperado($linea) > 0
            ? $respuesta->with('info', "La línea {$linea->codigo} recuperó el mínimo de buses: su alerta de insuficiencia quedó atendida.")
            : $respuesta;
    }

    private function datosFormulario(Bus $bus): array
    {
        $bus->loadMissing('recorridoEnCurso');

        return [
            'bus' => $bus,
            'estados' => $this->estadosPermitidos($bus),
            'lineas' => Linea::withCount(['estaciones', 'buses'])->orderBy('id')->get(),
            'parqueos' => Parqueo::withCount('buses')->orderBy('nombre')->get(),
        ];
    }

    /**
     * El estado 'en_ruta' lo ponen y quitan los recorridos: un bus en ruta lo conserva
     * hasta finalizar y los demás no pueden elegirlo a mano.
     */
    private function estadosPermitidos(?Bus $bus): array
    {
        return $bus?->exists && $bus->estaEnRuta()
            ? ['en_ruta']
            : array_values(array_diff(Bus::ESTADOS, ['en_ruta']));
    }

    private function validar(Request $request, ?Bus $bus = null): array
    {
        $enRuta = $this->estadosPermitidos($bus) === ['en_ruta'];

        // El máximo de buses por línea (estaciones × 2) lo valida el trigger en MySQL.
        return $request->validate([
            'linea_id' => ['nullable', 'exists:lineas,id'],
            'parqueo_id' => ['required', 'exists:parqueos,id'],
            'placa' => ['required', 'string', 'max:10', Rule::unique('buses', 'placa')->ignore($bus)],
            'codigo' => ['required', 'string', 'max:20', Rule::unique('buses', 'codigo')->ignore($bus)],
            'capacidad_maxima' => ['required', 'integer', 'min:1', 'max:500'],
            'estado' => ['required', Rule::in($this->estadosPermitidos($bus))],
        ], [
            'estado.in' => $enRuta
                ? 'El bus está en ruta: su estado cambia al finalizar el recorrido.'
                : 'Un bus solo queda en ruta al iniciar un recorrido.',
        ]);
    }
}
