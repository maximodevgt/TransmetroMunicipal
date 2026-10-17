<?php

namespace App\Reportes;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pilotos con su historial educativo y los recorridos y kilómetros del rango de fechas.
 * Kilómetros: suma de la distancia_anterior_km de las estaciones visitadas en la línea del recorrido.
 * El operador ve a los pilotos que pasaron por su estación, sin DPI ni teléfono.
 */
class PilotosReporte extends Reporte
{
    public function clave(): string
    {
        return 'pilotos';
    }

    public function titulo(): string
    {
        return 'Pilotos';
    }

    public function descripcion(): string
    {
        return 'Datos, historial educativo, recorridos y kilómetros en el rango de fechas.';
    }

    public function icono(): string
    {
        return 'pilotos';
    }

    public function usaFechas(): bool
    {
        return true;
    }

    public function camposFiltro(User $usuario): array
    {
        return ['estado' => ['etiqueta' => 'Estado', 'vacio' => 'Todos', 'opciones' => ['activo' => 'Activo', 'inactivo' => 'Inactivo']]];
    }

    public function columnas(array $filtros, User $usuario): array
    {
        return [
            'nombre' => ['Piloto', 'texto'],
            ...($usuario->esAdministrador() ? ['dpi' => ['DPI', 'texto']] : []),
            'licencia' => ['Licencia', 'texto'],
            ...($usuario->esAdministrador() ? ['telefono' => ['Teléfono', 'texto']] : []),
            'estado' => ['Estado', 'estado'],
            'historial' => ['Historial educativo', 'texto', 'min-w-64'],
            'recorridos' => ['Recorridos', 'numero'],
            'km' => ['Kilómetros', 'km'],
        ];
    }

    public function consulta(array $filtros, User $usuario): Builder
    {
        $actividad = $this->enRango(DB::table('recorridos as r'), 'r.inicio', $filtros)
            ->leftJoin('recorrido_estaciones as re', 're.recorrido_id', '=', 'r.id')
            ->leftJoin('linea_estacion as le', fn ($j) => $j->on('le.linea_id', '=', 'r.linea_id')->on('le.estacion_id', '=', 're.estacion_id'))
            ->when(! $usuario->esAdministrador(), fn ($q) => $q->whereExists(
                fn ($s) => $s->from('recorrido_estaciones as v')->whereColumn('v.recorrido_id', 'r.id')->where('v.estacion_id', $this->estacionDe($usuario))
            ))
            ->groupBy('r.piloto_id')
            ->selectRaw('r.piloto_id, COUNT(DISTINCT r.id) AS recorridos, COALESCE(SUM(le.distancia_anterior_km), 0) AS km');

        $historial = DB::table('historial_educativo as h')->whereColumn('h.piloto_id', 'p.id')->selectRaw(
            "GROUP_CONCAT(CONCAT(ELT(FIELD(h.nivel, 'primaria', 'basico', 'diversificado', 'universitario', 'otro'),
                'Primaria', 'Básico', 'Diversificado', 'Universitario', 'Otro'), ': ', COALESCE(h.titulo, h.institucion),
                IF(h.anio IS NULL, '', CONCAT(' (', h.anio, ')'))) ORDER BY h.anio SEPARATOR '; ')"
        );

        return DB::table('pilotos as p')
            ->leftJoinSub($actividad, 'a', 'a.piloto_id', '=', 'p.id')
            ->select('p.id', 'p.nombre', 'p.dpi', 'p.licencia', 'p.telefono', 'p.estado')
            ->selectSub($historial, 'historial')
            ->selectRaw('COALESCE(a.recorridos, 0) AS recorridos, COALESCE(a.km, 0) AS km')
            ->when(! $usuario->esAdministrador(), fn ($q) => $q->whereNotNull('a.piloto_id'))
            ->when(isset($filtros['estado']), fn ($q) => $q->where('p.estado', $filtros['estado']))
            ->orderBy('p.nombre');
    }

    public function totales(array $filtros, User $usuario): array
    {
        $t = $this->agregar($filtros, $usuario, 'COUNT(*) AS pilotos, SUM(recorridos > 0) AS con_recorridos, SUM(recorridos) AS recorridos, SUM(km) AS km');

        return [
            ['Pilotos', $t->pilotos, 'numero'],
            ['Con recorridos', $t->con_recorridos ?? 0, 'numero'],
            ['Recorridos', $t->recorridos ?? 0, 'numero'],
            ['Kilómetros', $t->km ?? 0, 'km'],
        ];
    }

    public function enlaces(Collection $filas, User $usuario): array
    {
        // La ficha del piloto es solo del administrador.
        return $usuario->esAdministrador() ? $filas->mapWithKeys(fn ($f) => [$f->id => route('pilotos.show', $f->id)])->all() : [];
    }
}
