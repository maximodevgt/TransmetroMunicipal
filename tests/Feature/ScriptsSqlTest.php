<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GraficasService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Los scripts de datos (06 y 07) se ejecutan dentro de la transacción de la prueba, que se revierte
 * al terminar: así se comprueba que corren sin errores sobre la base real, que respetan los triggers
 * y que son idempotentes, sin dejar nada guardado. Solo usan DML y una tabla temporal (no confirman).
 */
class ScriptsSqlTest extends TestCase
{
    /** Ejecuta el script y devuelve el resultado de su última consulta (la verificación). */
    private function ejecutar(string $archivo): array
    {
        $resultado = [];
        // Como MySQL Workbench: con el modo de actualización segura, un UPDATE sin llave en el WHERE falla (1175).
        DB::statement('SET SESSION sql_safe_updates = 1');
        $sql = file_get_contents(database_path("sql/{$archivo}"));
        foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $sentencia) {
            // Sin comentarios de línea; la transacción del script se omite porque la prueba ya tiene la suya,
            // y USE sobra porque la conexión de las pruebas ya está en transmetro.
            $sentencia = trim(preg_replace('/^\s*--.*$/m', '', $sentencia));
            if ($sentencia === '' || preg_match('/^(START TRANSACTION|COMMIT|USE \w+)$/i', $sentencia)) {
                continue;
            }
            // Las consultas de verificación del final se leen completas para no dejar resultados pendientes.
            preg_match('/^SELECT\s/i', $sentencia) ? $resultado = DB::select($sentencia) : DB::statement($sentencia);
        }
        DB::statement('SET SESSION sql_safe_updates = 0');

