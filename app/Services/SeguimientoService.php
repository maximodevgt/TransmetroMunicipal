<?php

namespace App\Services;

use App\Models\Alerta;
use App\Models\Linea;
use App\Models\Recorrido;
use App\Models\RegistroPasajeros;
use App\Models\User;
use App\Support\Interpolacion;
use Illuminate\Support\Collection;

/**
 * Datos del mapa de seguimiento en vivo. Pocas consultas fijas (con eager loading) sin importar
 * cuántos buses haya en ruta: la página lo pide cada 3 segundos.
 */
class SeguimientoService
{
    public function __construct(
        private SimuladorService $simulador,
        private ResolucionAlertaService $resolucion,
    ) {}

    public function datos(User $usuario, ?float $ahora = null): array
    {
        $ahora ??= SimuladorService::ahora();

        $lineas = Linea::with('estaciones:estaciones.id,estaciones.nombre,estaciones.latitud,estaciones.longitud')->orderBy('id')->get();
        $coordenadas = $lineas->flatMap->estaciones->keyBy('id')->filter(fn ($e) => $e->tieneCoordenadas())
            ->map(fn ($e) => ['lat' => $e->latitud, 'lng' => $e->longitud]);

        // El operador solo recibe las alertas de su estación (activasPara ya lo filtra).
        $alertas = Alerta::with(['estacion', 'bus', 'linea'])->activasPara($usuario)->orderByDesc('id')->get();
        $resumenAlertas = $alertas->mapWithKeys(fn (Alerta $a) => [$a->id => $this->resumenAlerta($a, $usuario)]);

        $espera = RegistroPasajeros::whereIn('id', RegistroPasajeros::selectRaw('MAX(id)')->groupBy('estacion_id'))
            ->get()->keyBy('estacion_id');

        $recorridos = Recorrido::with(['bus', 'piloto', 'visitas'])->where('estado', 'en_curso')->orderBy('id')->get();
        $simulacion = $this->simulador->estado();

        return [
            'ahora' => $ahora,
            'usuario' => [
                'es_administrador' => $usuario->esAdministrador(),
                'estacion_id' => $usuario->estacion_id,
            ],
            'simulacion' => [
                'activa' => $simulacion['activo'],
                'buses' => $simulacion['buses'],
                'propios' => count($simulacion['propios']),
            ],
            'lineas' => $lineas->map(fn (Linea $l) => [
                'id' => $l->id,
                'codigo' => $l->codigo,
                'nombre' => $l->nombre,
                'color' => $l->color(),
                'trazo' => $l->estaciones->map(fn ($e) => $coordenadas->get($e->id))->filter()->values(),
            ])->values(),
            'estaciones' => $this->estaciones($lineas, $coordenadas, $alertas, $resumenAlertas, $espera),
            'buses' => $recorridos->map(fn (Recorrido $r) => $this->bus($r, $lineas->firstWhere('id', $r->linea_id), $coordenadas, $alertas, $resumenAlertas, $simulacion['tramos'][$r->id] ?? null, $ahora))
                ->filter()->values(),
            'alertas' => $resumenAlertas->values()->take(10),
        ];
    }

    private function estaciones(Collection $lineas, Collection $coordenadas, Collection $alertas, Collection $resumenAlertas, Collection $espera): Collection
    {
        return $lineas->flatMap(fn (Linea $l) => $l->estaciones->map(fn ($e) => [$e, $l]))
            ->groupBy(fn ($par) => $par[0]->id)
            ->filter(fn ($pares, $id) => $coordenadas->has($id))
            ->map(function ($pares, $id) use ($coordenadas, $alertas, $resumenAlertas, $espera) {
                $estacion = $pares[0][0];
                $registro = $espera->get($id);

                return [
                    'id' => $id,
                    'nombre' => $estacion->nombre,
                    ...$coordenadas->get($id),
                    'lineas' => collect($pares)->map(fn ($par) => $par[1]->codigo)->values(),
                    'color' => $pares[0][1]->color(),
                    'en_espera' => $registro ? ['cantidad' => $registro->cantidad, 'hora' => $registro->registrado_en->format('H:i')] : null,
                    'alertas' => $alertas->where('estacion_id', $id)->map(fn ($a) => $resumenAlertas[$a->id])->values(),
                    'url' => route('estaciones.show', $id),
                ];
            })->values();
    }

    private function bus(Recorrido $recorrido, ?Linea $linea, Collection $coordenadas, Collection $alertas, Collection $resumenAlertas, ?array $tramo, float $ahora): ?array
    {
        $estaciones = $linea?->estaciones->values() ?? collect();
        $ultima = $recorrido->visitas->last();
        $indice = $ultima ? $estaciones->search(fn ($e) => $e->id === $ultima->estacion_id) : false;
        $actual = $indice === false ? null : $estaciones->get($indice);
        $proxima = $estaciones->get($indice === false ? 0 : $indice + 1);

        // Posición: entre dos estaciones si el simulador lo está moviendo; si no, en la última estación (o en la primera).
        $enMovimiento = $tramo && $tramo['hacia'] && $coordenadas->has($tramo['desde']) && $coordenadas->has($tramo['hacia']);
        $posicion = $enMovimiento
            ? Interpolacion::punto($coordenadas[$tramo['desde']], $coordenadas[$tramo['hacia']], Interpolacion::fraccion($tramo['salida'], $tramo['llegada'], $ahora))
            : $coordenadas->get($actual?->id ?? $estaciones->first()?->id);
        if ($posicion === null) {
            return null;
        }

        $bus = $recorrido->bus;
        $pasajeros = $ultima?->pasajeros_a_bordo ?? 0;

        return [
            'recorrido_id' => $recorrido->id,
            'bus_id' => $bus->id,
            'codigo' => $bus->codigo,
            'linea_id' => $recorrido->linea_id,
            'linea' => $linea?->codigo,
            'color' => $linea?->color(),
            'piloto' => $recorrido->piloto->nombre,
            'capacidad' => $bus->capacidad_maxima,
            'pasajeros' => $pasajeros,
            'ocupacion' => (int) round($pasajeros / max(1, $bus->capacidad_maxima) * 100),
            'estacion_actual' => $actual?->nombre,
            'en_estacion' => ! $enMovimiento || $ahora < $tramo['salida'],
            'proxima_estacion' => $proxima?->nombre,
            'actualizado' => $ultima?->hora_llegada->format('H:i:s'),
            'posicion' => $posicion,
            'tramo' => $enMovimiento ? [
                'desde' => $coordenadas[$tramo['desde']],
                'hacia' => $coordenadas[$tramo['hacia']],
                'salida' => $tramo['salida'],
                'llegada' => $tramo['llegada'],
            ] : null,
            'alertas' => $alertas->where('bus_id', $bus->id)->map(fn ($a) => $resumenAlertas[$a->id])->values(),
            'url' => route('recorridos.show', $recorrido),
        ];
    }

    private function resumenAlerta(Alerta $alerta, User $usuario): array
    {
        return [
            'id' => $alerta->id,
            'tipo' => $alerta->tipo,
            'tipo_texto' => $alerta->tipoTexto(),
            'mensaje' => $alerta->mensaje,
            'lugar' => $alerta->lugar(),
            'grave' => in_array($alerta->tipo, ['alta_demanda', 'insuficiencia_unidades'], true),
            'url' => route('alertas.show', $alerta),
            'resolver' => $this->resolucion->accion($alerta, $usuario),
        ];
    }
}
