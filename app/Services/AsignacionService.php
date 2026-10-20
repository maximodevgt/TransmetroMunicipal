<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Bus;
use App\Models\Estacion;
use App\Models\Linea;
use Illuminate\Support\Facades\DB;

/**
 * Reglas de cantidad de buses por línea: mínimo = estaciones, máximo = estaciones × 2.
 * El máximo al asignar un bus lo valida el trigger de MySQL; aquí se cubren los casos que el trigger no ve.
 */
class AsignacionService
{
    public function __construct(private AlertaService $alertas) {}

    /**
     * Un bus en ruta no se quita ni se reasigna de su línea, ni se elimina, hasta finalizar su recorrido.
     *
     * @param  string  $accion  'quitar o reasignar' | 'eliminar'
     */
    public function validarBusSinRecorrido(Bus $bus, string $accion): void
    {
        if ($recorrido = $bus->recorridoEnCurso()->first()) {
            $verbo = $accion === 'eliminar' ? 'eliminarlo' : 'quitarlo o reasignarlo de su línea';
            throw new ReglaNegocioException("El bus {$bus->codigo} está en ruta (recorrido #{$recorrido->id} en curso). Finalice el recorrido antes de {$verbo}.");
        }
    }

    /**
     * Después de que un bus llega a una línea (o la línea pierde una estación): si recuperó el mínimo,
     * su alerta de insuficiencia se atiende sola.
     */
    public function revisarMinimoRecuperado(?Linea $linea): int
    {
        return $this->alertas->atenderInsuficienciaSiCubierta($linea);
    }

    /**
     * Revisar el mínimo después de que un bus deja una línea (reasignado, sin línea o eliminado).
     * La operación ya se guardó: si la línea quedó corta se crea una alerta 'insuficiencia_unidades'
     * (sin duplicar una activa) y se devuelve el aviso para mostrarlo al usuario.
     */
    public function revisarMinimo(?Linea $linea): ?string
    {
        if ($linea === null) {
            return null;
        }

        $estaciones = $linea->estaciones()->count();
        $buses = $linea->buses()->count();
        if ($buses >= $estaciones) {
            return null;
        }

        $faltan = $estaciones - $buses;
        $mensaje = "La línea {$linea->codigo} quedó con {$buses} ".($buses === 1 ? 'bus' : 'buses')
            ." y necesita al menos {$estaciones} (uno por estación). Faltan {$faltan}.";

        $this->alertas->registrarInsuficiencia($linea, $mensaje);

        return $mensaje;
    }

    /**
     * Agrega la estación a la línea en la posición indicada (al final si no se indica) y devuelve la posición.
     */
    public function agregarEstacion(Linea $linea, Estacion $estacion, float $distanciaKm, ?int $orden = null): int
    {
        return DB::transaction(function () use ($linea, $estacion, $distanciaKm, $orden) {
            $orden ??= $linea->estaciones()->count() + 1;
            // Recorre hacia atrás para no chocar con la llave única (linea_id, orden).
            DB::table('linea_estacion')
                ->where('linea_id', $linea->id)
                ->where('orden', '>=', $orden)
                ->orderByDesc('orden')
                ->increment('orden');

            $linea->estaciones()->attach($estacion->id, ['orden' => $orden, 'distancia_anterior_km' => $distanciaKm]);

            return $orden;
        });
    }

