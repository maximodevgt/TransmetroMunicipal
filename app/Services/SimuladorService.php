<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Bitacora;
use App\Models\Bus;
use App\Models\Estacion;
use App\Models\Linea;
use App\Models\Piloto;
use App\Models\Recorrido;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Simulador de la operación para la demostración del mapa.
 *
 * Mueve los recorridos en curso estación por estación usando RecorridoService y AlertaService
 * (las llegadas, alertas y pasajeros en espera son registros reales). El horario de cada bus
 * (de qué estación sale, hacia cuál va y a qué hora) vive en caché: no hay tablas nuevas.
 * El tiempo está acelerado: un kilómetro toma unos segundos.
 *
 * Avanza con avanzar(): lo llama en bucle el comando transmetro:simular y, mientras el mapa está
 * abierto, la página de seguimiento cada 3 segundos.
 */
class SimuladorService
{
    public const CLAVE = 'transmetro:simulador';

    public const SEGUNDOS_POR_KM = 20;

    public const TRAMO_MINIMO_SEG = 6;

    public const PARADA_SEG = 4;

    /** Los 5 minutos de espera por baja ocupación, a escala de la simulación. */
    public const ESPERA_EXTRA_SEG = 12;

    /** Separación entre buses que salen a la vez de la misma terminal. */
    public const SEPARACION_SALIDAS_SEG = 20;

    /** Probabilidad, en cada avance, de registrar pasajeros en espera en alguna estación. */
    public const PROBABILIDAD_ESPERA = 0.15;

    /** De esos registros de espera, cuántos son de alta demanda (≥ 150 %): una cada 2 o 3 minutos en promedio. */
    public const PROBABILIDAD_ALTA_DEMANDA = 0.15;

    /**
     * Probabilidad de que un bus llegue con menos del 25 % de ocupación. Incluso en el peor caso
     * (tramos y paradas mínimas) da menos de una alerta de baja ocupación por minuto con 6 buses.
     */
    public const PROBABILIDAD_BAJA_OCUPACION = 0.025;

    /** Segundos mínimos entre dos avances (varias pestañas pueden pedirlo a la vez). */
    public const INTERVALO_MINIMO_SEG = 2.5;

    public function __construct(
        private RecorridoService $recorridos,
        private AlertaService $alertas,
    ) {}

    public function estado(): array
    {
        return Cache::get(self::CLAVE, ['activo' => false, 'buses' => 0, 'propios' => [], 'tramos' => [], 'ultimo' => 0.0]);
    }

    public function activo(): bool
    {
        return $this->estado()['activo'];
    }

    /**
     * Horario del recorrido: ['desde' => estación, 'hacia' => estación|null, 'salida', 'llegada', 'fin'] en segundos Unix.
     */
    public function tramo(int $recorridoId): ?array
    {
        return $this->estado()['tramos'][$recorridoId] ?? null;
    }

    public function iniciar(int $buses): array
    {
        $estado = $this->estado();
        $estado['activo'] = true;
        $estado['buses'] = max(1, min(20, $buses));
        $estado['ultimo'] = 0.0;
        $this->guardar($estado);

        return $this->avanzar();
    }

    /**
     * Detiene la simulación: finaliza los recorridos que inició (sus buses vuelven a disponible).
     * Los recorridos que no inició, como un refuerzo manual, siguen en curso.
     */
    public function detener(): int
    {
        return $this->conBloqueo(function () {
            $estado = $this->estado();
            $finalizados = $this->comoSimulador(function () use ($estado) {
                $recorridos = Recorrido::with(['bus', 'linea'])->whereIn('id', $estado['propios'])->where('estado', 'en_curso')->get();
                foreach ($recorridos as $recorrido) {
                    $this->finalizar($recorrido);
                }

                return $recorridos->count();
            });

            $this->guardar(['activo' => false, 'buses' => $estado['buses'], 'propios' => [], 'tramos' => [], 'ultimo' => 0.0]);

            return $finalizados;
        }) ?? 0;
    }

    /**
     * Un paso de la simulación: llegadas, finales, nuevos recorridos y pasajeros en espera.
     */
    public function avanzar(?float $ahora = null): array
    {
        $ahora ??= self::ahora();
        $estado = $this->estado();
        if (! $estado['activo'] || $ahora - $estado['ultimo'] < self::INTERVALO_MINIMO_SEG) {
            return $estado;
        }

        return $this->conBloqueo(fn () => $this->comoSimulador(function () use ($ahora) {
            $estado = $this->estado();
            $enCurso = Recorrido::with(['bus', 'linea.estaciones', 'visitas'])->where('estado', 'en_curso')->get()->keyBy('id');

            // Se olvida lo que ya no está en curso (finalizado a mano o por otro proceso).
            $estado['tramos'] = array_intersect_key($estado['tramos'], $enCurso->all());
            $estado['propios'] = array_values(array_intersect($estado['propios'], $enCurso->keys()->all()));

            foreach ($enCurso as $recorrido) {
                $this->moverRecorrido($recorrido, $estado, $ahora);
            }

            $this->completarFlota($estado, $ahora);

            if (mt_rand() / mt_getrandmax() < self::PROBABILIDAD_ESPERA) {
                $this->registrarEsperaAleatoria($enCurso);
            }

            $estado['ultimo'] = $ahora;
            $this->guardar($estado);

            return $estado;
        })) ?? $this->estado();
    }