        return $resultado;
    }

    private function demo(): string
    {
        return (string) DB::selectOne('SELECT @demo AS demo')->demo;
    }

    public function test_06_pone_las_tildes_y_se_puede_repetir(): void
    {
        $this->ejecutar('06_correcciones_datos.sql');
        $verificacion = $this->ejecutar('06_correcciones_datos.sql');

        $this->assertSame(['Línea 12 - Eje Sur', 'Línea 1 - Centro Histórico', 'Línea 2 - Hipódromo'],
            DB::table('lineas')->whereIn('codigo', ['L12', 'L1', 'L2'])->orderBy('id')->pluck('nombre')->all());
        // La intercalación de la base no distingue tildes: se compara byte a byte.
        $this->assertSame(0, DB::table('parqueos')->whereRaw("nombre COLLATE utf8mb4_bin IN ('Predio Centro Historico', 'Predio Hipodromo')")->count());
        $this->assertSame(0, DB::table('historial_educativo')->whereRaw("titulo COLLATE utf8mb4_bin IN ('Tecnico en Transporte', 'Tercero Basico', 'Perito en Mecanica Automotriz')")->count());
        $this->assertSame(['Instituto Nacional de Educación Básica', 'Instituto Técnico Vocacional Dr. Imrich Fischmann'],
            DB::table('historial_educativo')->where('institucion', 'like', 'Instituto %')->where('institucion', '!=', 'Instituto Nacional Central para Varones')
                ->distinct()->orderBy('institucion')->pluck('institucion')->all());
        $this->assertSame([0, 0, 0, 0], array_map(fn ($f) => (int) $f->filas, $verificacion), 'La verificación del script debe salir en cero.');
    }

    public function test_01_ya_trae_los_textos_con_tilde(): void
    {
        $sql = file_get_contents(database_path('sql/01_transmetro.sql'));
        foreach (['Línea 1 - Centro Histórico', 'Línea 2 - Hipódromo', 'Predio Hipódromo', 'Técnico en Transporte', 'Estación Trébol con 155'] as $texto) {
            $this->assertStringContainsString($texto, $sql);
        }
        $this->assertStringNotContainsString("'Linea ", $sql);
    }

    public function test_07_corre_sin_errores_respeta_los_triggers_y_no_se_duplica(): void
    {
        $tm4 = DB::table('tarjetas')->where('codigo', 'TM-000004')->value('saldo');
        // Si la base ya tiene la carga (el script se ejecutó en Workbench), se validan esos datos reales;
        // si no, se cargan aquí dentro de la transacción de la prueba.
        $marca = DB::table('bitacora')->where('accion', 'cargar_datos_demo')->first();
        if ($marca) {
            $demo = (string) $marca->created_at;
        } else {
            $this->ejecutar('07_datos_demo.sql');
            $demo = $this->demo();
        }

        $cuenta = fn () => collect(['recorridos', 'recorrido_estaciones', 'registros_pasajeros', 'alertas', 'pasajeros', 'tarjetas', 'transacciones'])
            ->mapWithKeys(fn ($t) => [$t => DB::table($t)->where('updated_at', $demo)->count()])->all();
        $antes = $cuenta();

        $this->assertSame(1, DB::table('bitacora')->where('accion', 'cargar_datos_demo')->count());
        $this->assertGreaterThan(100, $antes['recorridos'], 'Debe haber recorridos de 7 días.');
        $this->assertGreaterThan($antes['recorridos'] * 4, $antes['recorrido_estaciones']);
        $this->assertSame(20, $antes['tarjetas']);
        $this->assertGreaterThan(300, $antes['transacciones']);
        $this->assertSame(0, DB::table('recorridos')->where('updated_at', $demo)->where('fin', '>', $demo)->count(), 'Nada en el futuro.');
        $this->assertSame(0, DB::table('transacciones')->where('updated_at', $demo)->where('created_at', '>', $demo)->count());

        // Saldo de cada tarjeta nueva = recargas aprobadas - pasajes aprobados (lo calcularon los triggers).
        foreach (DB::table('tarjetas')->where('updated_at', $demo)->get() as $tarjeta) {
            $movimientos = DB::table('transacciones')->where('tarjeta_id', $tarjeta->id)->where('estado', 'aprobada');
            $esperado = (clone $movimientos)->where('tipo', 'recarga')->sum('monto') - (clone $movimientos)->where('tipo', 'pago')->sum('monto');
            $this->assertEquals(round($esperado, 2), (float) $tarjeta->saldo, "Saldo de {$tarjeta->codigo}");
            $this->assertEquals((float) $tarjeta->saldo, (float) DB::table('transacciones')->where('tarjeta_id', $tarjeta->id)->orderByDesc('id')->value('saldo_resultante'));
        }
        // Las tarjetas del script inicial no cambian de saldo; los rechazos quedan registrados.
        $this->assertSame($tm4, DB::table('tarjetas')->where('codigo', 'TM-000004')->value('saldo'));
        $this->assertGreaterThan(0, DB::table('transacciones')->where('updated_at', $demo)->where('estado', 'rechazada')->count());
        $this->assertSame(0, DB::table('transacciones')->where('updated_at', $demo)->whereNotNull('tarjeta_ultimos4')->whereRaw("tarjeta_ultimos4 NOT REGEXP '^[0-9]{4}$'")->count());

        // RN-16: cada espera de 5 minutos tiene su alerta de baja ocupación.
        $esperas = DB::table('recorrido_estaciones')->where('updated_at', $demo)->where('espera_extra_min', 5)->count();
        $this->assertSame($esperas, DB::table('alertas')->where('updated_at', $demo)->where('tipo', 'baja_ocupacion')->count());
        // Sin recorridos viejos abiertos.
        $this->assertSame(0, DB::table('recorridos')->where('estado', 'en_curso')->where('inicio', '<', now()->subDay())->count());

        // Las gráficas de la semana ya tienen datos.
        $graficas = app(GraficasService::class)->para(User::where('email', 'admin@transmetro.gt')->firstOrFail());
        $this->assertTrue($graficas['alertas_semana']['hay_datos']);
        $this->assertTrue($graficas['recaudacion_semana']['hay_datos']);
        $this->assertTrue($graficas['espera']['hay_datos']);

        // Segunda ejecución: la marca de la bitácora evita duplicar.
        $this->ejecutar('07_datos_demo.sql');
        $this->assertSame($antes, $cuenta());
        $this->assertSame(1, DB::table('bitacora')->where('accion', 'cargar_datos_demo')->count());
    }
}
