<?php

namespace App\Reportes;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Un reporte: sus filtros, una consulta agregada en SQL (sin N+1), los totales y, si aplica,
 * un resumen agrupado. La misma consulta alimenta la pantalla (paginada), la impresión y el CSV,
 * así los totales siempre cuadran con la tabla.
 *
 * Tipos de columna: texto, numero, dinero, km, porcentaje, minutos, fecha, dia y estado.
 */
abstract class Reporte
{
    public const PERIODOS = ['hoy' => 'Hoy', '7dias' => '7 días', 'mes' => 'Este mes'];

    public const POR_PAGINA = 25;

    /** Opciones de los filtros, calculadas una vez por petición. */
    private ?array $campos = null;

    abstract public function clave(): string;

    abstract public function titulo(): string;

    abstract public function descripcion(): string;

    abstract public function icono(): string;

    /**
     * Columnas de la tabla: clave del campo => [etiqueta, tipo].
     *
     * @return array<string, array{0: string, 1: string}>
     */
    abstract public function columnas(array $filtros, User $usuario): array;

    /**
     * Filas del reporte, ya filtradas y ordenadas.
     */
    abstract public function consulta(array $filtros, User $usuario): Builder;

    /**
     * Tarjetas de totales: lista de [etiqueta, valor, tipo].
     *
     * @return list<array{0: string, 1: mixed, 2: string}>
     */
    abstract public function totales(array $filtros, User $usuario): array;

    /** Si el reporte se filtra por un rango de fechas. */
    public function usaFechas(): bool
    {
        return false;
    }

    /**
     * Filtros propios: nombre => ['etiqueta' => ..., 'opciones' => [valor => texto], 'vacio' => 'Todas'].
     * Sin 'vacio', el filtro siempre tiene valor y toma 'predeterminado'.
     */
    public function camposFiltro(User $usuario): array
    {
        return [];
    }

    public function campos(User $usuario): array
    {
        return $this->campos ??= $this->camposFiltro($usuario);
    }

    /**
     * Tabla agrupada debajo de la principal: ['titulo' => ..., 'columnas' => [...], 'filas' => [...]].
     */
    public function resumen(array $filtros, User $usuario): ?array
    {
        return null;
    }

    /**
     * Enlace de cada fila (id => url) calculado de una vez para todas las filas.
     *
     * @return array<int|string, string|null>
     */
    public function enlaces(Collection $filas, User $usuario): array
    {
        return [];
    }

    /** Ajuste de cada fila antes de mostrarla (por ejemplo, el texto de un tipo). */
    public function preparar(object $fila): object
    {
        return $fila;
    }

    /**
     * Lee y valida los filtros de la URL: lo que no es una opción válida se ignora.
     */
    public function filtros(Request $request, User $usuario): array
    {
        $filtros = [];
        foreach ($this->campos($usuario) as $nombre => $campo) {
            $valor = (string) $request->query($nombre, '');
            if ($valor !== '' && array_key_exists($valor, $campo['opciones'])) {
                $filtros[$nombre] = $valor;
            } elseif (! isset($campo['vacio'])) {
                $filtros[$nombre] = $campo['predeterminado'];
            }
        }

        return $this->usaFechas() ? [...$filtros, ...$this->rango($request)] : $filtros;
    }

