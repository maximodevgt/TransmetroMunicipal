<?php

namespace App\Reportes;

use App\Models\Estacion;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Recaudación de la Tarjeta Transmetro por día y/o estación (efectivo, en línea y pasajes), con los rechazos.
 * Los registros del script inicial no tienen estación ni medio de pago: cuentan la del acceso y como ventanilla.
 * "Recargado" es el dinero que entra; los pasajes gastan saldo ya pagado y no se suman.
 */
class PagosReporte extends Reporte
{
    public const AGRUPAR = ['dia' => 'Día', 'estacion' => 'Estación', 'dia_estacion' => 'Día y estación'];

    private const ESTACION = 'COALESCE(t.estacion_id, ac.estacion_id)';

    public function clave(): string
    {
        return 'pagos';
    }

    public function titulo(): string
    {
        return 'Tarjetas y pagos';
    }

    public function descripcion(): string
    {
        return 'Recaudación por día, estación y tipo, con los rechazos y su motivo.';
    }

    public function icono(): string
    {
        return 'tarjeta';
    }

    public function usaFechas(): bool
    {
        return true;
    }

    public function camposFiltro(User $usuario): array
    {
        return [
            'agrupar' => ['etiqueta' => 'Agrupar por', 'predeterminado' => 'dia', 'opciones' => self::AGRUPAR],
            ...($usuario->esAdministrador()
                ? ['estacion_id' => ['etiqueta' => 'Estación', 'vacio' => 'Todas', 'opciones' => ['en_linea' => 'En línea (sin estación)']
                    + Estacion::orderBy('nombre')->pluck('nombre', 'id')->all()]]
                : []),
        ];
    }

    public function columnas(array $filtros, User $usuario): array
    {
        $grupo = match ($filtros['agrupar']) {
            'estacion' => ['estacion' => ['Estación', 'texto']],
            'dia_estacion' => ['dia' => ['Día', 'dia'], 'estacion' => ['Estación', 'texto']],
            default => ['dia' => ['Día', 'dia']],
        };

        return [
            ...$grupo,
            'efectivo' => ['Recargas en efectivo', 'dinero'],
            'en_linea' => ['Recargas en línea', 'dinero'],
            'recargado' => ['Total recargado', 'dinero'],
            'pasajes' => ['Pasajes', 'numero'],
            'pasajes_monto' => ['Cobrado en pasajes', 'dinero'],
            'rechazos' => ['Rechazos', 'numero'],
        ];
    }

    /** Movimientos del rango y del alcance del usuario, sin agrupar. */
    private function movimientos(array $filtros, User $usuario): Builder
    {
        $estacion = $filtros['estacion_id'] ?? null;

        return $this->enRango(DB::table('transacciones as t'), 't.created_at', $filtros)
            ->leftJoin('accesos as ac', 'ac.id', '=', 't.acceso_id')
            ->leftJoin('estaciones as e', 'e.id', '=', DB::raw(self::ESTACION))
            ->when(! $usuario->esAdministrador(), fn ($q) => $q->whereRaw(self::ESTACION.' = ?', [$this->estacionDe($usuario)]))
            ->when($estacion === 'en_linea', fn ($q) => $q->whereRaw(self::ESTACION.' IS NULL'))
            ->when($estacion !== null && $estacion !== 'en_linea', fn ($q) => $q->whereRaw(self::ESTACION.' = ?', [(int) $estacion]));
    }

    public function consulta(array $filtros, User $usuario): Builder
    {
        $porDia = $filtros['agrupar'] !== 'estacion';
        $porEstacion = $filtros['agrupar'] !== 'dia';

        return $this->movimientos($filtros, $usuario)
            ->when($porDia, fn ($q) => $q->selectRaw('DATE(t.created_at) AS dia')->groupBy('dia')->orderByDesc('dia'))
            ->when($porEstacion, fn ($q) => $q->selectRaw("COALESCE(e.nombre, IF(t.metodo = 'tarjeta', 'En línea', 'Sin estación')) AS estacion")
                ->groupBy('estacion')->orderBy('estacion'))
            ->selectRaw("SUM(CASE WHEN t.estado = 'aprobada' AND t.tipo = 'recarga' AND COALESCE(t.metodo, 'efectivo') = 'efectivo' THEN t.monto ELSE 0 END) AS efectivo")
            ->selectRaw("SUM(CASE WHEN t.estado = 'aprobada' AND t.tipo = 'recarga' AND t.metodo = 'tarjeta' THEN t.monto ELSE 0 END) AS en_linea")
            ->selectRaw("SUM(CASE WHEN t.estado = 'aprobada' AND t.tipo = 'recarga' THEN t.monto ELSE 0 END) AS recargado")
            ->selectRaw("SUM(t.estado = 'aprobada' AND t.tipo = 'pago') AS pasajes")
            ->selectRaw("SUM(CASE WHEN t.estado = 'aprobada' AND t.tipo = 'pago' THEN t.monto ELSE 0 END) AS pasajes_monto")
            ->selectRaw("SUM(t.estado = 'rechazada') AS rechazos");
    }

    public function totales(array $filtros, User $usuario): array
    {
        $t = $this->agregar($filtros, $usuario, 'SUM(efectivo) AS efectivo, SUM(en_linea) AS en_linea, SUM(recargado) AS recargado,
            SUM(pasajes) AS pasajes, SUM(pasajes_monto) AS pasajes_monto, SUM(rechazos) AS rechazos');

        return [
            ['Recargas en efectivo', $t->efectivo ?? 0, 'dinero'],
            ['Recargas en línea', $t->en_linea ?? 0, 'dinero'],
            ['Total recargado', $t->recargado ?? 0, 'dinero'],
            ['Pasajes cobrados', $t->pasajes ?? 0, 'numero'],
            ['Cobrado en pasajes', $t->pasajes_monto ?? 0, 'dinero'],
            ['Rechazos', $t->rechazos ?? 0, 'numero'],
        ];
    }

    public function resumen(array $filtros, User $usuario): ?array
    {
        $filas = $this->movimientos($filtros, $usuario)
            ->where('t.estado', 'rechazada')
            ->selectRaw("COALESCE(t.motivo_rechazo, 'Sin motivo') AS motivo, t.tipo, COUNT(*) AS cantidad, SUM(t.monto) AS monto")
            ->groupBy('motivo', 't.tipo')
            ->orderByDesc('cantidad')
            ->get()
            ->map(fn ($f) => [...(array) $f, 'tipo' => $f->tipo === 'pago' ? 'Pasaje' : 'Recarga']);

        return [
            'titulo' => 'Rechazos por motivo',
            'columnas' => [
                'motivo' => ['Motivo', 'texto'],
                'tipo' => ['Tipo', 'texto'],
                'cantidad' => ['Rechazos', 'numero'],
                'monto' => ['Monto no cobrado', 'dinero'],
            ],
            'filas' => $filas->all(),
        ];
    }
}
