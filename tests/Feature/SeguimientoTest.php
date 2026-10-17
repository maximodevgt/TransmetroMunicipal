<?php

namespace Tests\Feature;

use App\Models\Alerta;
use App\Models\Estacion;
use App\Services\SeguimientoService;
use App\Services\SimuladorService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\ConRecorridos;
use Tests\TestCase;

/**
 * GET /seguimiento/datos: datos del mapa en vivo (consultado cada 3 segundos).
 */
class SeguimientoTest extends TestCase
{
    use ConRecorridos;

    public function test_sin_sesion_no_hay_datos_ni_mapa(): void
    {
        $this->getJson(route('seguimiento.datos'))->assertUnauthorized();
        $this->get(route('seguimiento.index'))->assertRedirect(route('login'));
    }

    public function test_entrega_lineas_con_color_y_trazo_estaciones_con_coordenadas_y_buses_en_ruta(): void
    {
        $this->actingAs($this->administrador());
        $recorrido = $this->recorridoEnCurso();

        $respuesta = $this->getJson(route('seguimiento.datos'))
            ->assertOk()
            ->assertJsonStructure([
                'ahora', 'usuario' => ['es_administrador', 'estacion_id'], 'simulacion' => ['activa', 'buses'],
                'lineas' => [['id', 'codigo', 'color', 'trazo']],
                'estaciones' => [['id', 'nombre', 'lat', 'lng', 'lineas', 'en_espera', 'alertas', 'url']],
                'buses' => [['recorrido_id', 'codigo', 'linea', 'color', 'piloto', 'capacidad', 'pasajeros', 'ocupacion',
                    'estacion_actual', 'proxima_estacion', 'posicion' => ['lat', 'lng'], 'tramo', 'alertas', 'url']],
                'alertas',
            ]);

        $l12 = collect($respuesta->json('lineas'))->firstWhere('codigo', 'L12');
        $this->assertSame('#0369a1', $l12['color']);
        $this->assertCount(14, $l12['trazo']);
        $this->assertSame(Estacion::whereNotNull('latitud')->whereHas('lineas')->count(), count($respuesta->json('estaciones')));

        $bus = collect($respuesta->json('buses'))->firstWhere('recorrido_id', $recorrido->id);
        $primera = $this->estacionDeOrden($recorrido->linea, 1);
        $this->assertSame(['lat' => $primera->latitud, 'lng' => $primera->longitud], $bus['posicion'], 'Sin simulación el bus se ve en su estación.');
        $this->assertSame($recorrido->bus->codigo, $bus['codigo']);
    }

    public function test_con_el_horario_del_simulador_el_bus_aparece_entre_dos_estaciones(): void
    {
        $this->actingAs($this->administrador());
        $recorrido = $this->recorridoEnCurso();
        $desde = $this->estacionDeOrden($recorrido->linea, 1);
        $hacia = $this->estacionDeOrden($recorrido->linea, 2);
        $ahora = SimuladorService::ahora();
        Cache::forever(SimuladorService::CLAVE, [
            'activo' => true, 'buses' => 0, 'propios' => [], 'ultimo' => $ahora,
            'tramos' => [$recorrido->id => ['desde' => $desde->id, 'hacia' => $hacia->id, 'salida' => $ahora - 10, 'llegada' => $ahora + 10, 'fin' => null]],
        ]);

        $bus = collect($this->getJson(route('seguimiento.datos'))->json('buses'))->firstWhere('recorrido_id', $recorrido->id);

        $this->assertFalse($bus['en_estacion']);
        $this->assertEqualsWithDelta(($desde->latitud + $hacia->latitud) / 2, $bus['posicion']['lat'], 0.0002);
        $this->assertEqualsWithDelta(($desde->longitud + $hacia->longitud) / 2, $bus['posicion']['lng'], 0.0002);
        $this->assertSame(['lat' => $hacia->latitud, 'lng' => $hacia->longitud], $bus['tramo']['hacia']);
    }

