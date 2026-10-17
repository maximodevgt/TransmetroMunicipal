<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Recorrido;
use App\Services\RecorridoService;
use Tests\Feature\Concerns\ConRecorridos;
use Tests\TestCase;

/**
 * Ciclo del recorrido: iniciar, llegar a cada estación en orden y finalizar (App\Services\RecorridoService).
 */
class RecorridosTest extends TestCase
{
    use ConRecorridos;

    public function test_el_administrador_inicia_un_recorrido_y_el_bus_queda_en_ruta(): void
    {
        $linea = $this->lineaDePrueba();
        $bus = $this->busEn($linea);
        $piloto = $this->pilotoNuevo();

        $respuesta = $this->actingAs($this->administrador())
            ->post(route('recorridos.store'), ['linea_id' => $linea->id, 'bus_id' => $bus->id, 'piloto_id' => $piloto->id]);

        $recorrido = Recorrido::where('bus_id', $bus->id)->firstOrFail();
        $respuesta->assertRedirect(route('recorridos.show', $recorrido))
            ->assertSessionHas('ok', "Recorrido iniciado: {$bus->codigo} está en ruta.");
        $this->assertSame('en_curso', $recorrido->estado);
        $this->assertSame($piloto->id, $recorrido->piloto_id);
        $this->assertSame('en_ruta', $bus->fresh()->estado);
        $this->assertDatabaseHas('bitacora', ['tabla' => 'recorridos', 'registro_id' => $recorrido->id, 'accion' => 'iniciar']);
    }

    public function test_un_bus_que_ya_esta_en_ruta_no_inicia_otro_recorrido(): void
    {
        $recorrido = $this->recorridoEnCurso();

        $this->actingAs($this->administrador())
            ->post(route('recorridos.store'), ['linea_id' => $recorrido->linea_id, 'bus_id' => $recorrido->bus_id, 'piloto_id' => $this->pilotoNuevo()->id])
            ->assertSessionHas('error', "El bus {$recorrido->bus->codigo} ya está en ruta (recorrido #{$recorrido->id} en curso).");

        $this->assertSame(1, Recorrido::where('bus_id', $recorrido->bus_id)->count());
    }

    public function test_un_piloto_que_ya_conduce_no_inicia_otro_recorrido(): void
    {
        $recorrido = $this->recorridoEnCurso();
        $otroBus = $this->busEn($recorrido->linea);

        $this->actingAs($this->administrador())
            ->post(route('recorridos.store'), ['linea_id' => $recorrido->linea_id, 'bus_id' => $otroBus->id, 'piloto_id' => $recorrido->piloto_id])
            ->assertSessionHas('error', "El piloto {$recorrido->piloto->nombre} ya está conduciendo otro recorrido (#{$recorrido->id}).");

        $this->assertSame('disponible', $otroBus->fresh()->estado);
    }

    public function test_el_bus_debe_pertenecer_a_la_linea_y_el_piloto_estar_activo(): void
    {
        $linea = $this->lineaDePrueba();
        $busSinLinea = $this->busEn(null);
        $this->actingAs($this->administrador());

        $this->post(route('recorridos.store'), ['linea_id' => $linea->id, 'bus_id' => $busSinLinea->id, 'piloto_id' => $this->pilotoNuevo()->id])
            ->assertSessionHas('error', "El bus {$busSinLinea->codigo} no está asignado a la línea {$linea->codigo}.");

        $inactivo = $this->pilotoNuevo('inactivo');
        $this->post(route('recorridos.store'), ['linea_id' => $linea->id, 'bus_id' => $this->busEn($linea)->id, 'piloto_id' => $inactivo->id])
            ->assertSessionHas('error', "El piloto {$inactivo->nombre} está inactivo.");

        $this->assertSame(0, Recorrido::where('linea_id', $linea->id)->count());
    }

