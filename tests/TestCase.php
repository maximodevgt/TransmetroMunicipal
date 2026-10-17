<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Las pruebas usan la base MySQL transmetro (ver phpunit.xml), con su vista y sus triggers.
 * Cada prueba corre dentro de una transacción que se revierte al terminar, así nunca dejan datos.
 * No usar rasgos que refresquen la base ni migraciones: borrarían o recrearían las tablas.
 */
abstract class TestCase extends BaseTestCase
{
    use DatabaseTransactions;

    protected function administrador(): User
    {
        return User::where('email', 'admin@transmetro.gt')->firstOrFail();
    }

    /**
     * Operador de la estación Trébol (estación 8).
     */
    protected function operadorTrebol(): User
    {
        return User::where('email', 'operador.trebol@transmetro.gt')->firstOrFail();
    }
}
