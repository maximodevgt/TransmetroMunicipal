<?php

namespace App\Reportes;

use App\Models\Estacion;
use App\Models\Linea;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Estaciones: líneas y orden, accesos, guardias, operador, pasajeros en espera (último registro) y alertas activas.
 * Todo en una consulta con subconsultas por estación.
 */
class EstacionesReporte extends Reporte
{
    public function clave(): string
    {
        return 'estaciones';
    }

    public function titulo(): string
    {
        return 'Estaciones';
    }

    public function descripcion(): string
    {
        return 'Líneas, accesos, guardias, operador, pasajeros en espera y alertas activas.';
    }

    public function icono(): string
    {
        return 'estaciones';
    }

    public function camposFiltro(User $usuario): array
    {
        if (! $usuario->esAdministrador()) {
            return [];
        }

        return [
            'linea_id' => ['etiqueta' => 'Línea', 'vacio' => 'Todas', 'opciones' => Linea::orderBy('id')->pluck('codigo', 'id')->all()],
            'estacion_id' => ['etiqueta' => 'Estación', 'vacio' => 'Todas', 'opciones' => Estacion::orderBy('nombre')->pluck('nombre', 'id')->all()],
            'alertas' => ['etiqueta' => 'Alertas', 'vacio' => 'Todas', 'opciones' => ['con' => 'Con alertas activas']],
        ];
    }

    public function columnas(array $filtros, User $usuario): array
    {
        return [
            'nombre' => ['Estación', 'texto'],
            'lineas' => ['Líneas', 'texto'],
            'orden' => ['Orden', 'texto'],
            'accesos' => ['Accesos', 'numero'],
            'guardias' => ['Guardias', 'numero'],
            'operador' => ['Operador', 'texto'],
            'en_espera' => ['En espera', 'numero'],
            'alertas_activas' => ['Alertas activas', 'numero'],
        ];
    }

    public function consulta(array $filtros, User $usuario): Builder
    {
        $porLinea = fn (string $expresion) => DB::table('linea_estacion as le')->join('lineas as l', 'l.id', '=', 'le.linea_id')
            ->whereColumn('le.estacion_id', 'e.id')->selectRaw("GROUP_CONCAT({$expresion} ORDER BY l.id SEPARATOR ', ')");

        return DB::table('estaciones as e')
            ->select('e.id', 'e.nombre')
            ->selectSub($porLinea('l.codigo'), 'lineas')
            ->selectSub($porLinea('le.orden'), 'orden')
            ->selectSub(DB::table('accesos as a')->whereColumn('a.estacion_id', 'e.id')->selectRaw('COUNT(*)'), 'accesos')
            ->selectSub(DB::table('guardias as g')->join('accesos as a', 'a.id', '=', 'g.acceso_id')->whereColumn('a.estacion_id', 'e.id')->selectRaw('COUNT(*)'), 'guardias')
            ->selectSub(DB::table('users as u')->whereColumn('u.estacion_id', 'e.id')->where('u.rol', User::ROL_OPERADOR)->where('u.activo', true)
                ->selectRaw("GROUP_CONCAT(u.name ORDER BY u.name SEPARATOR ', ')"), 'operador')
            // Pasajeros en espera: el último registro de la estación.
            ->selectSub(DB::table('registros_pasajeros as rp')->whereColumn('rp.estacion_id', 'e.id')->orderByDesc('rp.id')->limit(1)->select('rp.cantidad'), 'en_espera')
            ->selectSub(DB::table('alertas as al')->whereColumn('al.estacion_id', 'e.id')->where('al.estado', 'activa')->selectRaw('COUNT(*)'), 'alertas_activas')
            ->when(! $usuario->esAdministrador(), fn ($q) => $q->where('e.id', $this->estacionDe($usuario)))
            ->when(isset($filtros['estacion_id']), fn ($q) => $q->where('e.id', $filtros['estacion_id']))
            ->when(($filtros['alertas'] ?? null) === 'con', fn ($q) => $q->whereExists(
                fn ($s) => $s->from('alertas as al2')->whereColumn('al2.estacion_id', 'e.id')->where('al2.estado', 'activa')
            ))
            // Con línea elegida, en el orden del recorrido de esa línea.
            ->when(isset($filtros['linea_id']), fn ($q) => $q
                ->join('linea_estacion as lf', fn ($j) => $j->on('lf.estacion_id', '=', 'e.id')->where('lf.linea_id', $filtros['linea_id']))
                ->orderBy('lf.orden'))
            ->orderBy('e.id');
    }

    public function totales(array $filtros, User $usuario): array
    {
        $t = $this->agregar($filtros, $usuario, 'COUNT(*) AS estaciones, SUM(accesos) AS accesos, SUM(guardias) AS guardias,
            SUM(en_espera) AS en_espera, SUM(alertas_activas) AS alertas');

        return [
            ['Estaciones', $t->estaciones, 'numero'],
            ['Accesos', $t->accesos ?? 0, 'numero'],
            ['Guardias', $t->guardias ?? 0, 'numero'],
            ['Pasajeros en espera', $t->en_espera ?? 0, 'numero'],
            ['Alertas activas', $t->alertas ?? 0, 'numero'],
        ];
    }

    public function enlaces(Collection $filas, User $usuario): array
    {
        return $filas->mapWithKeys(fn ($f) => [$f->id => route('estaciones.show', $f->id)])->all();
    }
}
