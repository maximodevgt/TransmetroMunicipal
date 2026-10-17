<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Verifica que transmetro_test se cargó completa desde 02_transmetro_test.sql.
 */
class BaseDePruebasTest extends TestCase
{
    public function test_la_conexion_apunta_a_la_base_de_pruebas(): void
    {
        $this->assertSame(self::BASE_DE_PRUEBAS, DB::connection()->getDatabaseName());
    }

    public function test_existen_las_19_tablas_y_la_vista(): void
    {
        $tablas = DB::table('information_schema.TABLES')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_TYPE', 'BASE TABLE')
            ->count();
        $vista = DB::table('information_schema.VIEWS')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', 'vw_resumen_lineas')
            ->exists();

        $this->assertSame(19, $tablas);
        $this->assertTrue($vista);
    }

    public function test_existen_los_4_triggers_de_reglas_de_negocio(): void
    {
        $triggers = DB::table('information_schema.TRIGGERS')
            ->whereRaw('TRIGGER_SCHEMA = DATABASE()')
            ->orderBy('TRIGGER_NAME')
            ->pluck('TRIGGER_NAME')
            ->all();

        $this->assertSame([
            'trg_buses_max_insert',
            'trg_buses_max_update',
            'trg_transacciones_saldo',
            'trg_transacciones_validar',
        ], $triggers);
    }

    public function test_existen_los_usuarios_de_prueba(): void
    {
        $admin = User::where('email', 'admin@transmetro.gt')->firstOrFail();
        $operador = User::where('email', 'operador.trebol@transmetro.gt')->firstOrFail();

        $this->assertTrue($admin->esAdministrador());
        $this->assertSame(User::ROL_OPERADOR, $operador->rol);
        $this->assertTrue(Hash::check('Transmetro2026', $admin->password));
        $this->assertTrue(Hash::check('Transmetro2026', $operador->password));
    }

    public function test_cada_prueba_corre_dentro_de_una_transaccion(): void
    {
        // DatabaseTransactions revierte esta transacción al terminar, así los datos del script no cambian.
        $this->assertSame(1, DB::transactionLevel());
    }
}
