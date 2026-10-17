<?php

/**
 * Genera 02_transmetro_test.sql a partir de 01_transmetro.sql.
 *
 * Solo cambia el nombre de la base (transmetro -> transmetro_test) en las tres
 * sentencias de encabezado; tablas, vista, triggers y datos quedan idénticos.
 * Los correos @transmetro.gt no se tocan porque se reemplazan sentencias completas.
 *
 * Uso: php database/sql/generar_transmetro_test.php
 */
function generarSqlPruebas(string $sql): string
{
    // La base original solo debe aparecer en estas tres sentencias.
    if (preg_match_all('/^\s*(USE|DROP\s+DATABASE|CREATE\s+DATABASE)\b/mi', $sql) !== 3) {
        throw new RuntimeException('01_transmetro.sql debe tener exactamente un DROP DATABASE, un CREATE DATABASE y un USE.');
    }

    $reemplazos = [
        'DROP DATABASE IF EXISTS transmetro;' => 'DROP DATABASE IF EXISTS transmetro_test;',
        'CREATE DATABASE transmetro CHARACTER SET' => 'CREATE DATABASE transmetro_test CHARACTER SET',
        'USE transmetro;' => 'USE transmetro_test;',
    ];

    foreach ($reemplazos as $buscar => $nuevo) {
        if (substr_count($sql, $buscar) !== 1) {
            throw new RuntimeException("No se encontró exactamente una vez: {$buscar}");
        }
        $sql = str_replace($buscar, $nuevo, $sql);
    }

    return "-- =====================================================================\n"
        ."-- ARCHIVO GENERADO por generar_transmetro_test.php desde 01_transmetro.sql.\n"
        ."-- No editar a mano. Base exclusiva para las pruebas automáticas (phpunit).\n"
        .$sql;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $sql = generarSqlPruebas(file_get_contents(__DIR__.'/01_transmetro.sql'));
    file_put_contents(__DIR__.'/02_transmetro_test.sql', $sql);
    echo "Generado database/sql/02_transmetro_test.sql\n";
}
