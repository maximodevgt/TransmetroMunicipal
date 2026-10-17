<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Las pruebas corren sobre la base transmetro: ninguna puede borrar ni recrear tablas.
 */
class PruebasSinBorrarDatosTest extends TestCase
{
    private const PROHIBIDO = '/\b(RefreshDatabase|DatabaseMigrations|DatabaseTruncation|LazilyRefreshDatabase)\b|[\'"](migrate|db:wipe|db:seed)[\'":]/';

    public function test_ninguna_prueba_borra_ni_recrea_la_base(): void
    {
        $archivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__)));
        $infractores = [];

        foreach ($archivos as $archivo) {
            if ($archivo->getExtension() !== 'php' || $archivo->getRealPath() === __FILE__) {
                continue;
            }
            if (preg_match(self::PROHIBIDO, file_get_contents($archivo->getRealPath()))) {
                $infractores[] = $archivo->getFilename();
            }
        }

        $this->assertSame([], $infractores, 'Estas pruebas borran o recrean tablas: '.implode(', ', $infractores));
    }

    public function test_la_clase_base_revierte_cada_prueba(): void
    {
        $this->assertStringContainsString('use DatabaseTransactions;', file_get_contents(dirname(__DIR__).'/TestCase.php'));
    }
}
