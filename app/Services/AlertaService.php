<?php

namespace App\Services;

use App\Models\Alerta;
use App\Models\Bitacora;
use App\Models\Bus;
use App\Models\Estacion;
use App\Models\Linea;
use App\Models\RecorridoEstacion;
use App\Models\RegistroPasajeros;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Generación y atención de alertas. Nunca duplica una alerta activa del mismo tipo
 * para la misma estación (alta demanda), el mismo bus (baja ocupación) o la misma línea (insuficiencia).
 */
class AlertaService
{
    /** Pasajeros en espera desde los que hay alta demanda: 150 % de la capacidad de referencia. */
    public const ALTA_DEMANDA = 1.5;

    /** Un bus llega con baja ocupación por debajo del 25 % de su capacidad. */
    public const BAJA_OCUPACION = 0.25;

    public const ESPERA_EXTRA_MIN = 5;

    /**
     * Registra los pasajeros en espera de la estación y revisa la alta demanda.
     */
    public function registrarEspera(Estacion $estacion, User $usuario, int $cantidad): array
    {
        $registro = RegistroPasajeros::create([
            'estacion_id' => $estacion->id,
            'user_id' => $usuario->id,
            'cantidad' => $cantidad,
            'registrado_en' => now(),
        ]);
        Bitacora::registrar('registrar_espera', $registro, "{$cantidad} pasajeros en espera en la estación {$estacion->nombre}");

        return [$registro, $this->revisarAltaDemanda($estacion, $cantidad)];
    }

    /**
     * Capacidad de referencia de la estación: el bus en ruta más grande de sus líneas;
     * si ninguno está en ruta, el más grande asignado. Devuelve [capacidad, bus] o null si no hay buses.
     */
    public function capacidadReferencia(Estacion $estacion): ?array
    {
        $lineas = DB::table('linea_estacion')->where('estacion_id', $estacion->id)->pluck('linea_id');
        $base = fn () => Bus::whereIn('linea_id', $lineas)->orderByDesc('capacidad_maxima')->orderBy('id');

        $bus = $base()->whereHas('recorridoEnCurso')->first() ?? $base()->first();

        return $bus ? [$bus->capacidad_maxima, $bus] : null;
    }

    public function revisarAltaDemanda(Estacion $estacion, int $enEspera): ?Alerta
    {
        $referencia = $this->capacidadReferencia($estacion);
        if ($referencia === null) {
            return null;
        }
        [$capacidad, $bus] = $referencia;

        if ($enEspera < $capacidad * self::ALTA_DEMANDA) {
            return null;
        }

        $porcentaje = (int) round($enEspera / $capacidad * 100);

        return $this->crearSinDuplicar(
            ['estacion_id' => $estacion->id, 'tipo' => 'alta_demanda'],
            [
                'linea_id' => $bus->linea_id,
                'mensaje' => "Estación {$estacion->nombre} con {$enEspera} pasajeros en espera ({$porcentaje} % de {$capacidad}): enviar otro bus.",
            ],
            "Alerta de alta demanda en la estación {$estacion->nombre}",
        );
    }

    /**
     * Revisa la ocupación del bus al llegar a una estación. Con menos del 25 % se le indica esperar
     * 5 minutos adicionales. No aplica en la última estación de la línea, donde bajan todos los pasajeros.
     */
    public function revisarOcupacion(RecorridoEstacion $visita): ?Alerta
    {
        $recorrido = $visita->recorrido()->with(['bus', 'linea'])->first();
        $bus = $recorrido->bus;

        $ultimaDeLaLinea = (int) DB::table('linea_estacion')->where('linea_id', $recorrido->linea_id)->max('orden');
        $ordenVisita = (int) DB::table('linea_estacion')->where('linea_id', $recorrido->linea_id)->where('estacion_id', $visita->estacion_id)->value('orden');
        if ($ordenVisita === $ultimaDeLaLinea) {
            return null;
        }

        if ($visita->pasajeros_a_bordo >= $bus->capacidad_maxima * self::BAJA_OCUPACION) {
            return null;
        }

        $visita->update(['espera_extra_min' => self::ESPERA_EXTRA_MIN]);
        $porcentaje = (int) round($visita->pasajeros_a_bordo / $bus->capacidad_maxima * 100);
        $estacion = $visita->estacion;

        return $this->crearSinDuplicar(
            ['bus_id' => $bus->id, 'tipo' => 'baja_ocupacion'],
            [
                'estacion_id' => $estacion->id,
                'linea_id' => $recorrido->linea_id,
                'mensaje' => "{$bus->codigo} llegó a {$estacion->nombre} con {$visita->pasajeros_a_bordo} de {$bus->capacidad_maxima} pasajeros ({$porcentaje} %): esperar ".self::ESPERA_EXTRA_MIN.' minutos adicionales.',
            ],
            "Alerta de baja ocupación del bus {$bus->codigo}",
        );
    }

