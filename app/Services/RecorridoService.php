<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Alerta;
use App\Models\Bus;
use App\Models\Estacion;
use App\Models\Linea;
use App\Models\Piloto;
use App\Models\Recorrido;
use App\Models\RecorridoEstacion;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Ciclo de un recorrido: iniciar, registrar la llegada a cada estación en orden y finalizar.
 * Las reglas que no se cumplen se informan con ReglaNegocioException.
 */
class RecorridoService
{
    /** Minutos que el bus se detiene en cada estación, además de la espera extra por baja ocupación. */
    public const PARADA_MIN = 1;

    public function __construct(private AlertaService $alertas) {}

    public function iniciar(Linea $linea, Bus $bus, Piloto $piloto): Recorrido
    {
        return DB::transaction(function () use ($linea, $bus, $piloto) {
            // Bloquea bus y piloto para que dos recorridos no los tomen a la vez.
            $bus = Bus::whereKey($bus->id)->lockForUpdate()->firstOrFail();
            $piloto = Piloto::whereKey($piloto->id)->lockForUpdate()->firstOrFail();

            if ($linea->estado !== 'activa') {
                throw new ReglaNegocioException("La línea {$linea->codigo} está inactiva.");
            }
            if ($linea->estaciones()->count() === 0) {
                throw new ReglaNegocioException("La línea {$linea->codigo} no tiene estaciones.");
            }
            if ($bus->linea_id !== $linea->id) {
                throw new ReglaNegocioException("El bus {$bus->codigo} no está asignado a la línea {$linea->codigo}.");
            }
            if ($enCurso = $bus->recorridoEnCurso()->first()) {
                throw new ReglaNegocioException("El bus {$bus->codigo} ya está en ruta (recorrido #{$enCurso->id} en curso).");
            }
            if ($bus->estado !== 'disponible') {
                throw new ReglaNegocioException("El bus {$bus->codigo} no está disponible (estado: ".str_replace('_', ' ', $bus->estado).').');
            }
            if ($piloto->estado !== 'activo') {
                throw new ReglaNegocioException("El piloto {$piloto->nombre} está inactivo.");
            }
            if ($conduciendo = $piloto->recorridos()->where('estado', 'en_curso')->first()) {
                throw new ReglaNegocioException("El piloto {$piloto->nombre} ya está conduciendo otro recorrido (#{$conduciendo->id}).");
            }

            $recorrido = Recorrido::create([
                'linea_id' => $linea->id,
                'bus_id' => $bus->id,
                'piloto_id' => $piloto->id,
                'inicio' => now(),
                'estado' => 'en_curso',
            ]);
            $bus->update(['estado' => 'en_ruta']);

            return $recorrido;
        });
    }

    /**
     * Registra la llegada a la siguiente estación del recorrido. Devuelve la visita y la alerta
     * de baja ocupación si se generó o actualizó.
     *
     * @return array{0: RecorridoEstacion, 1: ?Alerta}
     */
    public function registrarLlegada(Recorrido $recorrido, Estacion $estacion, int $pasajeros, ?CarbonInterface $hora = null): array
    {
        $hora ??= now();

        return DB::transaction(function () use ($recorrido, $estacion, $pasajeros, $hora) {
            $recorrido = Recorrido::whereKey($recorrido->id)->lockForUpdate()->with(['bus', 'linea'])->firstOrFail();

            if (! $recorrido->enCurso()) {
                throw new ReglaNegocioException("El recorrido #{$recorrido->id} no está en curso.");
            }

            $siguiente = $recorrido->siguienteEstacion();
            if ($siguiente === null) {
                throw new ReglaNegocioException('El recorrido ya pasó por todas las estaciones de la línea; solo falta finalizarlo.');
            }
            if ($siguiente->id !== $estacion->id) {
                throw new ReglaNegocioException("La siguiente estación del recorrido es {$siguiente->nombre}, no {$estacion->nombre}.");
            }

            $capacidad = $recorrido->bus->capacidad_maxima;
            if ($pasajeros > $capacidad) {
                throw new ReglaNegocioException("Los pasajeros a bordo ({$pasajeros}) superan la capacidad del bus {$recorrido->bus->codigo} ({$capacidad}).");
            }

            $anterior = $recorrido->ultimaVisita();
            $minimo = $anterior?->hora_llegada ?? $recorrido->inicio;
            // El formulario envía la hora sin segundos: dentro del mismo minuto se toma la hora mínima.
            if ($hora->lt($minimo->copy()->startOfMinute())) {
                throw new ReglaNegocioException('La hora de llegada no puede ser anterior a '.$minimo->format('d/m/Y H:i').'.');
            }
            $hora = $hora->max($minimo);
            if ($hora->gt(now()->addMinute())) {
                throw new ReglaNegocioException('La hora de llegada no puede estar en el futuro.');
            }

            if ($anterior && $anterior->hora_salida === null) {
                $this->marcarSalida($anterior, $hora);
            }

            $visita = $recorrido->visitas()->create([
                'estacion_id' => $estacion->id,
                'hora_llegada' => $hora,
                'pasajeros_a_bordo' => $pasajeros,
                'espera_extra_min' => 0,
            ]);

            return [$visita, $this->alertas->revisarOcupacion($visita)];
        });
    }

    /**
     * Finaliza el recorrido, devuelve el bus a 'disponible' y retorna la distancia recorrida en km.
     */
    public function finalizar(Recorrido $recorrido): float
    {
        return DB::transaction(function () use ($recorrido) {
            $recorrido = Recorrido::whereKey($recorrido->id)->lockForUpdate()->with('bus')->firstOrFail();

            if (! $recorrido->enCurso()) {
                throw new ReglaNegocioException("El recorrido #{$recorrido->id} ya no está en curso.");
            }

            $ultima = $recorrido->ultimaVisita();
            if ($ultima && $ultima->hora_salida === null) {
                $this->marcarSalida($ultima, now());
            }

            $recorrido->update(['fin' => now(), 'estado' => 'finalizado']);
            $recorrido->bus->update(['estado' => 'disponible']);

            return $recorrido->distanciaRecorrida();
        });
    }

    /**
     * El bus sale de la estación después de su parada y la espera extra, sin pasar del momento indicado
     * (la llegada a la siguiente estación o el fin del recorrido).
     */
    private function marcarSalida(RecorridoEstacion $visita, CarbonInterface $limite): void
    {
        $salida = $visita->hora_llegada->copy()->addMinutes(self::PARADA_MIN + $visita->espera_extra_min);
        $visita->update(['hora_salida' => $salida->min($limite)]);
    }
}