    /**
     * RF-03: mueve la estación a otra posición del recorrido y corre las demás. Devuelve el motivo si no se puede.
     * Mientras la línea tenga recorridos en curso no se cambia el orden (su "siguiente estación" depende de él).
     */
    public function moverEstacion(Linea $linea, Estacion $estacion, int $nueva): ?string
    {
        return DB::transaction(function () use ($linea, $estacion, $nueva) {
            Linea::whereKey($linea->id)->lockForUpdate()->first();
            $tramo = fn () => DB::table('linea_estacion')->where('linea_id', $linea->id);
            $actual = (int) $tramo()->where('estacion_id', $estacion->id)->value('orden');
            $total = $tramo()->count();
            if ($actual === 0 || $nueva < 1 || $nueva > $total || $nueva === $actual) {
                return $actual === 0 ? 'La estación no pertenece a esta línea.' : null;
            }
            if ($linea->recorridos()->where('estado', 'en_curso')->exists()) {
                return "No se cambia el orden de {$linea->codigo} mientras tenga recorridos en curso: finalícelos primero.";
            }

            // La estación pasa a una posición libre y las demás se corren sin chocar con la llave única (linea_id, orden).
            $tramo()->where('estacion_id', $estacion->id)->update(['orden' => $total + 1]);
            if ($nueva < $actual) {
                $tramo()->whereBetween('orden', [$nueva, $actual - 1])->orderByDesc('orden')->increment('orden');
            } else {
                $tramo()->whereBetween('orden', [$actual + 1, $nueva])->orderBy('orden')->decrement('orden');
            }
            $tramo()->where('estacion_id', $estacion->id)->update(['orden' => $nueva]);
            // La primera estación del recorrido no tiene distancia anterior.
            $tramo()->where('orden', 1)->update(['distancia_anterior_km' => 0]);

            return null;
        });
    }

    /**
     * Retira la estación de la línea y cierra el hueco en el orden. Devuelve el motivo si no se puede, o null.
     * RN-02: una estación no se queda sin línea, salvo cuando se está eliminando la estación.
     */
    public function retirarEstacion(Linea $linea, Estacion $estacion, bool $eliminandoEstacion = false): ?string
    {
        return DB::transaction(function () use ($linea, $estacion, $eliminandoEstacion) {
            // Bloquea la línea mientras se revisa el nuevo máximo de buses.
            Linea::whereKey($linea->id)->lockForUpdate()->first();
            $orden = DB::table('linea_estacion')->where('linea_id', $linea->id)->where('estacion_id', $estacion->id)->value('orden');
            if ($orden === null) {
                return null;
            }
            if (! $eliminandoEstacion && DB::table('linea_estacion')->where('estacion_id', $estacion->id)->count() === 1) {
                return "No se puede quitar {$estacion->nombre} de {$linea->codigo}: es su única línea y cada estación debe pertenecer "
                    .'al menos a una (RN-02). Agréguela antes a otra línea o elimine la estación.';
            }
            if ($motivo = $this->motivoParaNoQuitarEstacion($linea, $estacion)) {
                return $motivo;
            }

            $linea->estaciones()->detach($estacion->id);
            // Cierra el hueco en la numeración, de menor a mayor por la llave única.
            DB::table('linea_estacion')->where('linea_id', $linea->id)->where('orden', '>', $orden)->orderBy('orden')->decrement('orden');
            // La primera estación del recorrido no tiene distancia anterior.
            DB::table('linea_estacion')->where('linea_id', $linea->id)->where('orden', 1)->update(['distancia_anterior_km' => 0]);

            return null;
        });
    }

    /**
     * Motivo por el que no se puede quitar la estación de la línea, o null si se puede.
     * Con una estación menos el máximo baja a (estaciones - 1) × 2 y los buses asignados no deben superarlo.
     */
    public function motivoParaNoQuitarEstacion(Linea $linea, Estacion $estacion): ?string
    {
        $restantes = $linea->estaciones()->count() - 1;
        $maximo = $restantes * 2;
        $buses = $linea->buses()->count();

        if ($buses <= $maximo) {
            return null;
        }

        return "No se puede quitar {$estacion->nombre}: la línea {$linea->codigo} tiene {$buses} buses y con {$restantes} "
            .($restantes === 1 ? 'estación' : 'estaciones')." el máximo sería {$maximo} (estaciones × 2). Reasigne "
            .($buses - $maximo).' '.($buses - $maximo === 1 ? 'bus' : 'buses').' antes de quitarla.';
    }
}