    public function test_el_operador_recibe_solo_las_alertas_de_su_estacion(): void
    {
        $operador = $this->operadorTrebol();
        $propia = Alerta::create(['estacion_id' => $operador->estacion_id, 'tipo' => 'incidencia', 'mensaje' => 'Propia.', 'estado' => 'activa']);
        $ajena = Alerta::create(['estacion_id' => 1, 'tipo' => 'incidencia', 'mensaje' => 'Ajena.', 'estado' => 'activa']);

        $datos = $this->actingAs($operador)->getJson(route('seguimiento.datos'))->assertOk()->json();

        $this->assertFalse($datos['usuario']['es_administrador']);
        $this->assertSame($operador->estacion_id, $datos['usuario']['estacion_id']);
        $ids = array_column($datos['alertas'], 'id');
        $this->assertContains($propia->id, $ids);
        $this->assertNotContains($ajena->id, $ids);
        $this->assertSame([], collect($datos['estaciones'])->firstWhere('id', 1)['alertas']);
        $this->assertNotContains($ajena->id, collect($datos['buses'])->flatMap(fn ($b) => array_column($b['alertas'], 'id'))->all());
    }

    public function test_las_alertas_traen_su_boton_resolver(): void
    {
        $alerta = Alerta::create(['estacion_id' => 8, 'tipo' => 'incidencia', 'mensaje' => 'Con resolver.', 'estado' => 'activa']);

        $estacion = collect($this->actingAs($this->administrador())->getJson(route('seguimiento.datos'))->json('estaciones'))->firstWhere('id', 8);

        $this->assertSame(route('alertas.show', ['alerta' => $alerta, 'resolver' => 1]), collect($estacion['alertas'])->firstWhere('id', $alerta->id)['resolver']['url']);
    }

    public function test_las_consultas_no_crecen_con_la_cantidad_de_buses(): void
    {
        $this->actingAs($this->administrador());
        $this->recorridoEnCurso();

        DB::enableQueryLog();
        $this->getJson(route('seguimiento.datos'))->assertOk();
        $conUno = count(DB::getQueryLog());

        $this->recorridoEnCurso();
        $this->recorridoEnCurso();
        DB::flushQueryLog();
        $this->getJson(route('seguimiento.datos'))->assertOk();

        $this->assertSame($conUno, count(DB::getQueryLog()), 'Con eager loading, más buses no significan más consultas.');
        $this->assertLessThanOrEqual(25, $conUno);
    }

    public function test_armar_los_datos_toma_menos_de_200_ms(): void
    {
        $administrador = $this->administrador();
        $this->actingAs($administrador);
        foreach (range(1, 4) as $i) {
            $this->recorridoEnCurso();
        }
        $servicio = app(SeguimientoService::class);
        $servicio->datos($administrador);

        $inicio = microtime(true);
        $servicio->datos($administrador);

        $this->assertLessThan(200, (microtime(true) - $inicio) * 1000);
    }

    public function test_la_pantalla_carga_para_ambos_roles_y_solo_el_administrador_ve_la_simulacion(): void
    {
        $this->actingAs($this->administrador())->get(route('seguimiento.index'))
            ->assertOk()
            ->assertSee('data-mapa-seguimiento="completo"', false)
            ->assertSee('Iniciar simulación');

        $this->actingAs($this->operadorTrebol())->get(route('seguimiento.index'))
            ->assertOk()
            ->assertSee('data-mapa-seguimiento="completo"', false)
            ->assertDontSee('Iniciar simulación');
    }

    public function test_el_panel_tiene_mini_mapa_y_enlace_al_seguimiento(): void
    {
        $this->actingAs($this->operadorTrebol())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-mapa-seguimiento="mini"', false)
            ->assertSee('href="'.route('seguimiento.index').'"', false);
    }
}
