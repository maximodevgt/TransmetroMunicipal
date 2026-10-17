<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Bus;
use App\Models\Estacion;
use App\Models\Linea;
use App\Models\Piloto;
use App\Models\Recorrido;
use App\Services\RecorridoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class RecorridoController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        // Ambos roles consultan y registran llegadas (el operador solo en su estación); iniciar y finalizar es del administrador.
        return [new Middleware('rol:administrador', only: ['create', 'store', 'finalizar'])];
    }

    public function index(Request $request): View
    {
        $filtros = $request->only('linea_id', 'estado', 'fecha');
        $fecha = rescue(fn () => filled($filtros['fecha'] ?? null) ? Carbon::createFromFormat('Y-m-d', $filtros['fecha']) : null, null, false);

        return view('recorridos.index', [
            'filtros' => $filtros,
            'lineas' => Linea::orderBy('id')->get(),
            'recorridos' => Recorrido::with(['linea', 'bus', 'piloto'])->withCount('visitas')
                ->when(ctype_digit((string) ($filtros['linea_id'] ?? '')), fn ($q) => $q->where('linea_id', $filtros['linea_id']))
                ->when(array_key_exists($filtros['estado'] ?? '', Recorrido::ESTADOS), fn ($q) => $q->where('estado', $filtros['estado']))
                ->when($fecha, fn ($q) => $q->whereDate('inicio', $fecha))
                ->orderByRaw("estado = 'en_curso' desc")
                ->orderByDesc('inicio')
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('recorridos.form', [
            'lineas' => Linea::where('estado', 'activa')->has('estaciones')->orderBy('id')->get(),
            // Solo buses disponibles con línea y sin recorrido en curso, y pilotos activos que no estén conduciendo.
            'buses' => Bus::with('linea')->whereNotNull('linea_id')->where('estado', 'disponible')->whereDoesntHave('recorridoEnCurso')
                ->orderBy('linea_id')->orderBy('codigo')->get(),
            'pilotos' => Piloto::where('estado', 'activo')->whereDoesntHave('recorridos', fn ($q) => $q->where('estado', 'en_curso'))
                ->orderBy('nombre')->get(),
            'lineaElegida' => $request->query('linea_id'),
        ]);
    }

    public function store(Request $request, RecorridoService $servicio): RedirectResponse
    {
        $datos = $request->validate([
            'linea_id' => ['required', 'exists:lineas,id'],
            'bus_id' => ['required', 'exists:buses,id'],
            'piloto_id' => ['required', 'exists:pilotos,id'],
        ]);

        $recorrido = $servicio->iniciar(Linea::findOrFail($datos['linea_id']), Bus::findOrFail($datos['bus_id']), Piloto::findOrFail($datos['piloto_id']));
        $recorrido->load(['linea', 'bus', 'piloto']);
        Bitacora::registrar('iniciar', $recorrido, "Recorrido de {$recorrido->bus->codigo} en la línea {$recorrido->linea->codigo} con el piloto {$recorrido->piloto->nombre}");

        return redirect()->route('recorridos.show', $recorrido)->with('ok', "Recorrido iniciado: {$recorrido->bus->codigo} está en ruta.");
    }

    public function show(Recorrido $recorrido): View
    {
        $recorrido->load(['linea.estaciones', 'bus', 'piloto', 'visitas']);

        return view('recorridos.show', [
            'recorrido' => $recorrido,
            'visitas' => $recorrido->visitas->keyBy('estacion_id'),
            'siguiente' => $recorrido->enCurso() ? $recorrido->siguienteEstacion() : null,
            'distancia' => $recorrido->distanciaRecorrida(),
            'distanciaTotal' => $recorrido->linea->estaciones->sum('pivot.distancia_anterior_km'),
        ]);
    }

    /**
     * Registrar la llegada del bus a la siguiente estación. El operador solo puede hacerlo en la suya.
     */
    public function llegada(Request $request, Recorrido $recorrido, RecorridoService $servicio): RedirectResponse
    {
        $usuario = $request->user();
        $datos = $request->validate([
            'estacion_id' => ['required', 'exists:estaciones,id'],
            'pasajeros_a_bordo' => ['required', 'integer', 'min:0'],
            'hora_llegada' => ['nullable', 'date'],
        ]);
        abort_if(! $usuario->esAdministrador() && (int) $datos['estacion_id'] !== $usuario->estacion_id, 403, 'Solo puede registrar llegadas en su estación.');

        $estacion = Estacion::findOrFail($datos['estacion_id']);
        $hora = filled($datos['hora_llegada'] ?? null) ? Carbon::parse($datos['hora_llegada']) : null;
        [$visita, $alerta] = $servicio->registrarLlegada($recorrido, $estacion, (int) $datos['pasajeros_a_bordo'], $hora);

        $bus = $recorrido->bus;
        Bitacora::registrar('registrar_llegada', $recorrido, "{$bus->codigo} llegó a {$estacion->nombre} con {$visita->pasajeros_a_bordo} pasajeros");

        $respuesta = back()->with('ok', "Llegada de {$bus->codigo} a {$estacion->nombre} registrada.");

        return $alerta ? $respuesta->with('advertencia', $alerta->mensaje) : $respuesta;
    }

    public function finalizar(Recorrido $recorrido, RecorridoService $servicio): RedirectResponse
    {
        $distancia = $servicio->finalizar($recorrido);
        $recorrido->load(['bus', 'linea']);
        $km = number_format($distancia, 2);
        Bitacora::registrar('finalizar', $recorrido, "Recorrido de {$recorrido->bus->codigo} en la línea {$recorrido->linea->codigo} finalizado: {$km} km");

        return redirect()->route('recorridos.show', $recorrido)->with('ok', "Recorrido finalizado: {$km} km recorridos. {$recorrido->bus->codigo} vuelve a estar disponible.");
    }
}