    public function test_la_llegada_se_registra_en_el_orden_de_la_linea(): void
    {
        $recorrido = $this->recorridoEnCurso();
        $primera = $this->estacionDeOrden($recorrido->linea, 1);
        $segunda = $this->estacionDeOrden($recorrido->linea, 2);
        $this->actingAs($this->administrador());

        // Saltarse la primera estación se rechaza.
        $this->post(route('recorridos.llegadas.store', $recorrido), ['estacion_id' => $segunda->id, 'pasajeros_a_bordo' => 50])
            ->assertSessionHas('error', "La siguiente estación del recorrido es {$primera->nombre}, no {$segunda->nombre}.");
        $this->assertSame(0, $recorrido->visitas()->count());

        $this->post(route('recorridos.llegadas.store', $recorrido), ['estacion_id' => $primera->id, 'pasajeros_a_bordo' => 50])
            ->assertSessionHas('ok', "Llegada de {$recorrido->bus->codigo} a {$primera->nombre} registrada.");
        $this->post(route('recorridos.llegadas.store', $recorrido), ['estacion_id' => $segunda->id, 'pasajeros_a_bordo' => 60])
            ->assertSessionHasNoErrors();

        $this->assertSame([$primera->id, $segunda->id], $recorrido->visitas()->pluck('estacion_id')->all());
        // Al llegar a la segunda, la primera queda con su hora de salida.
        $this->assertNotNull($recorrido->visitas()->where('estacion_id', $primera->id)->value('hora_salida'));
    }

    public function test_la_hora_sin_segundos_del_formulario_se_acepta_en_el_minuto_de_inicio(): void
    {
        $recorrido = $this->recorridoEnCurso();
        $primera = $this->estacionDeOrden($recorrido->linea, 1);
        $this->actingAs($this->administrador());

        // Regresión: el recorrido empezó a las HH:MM:ss y el formulario envía HH:MM.
        $this->post(route('recorridos.llegadas.store', $recorrido), [
            'estacion_id' => $primera->id, 'pasajeros_a_bordo' => 50, 'hora_llegada' => $recorrido->inicio->format('Y-m-d\TH:i'),
        ])->assertSessionHas('ok');

        $this->assertTrue($recorrido->visitas()->first()->hora_llegada->gte($recorrido->inicio));

        $this->post(route('recorridos.llegadas.store', $recorrido), [
            'estacion_id' => $this->estacionDeOrden($recorrido->linea, 2)->id, 'pasajeros_a_bordo' => 50,
            'hora_llegada' => $recorrido->inicio->copy()->subMinutes(5)->format('Y-m-d\TH:i'),
        ])->assertSessionHas('error', fn ($m) => str_starts_with($m, 'La hora de llegada no puede ser anterior a'));
    }

    public function test_los_pasajeros_a_bordo_no_pueden_superar_la_capacidad_del_bus(): void
    {
        $recorrido = $this->recorridoEnCurso(capacidad: 80);
        $primera = $this->estacionDeOrden($recorrido->linea, 1);

        $this->actingAs($this->administrador())
            ->post(route('recorridos.llegadas.store', $recorrido), ['estacion_id' => $primera->id, 'pasajeros_a_bordo' => 81])
            ->assertSessionHas('error', "Los pasajeros a bordo (81) superan la capacidad del bus {$recorrido->bus->codigo} (80).");

        $this->assertSame(0, $recorrido->visitas()->count());
    }

    public function test_finalizar_devuelve_el_bus_a_disponible_y_calcula_la_distancia(): void
    {
        $recorrido = $this->recorridoEnCurso();
        $this->actingAs($this->administrador());
        foreach ([1, 2] as $orden) {
            $this->post(route('recorridos.llegadas.store', $recorrido), ['estacion_id' => $this->estacionDeOrden($recorrido->linea, $orden)->id, 'pasajeros_a_bordo' => 60]);
        }

        // Estaciones visitadas: 0 km (inicio) + 1.5 km.
        $this->patch(route('recorridos.finalizar', $recorrido))
            ->assertRedirect(route('recorridos.show', $recorrido))
            ->assertSessionHas('ok', "Recorrido finalizado: 1.50 km recorridos. {$recorrido->bus->codigo} vuelve a estar disponible.");

        $recorrido->refresh();
        $this->assertSame('finalizado', $recorrido->estado);
        $this->assertNotNull($recorrido->fin);
        $this->assertSame(1.5, $recorrido->distanciaRecorrida());
        $this->assertSame('disponible', $recorrido->bus->fresh()->estado);
    }

    public function test_no_se_registran_llegadas_en_un_recorrido_finalizado(): void
    {
        $recorrido = $this->recorridoEnCurso();
        app(RecorridoService::class)->finalizar($recorrido);

        $this->actingAs($this->administrador())
            ->post(route('recorridos.llegadas.store', $recorrido), ['estacion_id' => $this->estacionDeOrden($recorrido->linea, 1)->id, 'pasajeros_a_bordo' => 10])
            ->assertSessionHas('error', "El recorrido #{$recorrido->id} no está en curso.");
    }