    public static function ahora(): float
    {
        return now()->getTimestampMs() / 1000;
    }

    private function moverRecorrido(Recorrido $recorrido, array &$estado, float $ahora): void
    {
        $tramo = $estado['tramos'][$recorrido->id] ?? null;

        // Recorrido nuevo para el simulador (propio o adoptado, como un refuerzo): se le arma el horario.
        if ($tramo === null) {
            $ultima = $recorrido->visitas->last();
            if ($ultima === null) {
                $this->llegar($recorrido, $recorrido->linea->estaciones->first(), $estado, $ahora);
            } else {
                $this->programarDesde($recorrido, $ultima->estacion_id, $ultima->espera_extra_min, $estado, $ahora);
            }

            return;
        }

        if ($tramo['fin'] !== null && $ahora >= $tramo['fin']) {
            $this->finalizar($recorrido);
            unset($estado['tramos'][$recorrido->id]);

            return;
        }

        if ($tramo['hacia'] !== null && $ahora >= $tramo['llegada']) {
            $this->llegar($recorrido, $recorrido->linea->estaciones->firstWhere('id', $tramo['hacia']), $estado, $ahora);
        }
    }

    private function llegar(Recorrido $recorrido, ?Estacion $estacion, array &$estado, float $ahora): void
    {
        if ($estacion === null) {
            unset($estado['tramos'][$recorrido->id]);

            return;
        }

        $ultima = $recorrido->linea->estaciones->last()?->id === $estacion->id;
        try {
            [$visita] = $this->recorridos->registrarLlegada($recorrido, $estacion, self::pasajerosAlLlegar($recorrido->bus->capacidad_maxima, $ultima));
        } catch (ReglaNegocioException) {
            // Alguien registró la llegada a mano: en el siguiente paso se rehace el horario.
            unset($estado['tramos'][$recorrido->id]);

            return;
        }

        $this->programarDesde($recorrido, $estacion->id, $visita->espera_extra_min, $estado, $ahora);
    }

    /**
     * Horario desde la estación donde está el bus hasta la siguiente (o hasta el fin si era la última).
     */
    private function programarDesde(Recorrido $recorrido, int $estacionId, int $esperaExtraMin, array &$estado, float $ahora): void
    {
        $estaciones = $recorrido->linea->estaciones->values();
        $indice = $estaciones->search(fn ($e) => $e->id === $estacionId);
        $siguiente = $indice === false ? null : $estaciones->get($indice + 1);
        $salida = $ahora + self::PARADA_SEG + ($esperaExtraMin > 0 ? self::ESPERA_EXTRA_SEG : 0);

        $estado['tramos'][$recorrido->id] = $siguiente === null
            ? ['desde' => $estacionId, 'hacia' => null, 'salida' => $salida, 'llegada' => $salida, 'fin' => $salida]
            : [
                'desde' => $estacionId,
                'hacia' => $siguiente->id,
                'salida' => $salida,
                'llegada' => $salida + max(self::TRAMO_MINIMO_SEG, (float) $siguiente->pivot->distancia_anterior_km * self::SEGUNDOS_POR_KM),
                'fin' => null,
            ];
    }

    /**
     * Inicia recorridos propios hasta tener la cantidad pedida, repartidos entre las líneas.
     */
    private function completarFlota(array &$estado, float $ahora): void
    {
        $faltan = $estado['buses'] - count($estado['propios']);
        if ($faltan <= 0) {
            return;
        }

        $lineas = Linea::with('estaciones')->where('estado', 'activa')->has('estaciones')
            ->withCount(['recorridos as en_curso' => fn ($q) => $q->where('estado', 'en_curso')])
            ->get();
        // Los buses que salen juntos de la misma terminal se escalonan para no ir encimados en el mapa.
        $salidasPorLinea = [];

        while ($faltan > 0 && $lineas->isNotEmpty()) {
            $linea = $lineas->sortBy('en_curso')->first();
            $bus = Bus::where('linea_id', $linea->id)->where('estado', 'disponible')->whereDoesntHave('recorridoEnCurso')->inRandomOrder()->first();
            $piloto = Piloto::where('estado', 'activo')->whereDoesntHave('recorridos', fn ($q) => $q->where('estado', 'en_curso'))->inRandomOrder()->first();

            if ($piloto === null) {
                return;
            }
            if ($bus === null) {
                $lineas = $lineas->reject(fn ($l) => $l->id === $linea->id);

                continue;
            }

            try {
                $recorrido = $this->recorridos->iniciar($linea, $bus, $piloto);
            } catch (ReglaNegocioException) {
                $lineas = $lineas->reject(fn ($l) => $l->id === $linea->id);

                continue;
            }
            Bitacora::registrar('iniciar', $recorrido, "Recorrido de {$bus->codigo} en la línea {$linea->codigo} con el piloto {$piloto->nombre} (simulador)");

            $recorrido->setRelation('linea', $linea)->setRelation('bus', $bus->fresh())->setRelation('visitas', collect());
            $estado['propios'][] = $recorrido->id;
            $orden = $salidasPorLinea[$linea->id] = ($salidasPorLinea[$linea->id] ?? -1) + 1;
            $this->llegar($recorrido, $linea->estaciones->first(), $estado, $ahora + $orden * self::SEPARACION_SALIDAS_SEG);

            $linea->en_curso++;
            $faltan--;
        }
    }