    public function registrarInsuficiencia(Linea $linea, string $mensaje): Alerta
    {
        return $this->crearSinDuplicar(
            ['linea_id' => $linea->id, 'tipo' => 'insuficiencia_unidades'],
            ['mensaje' => $mensaje],
            "Alerta de insuficiencia de unidades en la línea {$linea->codigo}",
        );
    }

    /**
     * Si la línea ya tiene al menos un bus por estación, atiende sus alertas de insuficiencia activas.
     */
    public function atenderInsuficienciaSiCubierta(?Linea $linea): int
    {
        if ($linea === null || $linea->buses()->count() < $linea->estaciones()->count()) {
            return 0;
        }

        $alertas = Alerta::where('linea_id', $linea->id)->where('tipo', 'insuficiencia_unidades')->where('estado', 'activa')->get();
        foreach ($alertas as $alerta) {
            $this->atender($alerta, "atendida automáticamente: la línea {$linea->codigo} recuperó el mínimo de buses");
        }

        return $alertas->count();
    }

    /**
     * Marca la alerta como atendida. Quién (usuario) y cuándo quedan en la bitácora junto con qué se hizo;
     * la tabla alertas solo guarda el estado y atendida_en.
     */
    public function atender(Alerta $alerta, ?string $queSeHizo = null): void
    {
        $alerta->update(['estado' => 'atendida', 'atendida_en' => now()]);

        $detalle = match (true) {
            $queSeHizo === null || trim($queSeHizo) === '' => "Alerta #{$alerta->id} atendida sin comentario",
            str_starts_with($queSeHizo, 'atendida') => "Alerta #{$alerta->id} {$queSeHizo}",
            default => "Alerta #{$alerta->id} atendida: ".trim($queSeHizo),
        };
        Bitacora::registrar('atender', $alerta, $detalle);
    }

    /**
     * El operador pide una unidad para una alerta de alta demanda de su estación: se genera una alerta
     * 'asignacion' que el administrador recibe en vivo (campana y toast). No se duplica si ya hay una activa.
     */
    public function solicitarUnidad(Alerta $alerta, User $operador): Alerta
    {
        $estacion = $alerta->estacion;
        $solicitud = $this->crearSinDuplicar(
            ['estacion_id' => $estacion->id, 'tipo' => 'asignacion'],
            [
                'linea_id' => $alerta->linea_id,
                'mensaje' => "{$operador->name} solicita una unidad para la estación {$estacion->nombre} (alerta #{$alerta->id} de alta demanda).",
            ],
            "Solicitud de unidad para la estación {$estacion->nombre}",
        );
        Bitacora::registrar('solicitar_unidad', $alerta, "Alerta #{$alerta->id}: {$operador->name} solicitó una unidad al administrador");

        return $solicitud;
    }

    /**
     * Crea la alerta si no hay una activa con la misma clave; si la hay, solo actualiza sus datos.
     */
    private function crearSinDuplicar(array $clave, array $datos, string $detalleBitacora): Alerta
    {
        $alerta = Alerta::where($clave)->where('estado', 'activa')->lockForUpdate()->first();
        if ($alerta) {
            $alerta->update($datos);

            return $alerta;
        }

        $alerta = Alerta::create([...$clave, ...$datos, 'estado' => 'activa']);
        Bitacora::registrar('crear', $alerta, $detalleBitacora);

        return $alerta;
    }
}
