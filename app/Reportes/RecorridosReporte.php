<?php

namespace App\Reportes;

use App\Models\Linea;
use App\Models\Recorrido;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Recorridos del rango de fechas: duración, estaciones visitadas y ocupación promedio
 * (pasajeros a bordo al llegar a cada estación contra la capacidad del bus).
 * El operador ve los recorridos que pasaron por su estación.
 */
class RecorridosReporte extends Reporte
{
    public function clave(): string
    {
        return 'recorridos';
    }

    public function titulo(): string
    {
        return 'Recorridos';
    }

    public function descripcion(): string
    {
        return 'Por fechas y línea, con duración, estaciones visitadas y ocupación promedio.';
    }

    public function icono(): string
    {
        return 'recorridos';
    }

    public function usaFechas(): bool
    {
        return true;
    }

    public function camposFiltro(User $usuario): array
    {
        return [
            'linea_id' => ['etiqueta' => 'Línea', 'vacio' => 'Todas', 'opciones' => Linea::orderBy('id')->pluck('codigo', 'id')->all()],
            'estado' => ['etiqueta' => 'Estado', 'vacio' => 'Todos', 'opciones' => Recorrido::ESTADOS],
            'hora' => ['etiqueta' => 'Hora de inicio', 'vacio' => 'Todas', 'opciones' => collect(range(0, 23))
                ->mapWithKeys(fn ($h) => [$h => sprintf('%02d:00 – %02d:59', $h, $h)])->all()],
        ];
    }

    public function columnas(array $filtros, User $usuario): array
    {
        return [
            'id' => ['N.º', 'texto'],
            'inicio' => ['Inicio', 'fecha'],
            'linea' => ['Línea', 'texto'],
            'bus' => ['Bus', 'texto'],
            'piloto' => ['Piloto', 'texto'],
            'estado' => ['Estado', 'estado'],
            'duracion' => ['Duración', 'minutos'],
            'estaciones' => ['Estaciones visitadas', 'numero'],
            'ocupacion' => ['Ocupación promedio', 'porcentaje'],
        ];
    }

    public function consulta(array $filtros, User $usuario): Builder
    {
        $visitas = DB::table('recorrido_estaciones')->groupBy('recorrido_id')
            ->selectRaw('recorrido_id, COUNT(*) AS estaciones, AVG(pasajeros_a_bordo) AS pasajeros');

        return $this->enRango(DB::table('recorridos as r'), 'r.inicio', $filtros)
            ->join('lineas as l', 'l.id', '=', 'r.linea_id')
            ->join('buses as b', 'b.id', '=', 'r.bus_id')
            ->join('pilotos as p', 'p.id', '=', 'r.piloto_id')
            ->leftJoinSub($visitas, 'v', 'v.recorrido_id', '=', 'r.id')
            ->select('r.id', 'r.inicio', 'r.linea_id', 'l.codigo as linea', 'b.codigo as bus', 'p.nombre as piloto', 'r.estado')
            // Los que siguen en curso cuentan hasta ahora.
            ->selectRaw('TIMESTAMPDIFF(MINUTE, r.inicio, COALESCE(r.fin, ?)) AS duracion', [now()])
            ->selectRaw('COALESCE(v.estaciones, 0) AS estaciones')
            ->selectRaw('ROUND(v.pasajeros / b.capacidad_maxima * 100, 1) AS ocupacion')
            ->when(! $usuario->esAdministrador(), fn ($q) => $q->whereExists(
                fn ($s) => $s->from('recorrido_estaciones as ve')->whereColumn('ve.recorrido_id', 'r.id')->where('ve.estacion_id', $this->estacionDe($usuario))
            ))
            ->when(isset($filtros['linea_id']), fn ($q) => $q->where('r.linea_id', $filtros['linea_id']))
            ->when(isset($filtros['estado']), fn ($q) => $q->where('r.estado', $filtros['estado']))
            ->when(isset($filtros['hora']), fn ($q) => $q->whereRaw('HOUR(r.inicio) = ?', [(int) $filtros['hora']]))
            ->orderByDesc('r.inicio')
            ->orderByDesc('r.id');
    }

    public function totales(array $filtros, User $usuario): array
    {
        $t = $this->agregar($filtros, $usuario, "COUNT(*) AS recorridos, SUM(estado = 'finalizado') AS finalizados,
            AVG(CASE WHEN estado = 'finalizado' THEN duracion END) AS duracion, SUM(estaciones) AS estaciones, AVG(ocupacion) AS ocupacion");

        return [
            ['Recorridos', $t->recorridos, 'numero'],
            ['Finalizados', $t->finalizados ?? 0, 'numero'],
            ['Duración promedio (finalizados)', $t->duracion, 'minutos'],
            ['Estaciones visitadas', $t->estaciones ?? 0, 'numero'],
            ['Ocupación promedio', $t->ocupacion, 'porcentaje'],
        ];
    }

    public function enlaces(Collection $filas, User $usuario): array
    {
        return $filas->mapWithKeys(fn ($f) => [$f->id => route('recorridos.show', $f->id)])->all();
    }
}
