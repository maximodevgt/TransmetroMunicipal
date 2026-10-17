<?php

/*
 * Parámetros de la Tarjeta Transmetro. Todo pago es SIMULADO: no hay pasarela real.
 */
return [

    // Pasaje que cobra el torniquete (tarifa pública del Transmetro).
    'pasaje' => (float) env('TRANSMETRO_PASAJE', 1.00),

    // Recargas: botones de monto rápido y límites del monto libre (en quetzales).
    'recarga' => [
        'montos_rapidos' => [5, 10, 20, 50, 100],
        'minimo' => 1,
        'maximo' => 500,
    ],

    // Pasarela simulada: tarjetas de prueba y su resultado. Cualquier otra que pase Luhn se aprueba.
    'pasarela' => [
        'tarjetas_prueba' => [
            '4242424242424242' => null,
            '4000000000000002' => 'Fondos insuficientes',
            '4000000000000069' => 'Tarjeta vencida',
        ],
    ],

];
