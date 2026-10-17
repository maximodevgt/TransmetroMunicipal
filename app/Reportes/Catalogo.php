<?php

namespace App\Reportes;

use Illuminate\Support\Collection;

/**
 * Reportes disponibles, en el orden en que aparecen en el índice.
 */
class Catalogo
{
    public const REPORTES = [
        'lineas' => LineasReporte::class,
        'estaciones' => EstacionesReporte::class,
        'buses' => BusesReporte::class,
        'pilotos' => PilotosReporte::class,
        'recorridos' => RecorridosReporte::class,
        'alertas' => AlertasReporte::class,
        'pagos' => PagosReporte::class,
        'bitacora' => BitacoraReporte::class,
    ];

    /** @return Collection<string, Reporte> */
    public static function todos(): Collection
    {
        return collect(self::REPORTES)->map(fn ($clase) => new $clase);
    }

    public static function buscar(string $clave): Reporte
    {
        abort_unless(isset(self::REPORTES[$clave]), 404);

        return new (self::REPORTES[$clave]);
    }
}
