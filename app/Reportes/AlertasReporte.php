<?php

namespace App\Reportes;

use App\Models\Alerta;
use App\Models\Estacion;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Alertas por tipo, estado y fecha (de generación), con el tiempo de atención y un resumen por tipo.
 */
class AlertasReporte extends Reporte
{
    public function clave(): string
    {
        return 'alertas';
    }

    public function titulo(): string
    {
        return 'Alertas';
    }

    public function descripcion(): string
    {
        return 'Por tipo, estado y fecha, con tiempo promedio de atención.';
    }

    public function icono(): string
    {
        return 'alerta';
    }

    public function usaFechas(): bool
    {
        return true;
    }

    public function camposFiltro(User $usuario): array
    {
        return [
            'tipo' => ['etiqueta' => 'Tipo', 'vacio' => 'Todos', 'opciones' => Alerta::TIPOS],
            'estado' => ['etiqueta' => 'Estado', 'vacio' => 'Todos', 'opciones' => ['activa' => 'Activa', 'atendida' => 'Atendida']],
            ...($usuario->esAdministrador()
                ? ['estacion_id' => ['etiqueta' => 'Estación', 'vacio' => 'Todas', 'opciones' => Estacion::orderBy('nombre')->pluck('nombre', 'id')->all()]]
                : []),
        ];
    }

    public function columnas(array $filtros, User $usuario): array
    {
        return [
            'created_at' => ['Generada', 'fecha'],
            'tipo' => ['Tipo', 'texto'],
            'lugar' => ['Lugar', 'texto'],
            'mensaje' => ['Mensaje', 'texto', 'min-w-64'],
            'estado' => ['Estado', 'estado'],
            'atendida_en' => ['Atendida', 'fecha'],
            'atencion' => ['Tiempo de atención', 'minutos'],
        ];
    }

    public function consulta(array $filtros, User $usuario): Builder
    {
        return $this->enRango(DB::table('alertas as a'), 'a.created_at', $filtros)
            ->leftJoin('estaciones as e', 'e.id', '=', 'a.estacion_id')
            ->leftJoin('buses as b', 'b.id', '=', 'a.bus_id')
            ->leftJoin('lineas as l', 'l.id', '=', 'a.linea_id')
            ->select('a.id', 'a.created_at', 'a.tipo', 'a.mensaje', 'a.estado', 'a.atendida_en')
            ->selectRaw("CONCAT_WS(' · ', e.nombre, b.codigo, l.codigo) AS lugar")
            ->selectRaw('TIMESTAMPDIFF(MINUTE, a.created_at, a.atendida_en) AS atencion')
            ->when(! $usuario->esAdministrador(), fn ($q) => $q->where('a.estacion_id', $this->estacionDe($usuario)))
            ->when(isset($filtros['estacion_id']), fn ($q) => $q->where('a.estacion_id', $filtros['estacion_id']))
            ->when(isset($filtros['tipo']), fn ($q) => $q->where('a.tipo', $filtros['tipo']))
            ->when(isset($filtros['estado']), fn ($q) => $q->where('a.estado', $filtros['estado']))
            ->orderByDesc('a.id');
    }

    public function totales(array $filtros, User $usuario): array
    {
        $t = $this->agregar($filtros, $usuario, "COUNT(*) AS alertas, SUM(estado = 'activa') AS activas, SUM(estado = 'atendida') AS atendidas, AVG(atencion) AS atencion");

        return [
            ['Alertas', $t->alertas, 'numero'],
            ['Activas', $t->activas ?? 0, 'numero'],
            ['Atendidas', $t->atendidas ?? 0, 'numero'],
            ['Tiempo promedio de atención', $t->atencion, 'minutos'],
        ];
    }

    public function resumen(array $filtros, User $usuario): ?array
    {
        $filas = DB::query()->fromSub($this->consulta($filtros, $usuario)->reorder(), 't')
            ->groupBy('tipo')->orderByDesc('total')
            ->selectRaw("tipo, COUNT(*) AS total, SUM(estado = 'activa') AS activas, SUM(estado = 'atendida') AS atendidas, AVG(atencion) AS atencion")
            ->get()
            ->map(fn ($f) => [...(array) $f, 'tipo' => Alerta::TIPOS[$f->tipo] ?? $f->tipo]);

        return [
            'titulo' => 'Por tipo',
            'columnas' => [
                'tipo' => ['Tipo', 'texto'],
                'total' => ['Alertas', 'numero'],
                'activas' => ['Activas', 'numero'],
                'atendidas' => ['Atendidas', 'numero'],
                'atencion' => ['Tiempo promedio de atención', 'minutos'],
            ],
            'filas' => $filas->all(),
        ];
    }

    public function preparar(object $fila): object
    {
        $fila->tipo = Alerta::TIPOS[$fila->tipo] ?? $fila->tipo;

        return $fila;
    }

    public function enlaces(Collection $filas, User $usuario): array
    {
        return $filas->mapWithKeys(fn ($f) => [$f->id => route('alertas.show', $f->id)])->all();
    }
}