    /**
     * Rango de fechas: desde/hasta explícitos o un atajo (hoy, 7 días, este mes). Por omisión, este mes.
     */
    private function rango(Request $request): array
    {
        $leer = function (string $campo) use ($request): ?Carbon {
            $texto = (string) $request->query($campo, '');
            $fecha = rescue(fn () => $texto !== '' ? Carbon::createFromFormat('!Y-m-d', $texto) : null, null, false);

            return $fecha && $fecha->format('Y-m-d') === $texto ? $fecha : null;
        };
        $desde = $leer('desde');
        $hasta = $leer('hasta');

        if ($desde === null && $hasta === null) {
            $periodo = array_key_exists((string) $request->query('periodo'), self::PERIODOS) ? $request->query('periodo') : 'mes';
            [$desde, $hasta] = self::rangoDe($periodo);

            return ['periodo' => $periodo, 'desde' => $desde, 'hasta' => $hasta];
        }

        $hasta ??= today();
        $desde ??= $hasta->copy()->startOfMonth();
        if ($desde->gt($hasta)) {
            [$desde, $hasta] = [$hasta, $desde];
        }
        // Si las fechas coinciden con un atajo, se marca ese atajo.
        $periodo = collect(array_keys(self::PERIODOS))
            ->first(fn ($p) => self::rangoDe($p)[0]->eq($desde) && self::rangoDe($p)[1]->eq($hasta)) ?? 'rango';

        return ['periodo' => $periodo, 'desde' => $desde, 'hasta' => $hasta];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public static function rangoDe(string $periodo): array
    {
        return match ($periodo) {
            'hoy' => [today(), today()],
            '7dias' => [today()->subDays(6), today()],
            default => [today()->startOfMonth(), today()],
        };
    }

    /** Limita una columna de fecha al rango del filtro (días completos). */
    protected function enRango(Builder $query, string $columna, array $filtros): Builder
    {
        return $query->whereBetween($columna, [$filtros['desde']->copy()->startOfDay(), $filtros['hasta']->copy()->endOfDay()]);
    }

    /** Estación del operador; 0 si no tiene, para que no vea nada en vez de ver lo que no tiene estación. */
    protected function estacionDe(User $usuario): int
    {
        return (int) $usuario->estacion_id;
    }

    /** Agrega sobre las mismas filas de la tabla: los totales cuadran por construcción. */
    protected function agregar(array $filtros, User $usuario, string $select): object
    {
        return DB::query()->fromSub($this->consulta($filtros, $usuario)->reorder(), 't')->selectRaw($select)->first();
    }

    /** Filtros aplicados en una línea, para la impresión y la bitácora. */
    public function describirFiltros(array $filtros, User $usuario): string
    {
        $partes = [];
        if ($this->usaFechas()) {
            $partes[] = $filtros['desde']->eq($filtros['hasta'])
                ? 'Fecha: '.$filtros['desde']->format('d/m/Y')
                : 'Del '.$filtros['desde']->format('d/m/Y').' al '.$filtros['hasta']->format('d/m/Y');
        }
        foreach ($this->campos($usuario) as $nombre => $campo) {
            if (isset($filtros[$nombre])) {
                $partes[] = $campo['etiqueta'].': '.$campo['opciones'][$filtros[$nombre]];
            }
        }
        if (! $usuario->esAdministrador()) {
            $partes[] = 'Estación: '.($usuario->estacion?->nombre ?? 'sin estación asignada');
        }

        return $partes ? implode(' · ', $partes) : 'Sin filtros';
    }

    /** Parámetros de la URL que reproducen los filtros (para paginar, imprimir y exportar). */
    public function parametros(array $filtros): array
    {
        $parametros = collect($filtros)->except(['periodo', 'desde', 'hasta'])->all();
        if ($this->usaFechas()) {
            $parametros += $filtros['periodo'] === 'rango'
                ? ['desde' => $filtros['desde']->format('Y-m-d'), 'hasta' => $filtros['hasta']->format('Y-m-d')]
                : ['periodo' => $filtros['periodo']];
        }

        return $parametros;
    }

    public static function formatear(mixed $valor, string $tipo): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        return match ($tipo) {
            'numero' => number_format((float) $valor),
            'dinero' => 'Q'.number_format((float) $valor, 2),
            'km' => number_format((float) $valor, 2).' km',
            'porcentaje' => number_format((float) $valor, 1).' %',
            'minutos' => self::duracion((int) round((float) $valor)),
            'fecha' => Carbon::parse($valor)->format('d/m/Y H:i'),
            'dia' => Carbon::parse($valor)->format('d/m/Y'),
            'estado' => ucfirst(str_replace('_', ' ', (string) $valor)),
            default => (string) $valor,
        };
    }

    /** Valor para el CSV: números sin formato (punto decimal) para que Excel los sume. */
    public static function valorCsv(mixed $valor, string $tipo): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        return match ($tipo) {
            'numero', 'minutos' => (string) (int) round((float) $valor),
            'dinero', 'km' => number_format((float) $valor, 2, '.', ''),
            'porcentaje' => number_format((float) $valor, 1, '.', ''),
            'texto' => (string) $valor,
            default => self::formatear($valor, $tipo),
        };
    }

    /** Encabezado del CSV: la unidad va en el título porque el valor va sin formato. */
    public static function encabezadoCsv(string $etiqueta, string $tipo): string
    {
        $unidad = ['dinero' => 'Q', 'km' => 'km', 'porcentaje' => '%', 'minutos' => 'min'][$tipo] ?? null;

        return $unidad ? "{$etiqueta} ({$unidad})" : $etiqueta;
    }

    private static function duracion(int $minutos): string
    {
        return $minutos < 60 ? "{$minutos} min" : intdiv($minutos, 60).' h '.str_pad((string) ($minutos % 60), 2, '0', STR_PAD_LEFT).' min';
    }
}
