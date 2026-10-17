<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Las pruebas usan la base MySQL transmetro_test (ver phpunit.xml), creada con
 * database/sql/02_transmetro_test.sql para que existan la vista y los triggers.
 * Cada prueba corre dentro de una transacción que se revierte al terminar,
 * así los datos iniciales del script quedan intactos.
 */
abstract class TestCase extends BaseTestCase
{
    use DatabaseTransactions;

    public const BASE_DE_PRUEBAS = 'transmetro_test';

    protected function setUp(): void
    {
        parent::setUp();

        $conexion = DB::connection();

        if ($conexion->getDriverName() !== 'mysql' || $conexion->getDatabaseName() !== self::BASE_DE_PRUEBAS) {
            throw new RuntimeException(
                'Las pruebas solo pueden correr sobre la base MySQL '.self::BASE_DE_PRUEBAS
                ." y la conexión apunta a '{$conexion->getDatabaseName()}'. Revisa phpunit.xml."
            );
        }
    }
}
