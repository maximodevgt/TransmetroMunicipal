<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Recorrido;
use App\Services\SimuladorService;
use Tests\Feature\Concerns\ConRecorridos;
use Tests\TestCase;

/**
 * Simulador del seguimiento en vivo: usa RecorridoService (registros reales) y guarda el horario en caché.
 */
class SimuladorTest extends TestCase
{
    use ConRecorridos;

    protected function setUp(): void
    {
        parent::setUp();
        mt_srand(2026);
    }

    private function simulador(): SimuladorService
    {
        return app(SimuladorService::class);
    }

    /**
     * Avanza el reloj y un paso de la simulación.
     */
    private function pasar(int $segundos): void
    {
        $this->travel($segundos)->seconds();
        $this->simulador()->avanzar();
    }

    public function test_al_iniciar_pone_buses_en_ruta_con_recorridos_reales(): void
    {
        $estado = $this->simulador()->iniciar(2);

        $this->assertTrue($estado['activo']);
        $this->assertCount(2, $estado['propios']);
        foreach (Recorrido::with(['bus', 'visitas'])->findMany($estado['propios']) as $recorrido) {
            $this->assertSame('en_curso', $recorrido->estado);
            $this->assertSame('en_ruta', $recorrido->bus->estado);
            $this->assertCount(1, $recorrido->visitas, 'Al iniciar llega a la primera estación.');
            $this->assertNotNull($this->simulador()->tramo($recorrido->id));
        }
        $this->assertDatabaseHas('bitacora', ['tabla' => 'recorridos', 'registro_id' => $estado['propios'][0], 'accion' => 'iniciar']);
    }

    public function test_con_el_tiempo_registra_la_llegada_a_la_siguiente_estacion(): void
    {
        $estado = $this->simulador()->iniciar(1);
        $recorrido = Recorrido::findOrFail($estado['propios'][0]);
        $tramo = $this->simulador()->tramo($recorrido->id);

        $this->pasar((int) ceil($tramo['llegada'] - SimuladorService::ahora()) + 1);

        $visitas = $recorrido->visitas()->get();
        $this->assertCount(2, $visitas);
        $this->assertSame($tramo['hacia'], $visitas->last()->estacion_id);
        $this->assertLessThanOrEqual($recorrido->bus->capacidad_maxima, $visitas->last()->pasajeros_a_bordo);
    }

    public function test_al_llegar_a_la_ultima_estacion_finaliza_y_libera_el_bus(): void
    {
        // Un refuerzo en una línea de 2 estaciones: el simulador también lo mueve.
        $this->actingAs($this->administrador());
        $refuerzo = $this->recorridoEnCurso(estaciones: 2);
        $this->simulador()->iniciar(1);

        $this->assertNotNull($this->simulador()->tramo($refuerzo->id), 'El refuerzo se mueve en el mapa.');
        foreach (range(1, 3) as $paso) {
            $this->pasar(60);
        }

        $refuerzo->refresh();
        $this->assertSame('finalizado', $refuerzo->estado);
        $this->assertCount(2, $refuerzo->visitas);
        $this->assertSame('disponible', $refuerzo->bus->fresh()->estado);
    }

    public function test_al_detener_finaliza_sus_recorridos_y_deja_los_buses_disponibles(): void
    {
        $this->actingAs($this->administrador());
        $refuerzo = $this->recorridoEnCurso();
        $estado = $this->simulador()->iniciar(3);
        $propios = $estado['propios'];

        $finalizados = $this->simulador()->detener();

        $this->assertSame(3, $finalizados);
        $this->assertFalse($this->simulador()->activo());
        $this->assertSame(0, Recorrido::whereIn('id', $propios)->where('estado', 'en_curso')->count());
        $this->assertSame(0, Bus::whereIn('id', Recorrido::whereIn('id', $propios)->pluck('bus_id'))->where('estado', '!=', 'disponible')->count());
        $this->assertSame('en_curso', $refuerzo->fresh()->estado, 'Lo que no inició el simulador sigue en curso.');
    }

    public function test_el_comando_simula_y_al_terminar_deja_los_buses_disponibles(): void
    {
        $this->artisan('transmetro:simular', ['--buses' => 2, '--ciclos' => 2, '--intervalo' => 0])
            ->expectsOutputToContain('Simulación iniciada con 2 buses')
            ->expectsOutputToContain('Simulación detenida: 2 recorridos finalizados')
            ->assertSuccessful();

        $this->assertFalse($this->simulador()->activo());
        // Ningún bus queda "en ruta" sin recorrido en curso.
        $this->assertSame(0, Bus::where('estado', 'en_ruta')->whereDoesntHave('recorridoEnCurso')->count());
    }

    public function test_el_boton_de_simulacion_es_solo_del_administrador(): void
    {
        $this->actingAs($this->operadorTrebol())
            ->postJson(route('seguimiento.simulacion'), ['accion' => 'iniciar'])
            ->assertForbidden();
        $this->assertFalse($this->simulador()->activo());

        $this->actingAs($this->administrador())
            ->postJson(route('seguimiento.simulacion'), ['accion' => 'iniciar', 'buses' => 2])
            ->assertOk()
            ->assertJson(['activa' => true]);
        $this->assertTrue($this->simulador()->activo());

        $this->postJson(route('seguimiento.simulacion'), ['accion' => 'detener'])
            ->assertOk()
            ->assertJson(['activa' => false, 'mensaje' => 'Simulación detenida: 2 recorridos finalizados y sus buses quedaron disponibles.']);
    }

    public function test_avanzar_sin_simulacion_activa_no_cambia_nada(): void
    {
        $antes = Recorrido::count();

        $this->actingAs($this->operadorTrebol())
            ->postJson(route('seguimiento.avanzar'))
            ->assertOk()
            ->assertJson(['activa' => false]);

        $this->assertSame($antes, Recorrido::count());
    }
}