    /**
     * De vez en cuando llegan pasajeros a una estación; a veces muchos, para que haya alertas de alta demanda.
     */
    private function registrarEsperaAleatoria($enCurso): void
    {
        $estaciones = $enCurso->flatMap(fn ($r) => $r->linea->estaciones)->unique('id')->values();
        if ($estaciones->isEmpty()) {
            return;
        }

        $estacion = $estaciones->random();
        $referencia = $this->alertas->capacidadReferencia($estacion)[0] ?? 100;
        $cantidad = mt_rand() / mt_getrandmax() < self::PROBABILIDAD_ALTA_DEMANDA
            ? mt_rand((int) ceil($referencia * AlertaService::ALTA_DEMANDA), (int) ceil($referencia * 2.2))
            : mt_rand(5, (int) floor($referencia * 1.2));

        // Lo registra el operador de la estación (o el administrador si no tiene).
        $operador = User::where('estacion_id', $estacion->id)->where('activo', true)->first() ?? Auth::user();
        DB::transaction(fn () => $this->alertas->registrarEspera($estacion, $operador, $cantidad));
    }

    /**
     * Pasajeros realistas: casi siempre entre 35 % y 95 % de la capacidad, rara vez muy pocos
     * (baja ocupación) y casi vacío al llegar a la terminal, donde no se revisa la ocupación.
     */
    public static function pasajerosAlLlegar(int $capacidad, bool $ultimaEstacion): int
    {
        if ($ultimaEstacion) {
            return mt_rand(0, (int) floor($capacidad * 0.1));
        }

        return mt_rand() / mt_getrandmax() < self::PROBABILIDAD_BAJA_OCUPACION
            ? mt_rand((int) ceil($capacidad * 0.05), (int) floor($capacidad * 0.24))
            : mt_rand((int) ceil($capacidad * 0.35), (int) floor($capacidad * 0.95));
    }

    /**
     * Alertas esperadas por minuto con $buses en ruta, en el peor caso (llegadas lo más seguidas posible).
     *
     * @return array{baja_ocupacion: float, alta_demanda: float}
     */
    public static function alertasPorMinuto(int $buses): array
    {
        $llegadasPorMinuto = $buses * 60 / (self::PARADA_SEG + self::TRAMO_MINIMO_SEG);
        $avancesPorMinuto = 60 / 3;

        return [
            'baja_ocupacion' => $llegadasPorMinuto * self::PROBABILIDAD_BAJA_OCUPACION,
            'alta_demanda' => $avancesPorMinuto * self::PROBABILIDAD_ESPERA * self::PROBABILIDAD_ALTA_DEMANDA,
        ];
    }

    private function finalizar(Recorrido $recorrido): void
    {
        try {
            $km = number_format($this->recorridos->finalizar($recorrido), 2);
        } catch (ReglaNegocioException) {
            return;
        }
        Bitacora::registrar('finalizar', $recorrido, "Recorrido de {$recorrido->bus->codigo} en la línea {$recorrido->linea->codigo} finalizado: {$km} km (simulador)");
    }

    /**
     * Lo que hace el simulador queda en la bitácora a nombre del administrador municipal.
     */
    private function comoSimulador(callable $accion): mixed
    {
        $anterior = Auth::user();
        $simulador = User::where('rol', User::ROL_ADMINISTRADOR)->where('activo', true)->orderBy('id')->firstOrFail();
        Auth::setUser($simulador);

        try {
            return $accion();
        } finally {
            $anterior ? Auth::setUser($anterior) : Auth::forgetUser();
        }
    }

    /**
     * Ejecuta la acción si nadie más está avanzando la simulación; si no, devuelve null.
     */
    private function conBloqueo(callable $accion): mixed
    {
        $resultado = Cache::lock(self::CLAVE.':bloqueo', 15)->get($accion);

        return $resultado === false ? null : $resultado;
    }

    private function guardar(array $estado): void
    {
        Cache::forever(self::CLAVE, $estado);
    }
}