    public function test_un_bus_en_ruta_no_se_quita_de_su_linea_ni_se_elimina(): void
    {
        $recorrido = $this->recorridoEnCurso();
        $bus = $recorrido->bus;
        $datos = [...$bus->only(['parqueo_id', 'placa', 'codigo', 'capacidad_maxima']), 'estado' => 'en_ruta'];
        $this->actingAs($this->administrador());

        $this->put(route('buses.update', $bus), [...$datos, 'linea_id' => ''])
            ->assertSessionHas('error', "El bus {$bus->codigo} está en ruta (recorrido #{$recorrido->id} en curso). Finalice el recorrido antes de quitarlo o reasignarlo de su línea.");
        $this->put(route('buses.update', $bus), [...$datos, 'linea_id' => 1])->assertSessionHas('error');
        $this->delete(route('buses.destroy', $bus))
            ->assertSessionHas('error', "El bus {$bus->codigo} está en ruta (recorrido #{$recorrido->id} en curso). Finalice el recorrido antes de eliminarlo.");

        $this->assertSame($recorrido->linea_id, $bus->fresh()->linea_id);
        $this->assertModelExists($bus);
    }

    public function test_el_estado_en_ruta_solo_lo_manejan_los_recorridos(): void
    {
        $recorrido = $this->recorridoEnCurso();
        $enRuta = $recorrido->bus;
        $libre = $this->busEn($recorrido->linea);
        $this->actingAs($this->administrador());

        $this->put(route('buses.update', $enRuta), [...$enRuta->only(['linea_id', 'parqueo_id', 'placa', 'codigo', 'capacidad_maxima']), 'estado' => 'disponible'])
            ->assertSessionHasErrors(['estado' => 'El bus está en ruta: su estado cambia al finalizar el recorrido.']);
        $this->put(route('buses.update', $libre), [...$libre->only(['linea_id', 'parqueo_id', 'placa', 'codigo', 'capacidad_maxima']), 'estado' => 'en_ruta'])
            ->assertSessionHasErrors(['estado' => 'Un bus solo queda en ruta al iniciar un recorrido.']);

        $this->assertSame('en_ruta', $enRuta->fresh()->estado);
        $this->assertSame('disponible', $libre->fresh()->estado);
    }

    public function test_el_listado_filtra_por_linea_estado_y_fecha(): void
    {
        $recorrido = $this->recorridoEnCurso();
        $this->actingAs($this->administrador());

        $this->get(route('recorridos.index', ['linea_id' => $recorrido->linea_id, 'estado' => 'en_curso', 'fecha' => now()->format('Y-m-d')]))
            ->assertOk()
            ->assertSee('#'.$recorrido->id)
            ->assertSee($recorrido->bus->codigo);

        $this->get(route('recorridos.index', ['linea_id' => $recorrido->linea_id, 'estado' => 'finalizado']))
            ->assertOk()
            ->assertDontSee($recorrido->bus->codigo);
    }

    public function test_el_detalle_muestra_la_linea_de_tiempo(): void
    {
        $recorrido = $this->recorridoEnCurso(capacidad: 100);
        $primera = $this->estacionDeOrden($recorrido->linea, 1);
        app(RecorridoService::class)->registrarLlegada($recorrido, $primera, 45);

        $this->actingAs($this->operadorTrebol())
            ->get(route('recorridos.show', $recorrido))
            ->assertOk()
            ->assertSee($primera->nombre)
            ->assertSee('45 / 100 pasajeros')
            ->assertSee('45 % · Normal')
            ->assertSee('Siguiente')
            ->assertDontSee('Finalizar recorrido');
    }

    public function test_el_operador_no_inicia_ni_finaliza_recorridos(): void
    {
        $recorrido = $this->recorridoEnCurso();
        $this->actingAs($this->operadorTrebol());

        $this->get(route('recorridos.create'))->assertForbidden();
        $this->post(route('recorridos.store'), [])->assertForbidden();
        $this->patch(route('recorridos.finalizar', $recorrido))->assertForbidden();

        $this->assertSame('en_curso', $recorrido->fresh()->estado);
        $this->assertSame('en_ruta', Bus::find($recorrido->bus_id)->estado);
    }
}
