<?php

namespace Tests\Feature;

use App\Models\Alerta;
use App\Models\Bitacora;
use App\Models\Bus;
use App\Models\Estacion;
use App\Models\RegistroPasajeros;
use App\Models\User;
use App\Reportes\Catalogo;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Reportes: ambos roles, alcance del operador, filtros, totales, impresión, CSV y bitácora.
 * Los datos esperados se calculan desde la base, no se suponen.
 */
class ReportesTest extends TestCase
{
    /** @return list<array<string, string>> filas del CSV como [encabezado => valor] */
    private function csv(TestResponse $respuesta): array
    {
        $contenido = $respuesta->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $contenido, 'El CSV debe empezar con BOM para Excel.');
        $lineas = array_map(fn ($l) => str_getcsv($l, ';', '"', ''), explode("\n", trim(substr($contenido, 3))));
        $encabezado = array_shift($lineas);

        return array_map(fn ($l) => array_combine($encabezado, $l), $lineas);
    }

    private function totales(TestResponse $respuesta): array
    {
        return collect($respuesta->viewData('totales'))->mapWithKeys(fn ($t) => [$t[0] => (float) $t[1]])->all();
    }

    private function alerta(int $estacionId, string $tipo, string $mensaje, ?string $creada = null): Alerta
    {
        $alerta = Alerta::create(['estacion_id' => $estacionId, 'tipo' => $tipo, 'mensaje' => $mensaje, 'estado' => 'activa']);
        if ($creada) {
            $alerta->created_at = $creada;
            $alerta->save();
        }

        return $alerta;
    }

    private function otraEstacion(): Estacion
    {
        return Estacion::where('id', '!=', $this->operadorTrebol()->estacion_id)->orderBy('id')->firstOrFail();
    }

    public function test_cada_reporte_responde_200_a_ambos_roles(): void
    {
        foreach ([$this->administrador(), $this->operadorTrebol()] as $usuario) {
            $this->actingAs($usuario)->get(route('reportes.index'))->assertOk()->assertSee('Tarjetas y pagos');
            foreach (array_keys(Catalogo::REPORTES) as $clave) {
                $this->get(route('reportes.show', $clave))->assertOk();
                $this->get(route('reportes.imprimir', $clave))->assertOk();
                $this->get(route('reportes.csv', $clave))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
            }
        }
    }

    public function test_un_reporte_que_no_existe_da_404(): void
    {
        $this->actingAs($this->administrador())->get('/reportes/inventado')->assertNotFound();
    }

    public function test_el_operador_no_ve_datos_de_otra_estacion(): void
    {
        $trebol = $this->operadorTrebol()->estacion_id;
        $otra = $this->otraEstacion();
        $this->alerta($trebol, 'incidencia', 'Incidencia QA en Trébol');
        $this->alerta($otra->id, 'incidencia', 'Incidencia QA en otra estación');

        $this->actingAs($this->operadorTrebol());
        $alertas = collect($this->csv($this->get(route('reportes.csv', ['alertas', 'periodo' => 'hoy']))))->pluck('Mensaje');
        $this->assertContains('Incidencia QA en Trébol', $alertas);
        $this->assertNotContains('Incidencia QA en otra estación', $alertas);

        $estaciones = $this->csv($this->get(route('reportes.csv', 'estaciones')));
        $this->assertSame(['Trébol'], array_column($estaciones, 'Estación'));

        // Bitácora: solo lo que hizo el operador.
        $usuarios = array_unique(array_column($this->csv($this->get(route('reportes.csv', 'bitacora'))), 'Usuario'));
        $this->assertSame([$this->operadorTrebol()->name], array_values($usuarios));

        // El administrador sí ve las dos alertas.
        $this->actingAs($this->administrador());
        $alertas = collect($this->csv($this->get(route('reportes.csv', ['alertas', 'periodo' => 'hoy']))))->pluck('Mensaje');
        $this->assertContains('Incidencia QA en otra estación', $alertas);
    }

    public function test_el_csv_trae_bom_encabezado_correcto_y_tildes(): void
    {
        $respuesta = $this->actingAs($this->administrador())->get(route('reportes.csv', 'lineas'));
        $respuesta->assertDownload('reporte-lineas-'.now()->format('Y-m-d').'.csv');

        $contenido = $respuesta->streamedContent();
        $this->assertSame(
            ['Código', 'Nombre', 'Estaciones', 'Distancia (km)', 'Buses asignados', 'Mínimo', 'Máximo', 'Estado'],
            str_getcsv(explode("\n", substr($contenido, 3))[0], ';', '"', ''),
        );
        $this->assertTrue(mb_check_encoding($contenido, 'UTF-8'));

        $estaciones = array_column($this->csv($this->get(route('reportes.csv', 'estaciones'))), 'Estación');
        $this->assertContains('Trébol', $estaciones);
        $this->assertContains('Monte María', $estaciones);
    }

    public function test_los_filtros_funcionan_y_quedan_en_la_url(): void
    {
        $trebol = $this->operadorTrebol()->estacion_id;
        $this->alerta($trebol, 'incidencia', 'Incidencia QA de hoy');
        $this->alerta($trebol, 'baja_ocupacion', 'Baja ocupación QA de hoy');
        $this->alerta($trebol, 'incidencia', 'Incidencia QA de hace 3 días', now()->subDays(3)->toDateTimeString());
        $this->actingAs($this->administrador());

        $hoy = collect($this->csv($this->get(route('reportes.csv', ['alertas', 'periodo' => 'hoy', 'tipo' => 'incidencia']))));
        $this->assertSame(['Incidencia'], $hoy->pluck('Tipo')->unique()->values()->all());
        $this->assertContains('Incidencia QA de hoy', $hoy->pluck('Mensaje'));
        $this->assertNotContains('Incidencia QA de hace 3 días', $hoy->pluck('Mensaje'));

        $semana = collect($this->csv($this->get(route('reportes.csv', ['alertas', 'periodo' => '7dias', 'tipo' => 'incidencia']))));
        $this->assertContains('Incidencia QA de hace 3 días', $semana->pluck('Mensaje'));

        // Rango explícito de un solo día.
        $dia = now()->subDays(3)->format('Y-m-d');
        $rango = collect($this->csv($this->get(route('reportes.csv', ['alertas', 'desde' => $dia, 'hasta' => $dia]))));
        $this->assertContains('Incidencia QA de hace 3 días', $rango->pluck('Mensaje'));
        $this->assertNotContains('Incidencia QA de hoy', $rango->pluck('Mensaje'));

        // Buses por línea.
        $bus = Bus::whereNotNull('linea_id')->with('linea')->firstOrFail();
        $buses = collect($this->csv($this->get(route('reportes.csv', ['buses', 'linea_id' => $bus->linea_id]))));
        $this->assertSame([$bus->linea->codigo], $buses->pluck('Línea')->unique()->values()->all());
        $this->assertCount(Bus::where('linea_id', $bus->linea_id)->count(), $buses);

        // Un valor que no es opción se ignora; los filtros válidos quedan en los enlaces de imprimir y exportar.
        $this->get(route('reportes.show', ['alertas', 'tipo' => 'inventado']))->assertOk();
        $this->get(route('reportes.show', ['alertas', 'periodo' => '7dias', 'tipo' => 'incidencia']))
            ->assertSee('href="'.e(route('reportes.csv', ['alertas', 'tipo' => 'incidencia', 'periodo' => '7dias'])).'"', false)
            ->assertSee('href="'.e(route('reportes.imprimir', ['alertas', 'tipo' => 'incidencia', 'periodo' => '7dias'])).'"', false);
    }

    public function test_los_totales_cuadran_con_la_tabla(): void
    {
        $this->actingAs($this->administrador());
        $suma = fn (array $filas, string $columna) => round(array_sum(array_map('floatval', array_column($filas, $columna))), 2);

        $parametros = ['periodo' => 'mes'];
        $casos = [
            ['lineas', 'Buses asignados', 'Buses asignados'],
            ['estaciones', 'Accesos', 'Accesos'],
            ['estaciones', 'En espera', 'Pasajeros en espera'],
            ['buses', 'Capacidad', 'Capacidad total'],
            ['pilotos', 'Recorridos', 'Recorridos'],
            ['pilotos', 'Kilómetros (km)', 'Kilómetros'],
            ['recorridos', 'Estaciones visitadas', 'Estaciones visitadas'],
            ['pagos', 'Total recargado (Q)', 'Total recargado'],
            ['pagos', 'Pasajes', 'Pasajes cobrados'],
            ['pagos', 'Rechazos', 'Rechazos'],
        ];
        foreach ($casos as [$clave, $columna, $total]) {
            $filas = $this->csv($this->get(route('reportes.csv', [$clave, ...$parametros])));
            $totales = $this->totales($this->get(route('reportes.show', [$clave, ...$parametros])));
            $this->assertEquals(round($totales[$total], 2), $suma($filas, $columna), "{$clave}: {$columna} no cuadra con «{$total}».");
        }

        // Los que cuentan filas. En la bitácora se usa una acción que no sea de reportes:
        // generar un reporte agrega un registro y cambiaría el conteo entre una petición y otra.
        $accion = Bitacora::where('tabla', '!=', 'reportes')->where('created_at', '>=', today()->startOfMonth())->latest('id')->value('accion');
        $casos = ['lineas' => [[], 'Líneas'], 'recorridos' => [[], 'Recorridos'], 'alertas' => [[], 'Alertas'], 'bitacora' => [['accion' => $accion], 'Registros']];
        foreach ($casos as $clave => [$extra, $total]) {
            $filas = $this->csv($this->get(route('reportes.csv', [$clave, ...$parametros, ...$extra])));
            $this->assertEquals($this->totales($this->get(route('reportes.show', [$clave, ...$parametros, ...$extra])))[$total], count($filas), "{$clave}: el conteo no cuadra.");
        }
    }

    public function test_la_pantalla_pagina_pero_la_impresion_lleva_todo(): void
    {
        $this->actingAs($this->administrador());
        $total = Estacion::count();
        $this->assertGreaterThan(25, $total, 'Se necesitan más de 25 estaciones para probar la paginación.');

        $pantalla = $this->get(route('reportes.show', 'estaciones'))->assertOk();
        $this->assertCount(25, $pantalla->viewData('filas')->items());
        $this->assertSame($total, $pantalla->viewData('filas')->total());

        $impresion = $this->get(route('reportes.imprimir', 'estaciones'))->assertOk();
        // Estaciones no tiene resumen: todas las filas marcadas son de la tabla principal.
        $this->assertSame($total, substr_count($impresion->getContent(), '<tr data-fila'));
    }

    public function test_la_impresion_lleva_encabezado_y_no_lleva_menu(): void
    {
        $this->actingAs($this->operadorTrebol())
            ->get(route('reportes.imprimir', ['alertas', 'periodo' => 'hoy', 'tipo' => 'incidencia']))
            ->assertOk()
            ->assertSee('Municipalidad de Guatemala – Transmetro')
            ->assertSee('Alertas')
            ->assertSee('Fecha: '.now()->format('d/m/Y'))
            ->assertSee('Tipo: Incidencia')
            ->assertSee('Estación: Trébol')
            ->assertSee($this->operadorTrebol()->name)
            ->assertSee('size: letter', false)
            ->assertSee('counter(page)', false)
            ->assertDontSee('Cerrar sesión');
    }

    public function test_generar_un_reporte_queda_en_la_bitacora(): void
    {
        $this->actingAs($this->administrador());
        $antes = Bitacora::max('id');

        $this->get(route('reportes.show', ['lineas', 'estado' => 'cumple']));
        $this->get(route('reportes.show', ['estaciones', 'page' => 2]));
        $this->get(route('reportes.imprimir', 'buses'));
        $this->get(route('reportes.csv', 'pagos'))->streamedContent();

        $registros = Bitacora::where('id', '>', $antes)->where('tabla', 'reportes')->orderBy('id')->get();
        $this->assertSame(['generar_reporte', 'imprimir_reporte', 'exportar_reporte'], $registros->pluck('accion')->all());
        $this->assertSame('Reporte Líneas en pantalla · Estado: Cumple', $registros[0]->detalle);
        $this->assertStringStartsWith('Reporte Tarjetas y pagos en CSV · Del ', $registros[2]->detalle);
        $this->assertSame([$this->administrador()->id], $registros->pluck('user_id')->unique()->values()->all());
    }

    public function test_cada_reporte_hace_pocas_consultas(): void
    {
        $this->actingAs($this->administrador());
        $limites = [];
        foreach (array_keys(Catalogo::REPORTES) as $clave) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get(route('reportes.show', $clave))->assertOk();
            $limites[$clave] = count(DB::getQueryLog());
            DB::disableQueryLog();
        }

        // Sin N+1: el número de consultas no depende de las filas (estaciones tiene 28 y no hace 28 consultas).
        foreach ($limites as $clave => $consultas) {
            $this->assertLessThanOrEqual(12, $consultas, "El reporte {$clave} hace {$consultas} consultas.");
        }
    }

    public function test_el_menu_tiene_reportes_y_cobro_de_pasaje(): void
    {
        foreach ([$this->administrador(), $this->operadorTrebol()] as $usuario) {
            $this->actingAs($usuario)->get(route('dashboard'))
                ->assertSee('href="'.route('reportes.index').'"', false)
                ->assertSee('Cobro de pasaje');
        }
        $this->get(route('torniquete.index'))->assertSee('Simula el paso del pasajero por el torniquete de la estación');
    }

    // --- Gráficas del panel ---

    public function test_el_endpoint_de_graficas_devuelve_el_formato_esperado(): void
    {
        $respuesta = $this->actingAs($this->administrador())->getJson(route('panel.graficas'))->assertOk()
            ->assertJsonStructure(['actualizado', 'graficas' => [
                'espera', 'recorridos_hoy', 'alertas_semana', 'recaudacion_semana', 'ocupacion_lineas',
            ]]);

        $tipos = ['espera' => 'bar', 'recorridos_hoy' => 'line', 'alertas_semana' => 'bar', 'recaudacion_semana' => 'line', 'ocupacion_lineas' => 'doughnut'];
        foreach ($respuesta->json('graficas') as $clave => $grafica) {
            $this->assertSame($tipos[$clave], $grafica['tipo']);
            $this->assertIsBool($grafica['hay_datos']);
            $this->assertNotEmpty($grafica['vacio']);
            foreach ($grafica['series'] as $serie) {
                $this->assertNotEmpty($serie['nombre']);
                $this->assertCount(count($grafica['etiquetas']), $serie['datos'], "{$clave}: datos y etiquetas no coinciden.");
                $this->assertCount(count($grafica['etiquetas']), $serie['enlaces']);
                foreach ($serie['enlaces'] as $url) {
                    $this->assertStringStartsWith(route('reportes.index').'/', $url);
                }
            }
        }
        $this->assertCount(24, $respuesta->json('graficas.recorridos_hoy.etiquetas'));
        $this->assertSame(today()->subDays(6)->format('d/m'), $respuesta->json('graficas.recaudacion_semana.etiquetas.0'));
        $this->assertSame(['Recargas', 'Pasajes'], array_column($respuesta->json('graficas.recaudacion_semana.series'), 'nombre'));
        $this->assertLessThanOrEqual(10, count($respuesta->json('graficas.espera.etiquetas')));
    }

    public function test_las_graficas_del_operador_son_solo_de_su_estacion(): void
    {
        $otra = $this->otraEstacion();
        RegistroPasajeros::create(['estacion_id' => $otra->id, 'user_id' => $this->administrador()->id, 'cantidad' => 9999, 'registrado_en' => now()]);
        $this->alerta($otra->id, 'incidencia', 'Incidencia QA para la gráfica');

        $admin = $this->actingAs($this->administrador())->getJson(route('panel.graficas'));
        $this->assertSame($otra->nombre, $admin->json('graficas.espera.etiquetas.0'));

        $operador = $this->actingAs($this->operadorTrebol())->getJson(route('panel.graficas'))->assertOk();
        $this->assertNotContains($otra->nombre, $operador->json('graficas.espera.etiquetas'));
        $this->assertContains($operador->json('graficas.espera.etiquetas'), [[], ['Trébol']]);
        // Las incidencias del operador hoy son solo las de Trébol.
        $incidenciasHoy = collect($operador->json('graficas.alertas_semana.series'))->firstWhere('nombre', 'Incidencia')['datos'][6] ?? 0;
        $this->assertSame(Alerta::where('estacion_id', $this->operadorTrebol()->estacion_id)->where('tipo', 'incidencia')->where('created_at', '>=', today())->count(), $incidenciasHoy);
    }

    public function test_sin_datos_las_graficas_traen_un_mensaje_en_vez_de_datos(): void
    {
        $this->actingAs($this->operadorSinEstacion())->getJson(route('panel.graficas'))->assertOk()
            ->assertJsonPath('graficas.espera.hay_datos', false)
            ->assertJsonPath('graficas.espera.vacio', 'No hay pasajeros en espera registrados.')
            ->assertJsonPath('graficas.recorridos_hoy.hay_datos', false)
            ->assertJsonPath('graficas.alertas_semana.hay_datos', false)
            ->assertJsonPath('graficas.recaudacion_semana.hay_datos', false)
            ->assertJsonPath('graficas.ocupacion_lineas.hay_datos', false);
    }

    public function test_el_panel_tiene_las_cinco_graficas(): void
    {
        $respuesta = $this->actingAs($this->operadorTrebol())->get(route('dashboard'))->assertOk()
            ->assertSee('data-url-graficas="'.route('panel.graficas').'"', false);
        foreach (['espera', 'recorridos_hoy', 'alertas_semana', 'recaudacion_semana', 'ocupacion_lineas'] as $clave) {
            $respuesta->assertSee('data-grafica="'.$clave.'"', false);
        }
    }

    private function operadorSinEstacion(): User
    {
        $usuario = User::where('rol', User::ROL_OPERADOR)->firstOrFail()->replicate(['email']);
        $usuario->fill(['email' => 'operador.sin.estacion.qa@transmetro.gt', 'estacion_id' => null])->save();

        return $usuario;
    }

    public function test_un_operador_sin_estacion_no_ve_movimientos_ajenos(): void
    {
        $this->actingAs($this->operadorSinEstacion());
        $this->assertSame([], $this->csv($this->get(route('reportes.csv', ['pagos', 'agrupar' => 'estacion']))));
        $this->get(route('transacciones.index'))->assertOk()->assertSee('No hay movimientos con esos filtros.');
    }
}
