<?php

namespace App\Services;

use App\Models\Alerta;
use App\Models\User;
use App\Reportes\AlertasReporte;
use App\Reportes\EstacionesReporte;
use App\Reportes\PagosReporte;
use App\Reportes\RecorridosReporte;
use App\Reportes\Reporte;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Datos de las gráficas del panel. Cada gráfica sale de la consulta de su reporte, así los números
 * y el alcance del operador son los mismos que verá al hacer clic (cada punto lleva al reporte filtrado).
 *
 * Formato de cada gráfica: tipo, etiquetas, series [{nombre, datos, enlaces}], vacio (mensaje sin datos).
 */
class GraficasService
{
    public function para(User $usuario): array
    {
        return [
            'espera' => $this->espera($usuario),
            'recorridos_hoy' => $this->recorridosPorHora($usuario),
            'alertas_semana' => $this->alertasPorTipo($usuario),
            'recaudacion_semana' => $this->recaudacion($usuario),
            'ocupacion_lineas' => $this->ocupacionPorLinea($usuario),
        ];
    }

    /** Pasajeros en espera (último registro) de las 10 estaciones con más. */
    private function espera(User $usuario): array
    {
        $filas = (new EstacionesReporte)->consulta([], $usuario)->reorder()
            ->having('en_espera', '>', 0)->orderByDesc('en_espera')->orderBy('e.nombre')->limit(10)->get();

        return $this->grafica('bar', $filas->pluck('nombre'), [[
            'nombre' => 'Pasajeros en espera',
            'datos' => $filas->pluck('en_espera')->map(fn ($v) => (int) $v),
            'enlaces' => $filas->map(fn ($f) => route('reportes.show', ['estaciones', ...($usuario->esAdministrador() ? ['estacion_id' => $f->id] : [])])),
        ]], 'No hay pasajeros en espera registrados.');
    }

    /** Recorridos iniciados hoy, por hora. */
    private function recorridosPorHora(User $usuario): array
    {
        $porHora = DB::query()->fromSub((new RecorridosReporte)->consulta($this->rango('hoy'), $usuario)->reorder(), 'r')
            ->selectRaw('HOUR(inicio) AS hora, COUNT(*) AS total')->groupBy('hora')->pluck('total', 'hora');
        $horas = collect(range(0, 23));

        return $this->grafica('line', $horas->map(fn ($h) => sprintf('%02d:00', $h)), [[
            'nombre' => 'Recorridos',
            'datos' => $horas->map(fn ($h) => (int) ($porHora[$h] ?? 0)),
            'enlaces' => $horas->map(fn ($h) => route('reportes.show', ['recorridos', 'periodo' => 'hoy', 'hora' => $h])),
        ]], 'Hoy todavía no hay recorridos.', $porHora->isEmpty());
    }

    /** Alertas generadas en los últimos 7 días, por día y tipo (barras apiladas). */
    private function alertasPorTipo(User $usuario): array
    {
        $dias = $this->dias();
        $conteo = DB::query()->fromSub((new AlertasReporte)->consulta($this->rango('7dias'), $usuario)->reorder(), 'a')
            ->selectRaw('DATE(created_at) AS dia, tipo, COUNT(*) AS total')->groupBy('dia', 'tipo')->get()
            ->groupBy('tipo')->map(fn ($filas) => $filas->pluck('total', 'dia'));

        $series = collect(Alerta::TIPOS)->filter(fn ($texto, $tipo) => $conteo->has($tipo))->map(fn ($texto, $tipo) => [
            'nombre' => $texto,
            'datos' => $dias->map(fn (Carbon $d) => (int) ($conteo[$tipo][$d->format('Y-m-d')] ?? 0)),
            'enlaces' => $dias->map(fn (Carbon $d) => route('reportes.show', ['alertas', 'tipo' => $tipo, 'desde' => $d->format('Y-m-d'), 'hasta' => $d->format('Y-m-d')])),
        ])->values();

        return $this->grafica('bar', $dias->map(fn ($d) => $d->format('d/m')), $series->all(), 'Sin alertas en los últimos 7 días.', $series->isEmpty());
    }

    /** Recargas y pasajes de los últimos 7 días, por día. */
    private function recaudacion(User $usuario): array
    {
        $dias = $this->dias();
        $filas = (new PagosReporte)->consulta([...$this->rango('7dias'), 'agrupar' => 'dia'], $usuario)->get()->keyBy('dia');
        $serie = fn (string $nombre, string $campo) => [
            'nombre' => $nombre,
            'datos' => $dias->map(fn (Carbon $d) => round((float) ($filas[$d->format('Y-m-d')]->{$campo} ?? 0), 2)),
            'enlaces' => $dias->map(fn (Carbon $d) => route('reportes.show', ['pagos', 'desde' => $d->format('Y-m-d'), 'hasta' => $d->format('Y-m-d')])),
        ];
        $hayDatos = $filas->contains(fn ($f) => $f->recargado > 0 || $f->pasajes_monto > 0);

        return $this->grafica('line', $dias->map(fn ($d) => $d->format('d/m')), [
            $serie('Recargas', 'recargado'),
            $serie('Pasajes', 'pasajes_monto'),
        ], 'Sin recargas ni pasajes en los últimos 7 días.', ! $hayDatos);
    }

    /** Ocupación promedio de los buses de hoy por línea (pasajeros a bordo contra la capacidad). */
    private function ocupacionPorLinea(User $usuario): array
    {
        $filas = DB::query()->fromSub((new RecorridosReporte)->consulta($this->rango('hoy'), $usuario)->reorder(), 'r')
            ->whereNotNull('ocupacion')
            ->selectRaw('linea_id, linea, ROUND(AVG(ocupacion), 1) AS ocupacion')->groupBy('linea_id', 'linea')->orderBy('linea_id')->get();

        return $this->grafica('doughnut', $filas->pluck('linea'), [[
            'nombre' => 'Ocupación promedio (%)',
            'datos' => $filas->pluck('ocupacion')->map(fn ($v) => (float) $v),
            'enlaces' => $filas->map(fn ($f) => route('reportes.show', ['recorridos', 'periodo' => 'hoy', 'linea_id' => $f->linea_id])),
        ]], 'Hoy todavía no hay llegadas registradas.');
    }

    private function grafica(string $tipo, $etiquetas, array $series, string $vacio, ?bool $sinDatos = null): array
    {
        $series = array_map(fn ($s) => ['nombre' => $s['nombre'], 'datos' => collect($s['datos'])->values()->all(), 'enlaces' => collect($s['enlaces'])->values()->all()], $series);

        return [
            'tipo' => $tipo,
            'etiquetas' => collect($etiquetas)->values()->all(),
            'series' => $series,
            'hay_datos' => ! ($sinDatos ?? collect($etiquetas)->isEmpty()),
            'vacio' => $vacio,
        ];
    }

    /** Filtros de fecha como los que arma un reporte con ese atajo. */
    private function rango(string $periodo): array
    {
        [$desde, $hasta] = Reporte::rangoDe($periodo);

        return ['periodo' => $periodo, 'desde' => $desde, 'hasta' => $hasta];
    }

    private function dias()
    {
        return collect(range(6, 0))->map(fn ($n) => today()->subDays($n));
    }
}
