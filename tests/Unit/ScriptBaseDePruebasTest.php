<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__.'/../../database/sql/generar_transmetro_test.php';

class ScriptBaseDePruebasTest extends TestCase
{
    private const CARPETA = __DIR__.'/../../database/sql';

    public function test_el_script_de_pruebas_esta_sincronizado_con_el_original(): void
    {
        $esperado = generarSqlPruebas(file_get_contents(self::CARPETA.'/01_transmetro.sql'));

        $this->assertSame(
            $esperado,
            file_get_contents(self::CARPETA.'/02_transmetro_test.sql'),
            '02_transmetro_test.sql está desactualizado: ejecuta php database/sql/generar_transmetro_test.php'
        );
    }

    public function test_el_script_de_pruebas_nunca_toca_la_base_real(): void
    {
        $sql = file_get_contents(self::CARPETA.'/02_transmetro_test.sql');

        $this->assertDoesNotMatchRegularExpression('/^\s*(USE|DROP\s+DATABASE|CREATE\s+DATABASE)\s+(IF\s+EXISTS\s+)?transmetro\s*;?\s*$/mi', $sql);
        $this->assertStringContainsString('USE transmetro_test;', $sql);
    }

    public function test_el_generador_rechaza_un_script_con_otra_base(): void
    {
        $this->expectException(RuntimeException::class);

        generarSqlPruebas("DROP DATABASE IF EXISTS transmetro;\nCREATE DATABASE transmetro CHARACTER SET utf8mb4;\nUSE transmetro;\nUSE otra;\n");
    }
}
