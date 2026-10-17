<?php

namespace App\Reportes;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Líneas con su resumen (vista vw_resumen_lineas): estaciones, distancia y buses contra el mínimo y el máximo.
 */
class LineasReporte extends Reporte
{
    public function clave(): string
    {
        return 'lineas';
    }

    public function titulo(): string
    {
        return 'Líneas';
    }

    public function descripcion(): string
    {
        return 'Estaciones, distancia y buses asignados contra el mínimo y el máximo.';
    }

    public function icono(): string
    {
        return 'lineas';
    }

    public function camposFiltro(User $usuario): array
    {
        return ['estado' => ['etiqueta' => 'Estado', 'vacio' => 'Todas', 'opciones' => ['cumple' => 'Cumple', 'faltan_buses' => 'Faltan buses']]];
    }

    public function columnas(array $filtros, User $usuario): array
    {
        return [
            'codigo' => ['Código', 'texto'],
            'nombre' => ['Nombre', 'texto'],
            'total_estaciones' => ['Estaciones', 'numero'],
            'distancia_total_km' => ['Distancia', 'km'],
            'buses_asignados' => ['Buses asignados', 'numero'],
            'buses_minimos' => ['Mínimo', 'numero'],
            'buses_maximos' => ['Máximo', 'numero'],
            'estado' => ['Estado', 'estado'],
        ];
    }

    public function consulta(array $filtros, User $usuario): Builder
    {
        return DB::table('vw_resumen_lineas as v')
            ->select('v.id', 'v.codigo', 'v.nombre', 'v.total_estaciones', 'v.distancia_total_km', 'v.buses_asignados', 'v.buses_minimos', 'v.buses_maximos')
            ->selectRaw("CASE WHEN v.buses_asignados >= v.buses_minimos THEN 'cumple' ELSE 'faltan_buses' END AS estado")
            ->when(! $usuario->esAdministrador(), fn ($q) => $q->whereIn('v.id', DB::table('linea_estacion')->where('estacion_id', $this->estacionDe($usuario))->select('linea_id')))
            ->when(($filtros['estado'] ?? null) === 'cumple', fn ($q) => $q->whereColumn('v.buses_asignados', '>=', 'v.buses_minimos'))
            ->when(($filtros['estado'] ?? null) === 'faltan_buses', fn ($q) => $q->whereColumn('v.buses_asignados', '<', 'v.buses_minimos'))
            ->orderBy('v.id');
    }

    public function totales(array $filtros, User $usuario): array
    {
        $t = $this->agregar($filtros, $usuario, "COUNT(*) AS lineas, SUM(total_estaciones) AS estaciones, SUM(distancia_total_km) AS km,
            SUM(buses_asignados) AS buses, SUM(estado = 'faltan_buses') AS faltan");

        return [
            ['Líneas', $t->lineas, 'numero'],
            ['Estaciones en las líneas', $t->estaciones ?? 0, 'numero'],
            ['Distancia total', $t->km ?? 0, 'km'],
            ['Buses asignados', $t->buses ?? 0, 'numero'],
            ['Líneas con faltante', $t->faltan ?? 0, 'numero'],
        ];
    }

    public function enlaces(Collection $filas, User $usuario): array
    {
        return $filas->mapWithKeys(fn ($f) => [$f->id => route('lineas.show', $f->id)])->all();
    }
}
