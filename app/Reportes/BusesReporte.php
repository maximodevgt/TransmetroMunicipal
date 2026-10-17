<?php

namespace App\Reportes;

use App\Models\Bus;
use App\Models\Linea;
use App\Models\Parqueo;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Buses asignados a una línea, con su parqueo y el piloto de su último recorrido.
 * El operador ve los buses de las líneas que pasan por su estación.
 */
class BusesReporte extends Reporte
{
    public function clave(): string
    {
        return 'buses';
    }

    public function titulo(): string
    {
        return 'Buses asignados';
    }

    public function descripcion(): string
    {
        return 'Buses por línea con parqueo, capacidad, estado y piloto del último recorrido.';
    }

    public function icono(): string
    {
        return 'buses';
    }

    public function camposFiltro(User $usuario): array
    {
        return [
            'linea_id' => ['etiqueta' => 'Línea', 'vacio' => 'Todas', 'opciones' => Linea::query()
                ->when(! $usuario->esAdministrador(), fn ($q) => $q->whereIn('id', $this->lineasDeLaEstacion($usuario)))
                ->orderBy('id')->pluck('codigo', 'id')->all()],
            'parqueo_id' => ['etiqueta' => 'Parqueo', 'vacio' => 'Todos', 'opciones' => Parqueo::orderBy('nombre')->pluck('nombre', 'id')->all()],
            'estado' => ['etiqueta' => 'Estado', 'vacio' => 'Todos', 'opciones' => collect(Bus::ESTADOS)->mapWithKeys(fn ($e) => [$e => ucfirst(str_replace('_', ' ', $e))])->all()],
        ];
    }

    public function columnas(array $filtros, User $usuario): array
    {
        return [
            'linea' => ['Línea', 'texto'],
            'codigo' => ['Código', 'texto'],
            'placa' => ['Placa', 'texto'],
            'parqueo' => ['Parqueo', 'texto'],
            'capacidad_maxima' => ['Capacidad', 'numero'],
            'estado' => ['Estado', 'estado'],
            'piloto' => ['Piloto del último recorrido', 'texto'],
        ];
    }

    public function consulta(array $filtros, User $usuario): Builder
    {
        return DB::table('buses as b')
            ->join('lineas as l', 'l.id', '=', 'b.linea_id')
            ->join('parqueos as p', 'p.id', '=', 'b.parqueo_id')
            ->select('b.id', 'l.codigo as linea', 'b.codigo', 'b.placa', 'p.nombre as parqueo', 'b.capacidad_maxima', 'b.estado')
            ->selectSub(DB::table('recorridos as r')->join('pilotos as pi', 'pi.id', '=', 'r.piloto_id')
                ->whereColumn('r.bus_id', 'b.id')->orderByDesc('r.inicio')->orderByDesc('r.id')->limit(1)->select('pi.nombre'), 'piloto')
            ->when(! $usuario->esAdministrador(), fn ($q) => $q->whereIn('b.linea_id', $this->lineasDeLaEstacion($usuario)))
            ->when(isset($filtros['linea_id']), fn ($q) => $q->where('b.linea_id', $filtros['linea_id']))
            ->when(isset($filtros['parqueo_id']), fn ($q) => $q->where('b.parqueo_id', $filtros['parqueo_id']))
            ->when(isset($filtros['estado']), fn ($q) => $q->where('b.estado', $filtros['estado']))
            ->orderBy('l.id')
            ->orderBy('b.codigo');
    }

    public function totales(array $filtros, User $usuario): array
    {
        $t = $this->agregar($filtros, $usuario, "COUNT(*) AS buses, SUM(capacidad_maxima) AS capacidad,
            SUM(estado = 'en_ruta') AS en_ruta, SUM(estado = 'disponible') AS disponibles, SUM(estado = 'mantenimiento') AS mantenimiento");

        return [
            ['Buses', $t->buses, 'numero'],
            ['Capacidad total', $t->capacidad ?? 0, 'numero'],
            ['En ruta', $t->en_ruta ?? 0, 'numero'],
            ['Disponibles', $t->disponibles ?? 0, 'numero'],
            ['En mantenimiento', $t->mantenimiento ?? 0, 'numero'],
        ];
    }

    public function enlaces(Collection $filas, User $usuario): array
    {
        return $filas->mapWithKeys(fn ($f) => [$f->id => route('buses.show', $f->id)])->all();
    }

    private function lineasDeLaEstacion(User $usuario): Builder
    {
        return DB::table('linea_estacion')->where('estacion_id', $this->estacionDe($usuario))->select('linea_id');
    }
}
