<?php

namespace App\Support;

/**
 * Posición de un bus entre dos estaciones según el tiempo (interpolación lineal).
 * La misma fórmula usa el navegador para mover el ícono sin saltos entre consultas.
 */
final class Interpolacion
{
    /**
     * Fracción del tramo recorrida en $ahora: 0 al salir, 1 al llegar, sin salirse de [0, 1].
     */
    public static function fraccion(float $salida, float $llegada, float $ahora): float
    {
        if ($llegada <= $salida) {
            return $ahora >= $llegada ? 1.0 : 0.0;
        }

        return max(0.0, min(1.0, ($ahora - $salida) / ($llegada - $salida)));
    }

    /**
     * @param  array{lat: float, lng: float}  $desde
     * @param  array{lat: float, lng: float}  $hacia
     * @return array{lat: float, lng: float}
     */
    public static function punto(array $desde, array $hacia, float $fraccion): array
    {
        $fraccion = max(0.0, min(1.0, $fraccion));

        return [
            'lat' => round($desde['lat'] + ($hacia['lat'] - $desde['lat']) * $fraccion, 6),
            'lng' => round($desde['lng'] + ($hacia['lng'] - $desde['lng']) * $fraccion, 6),
        ];
    }
}
