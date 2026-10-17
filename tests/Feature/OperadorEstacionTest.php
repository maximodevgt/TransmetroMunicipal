<?php

namespace Tests\Feature;

use App\Models\Alerta;
use App\Models\RegistroPasajeros;
use App\Models\User;
use Tests\Feature\Concerns\ConRecorridos;
use Tests\TestCase;

/**
 * "Mi estación" y alertas: el operador trabaja solo con su estación.
 */
class OperadorEstacionTest extends TestCase
{
    use ConRecorridos;

    private function operadorCentraSur(): User
    {
        return User::where('email', 'operador.centra.sur@transmetro.gt')->firstOrFail();
    }

    private function alertaEn(int $estacionId): Alerta
    {
        return Alerta::create(['estacion_id' => $estacionId, 'tipo' => 'incidencia', 'mensaje' => 'Alerta de la prueba.', 'estado' => 'activa']);
    }

    public function test_el_operador_registra_pasajeros_solo_en_su_estacion(): void
    {
        $operador = $this->operadorTrebol();

        // Aunque la petición diga otra estación, se guarda en la del operador.
        $this->actingAs($operador)->post(route('mi-estacion.pasajeros'), ['cantidad' => 12, 'estacion_id' => 1])->assertSessionHas('ok');

        $registro = RegistroPasajeros::latest('id')->firstOrFail();
        $this->assertSame($operador->estacion_id, $registro->estacion_id);
        $this->assertSame(12, $registro->cantidad);
    }

    public function test_el_operador_registra_la_llegada_solo_en_su_estacion(): void
    {
        // La línea de prueba empieza en Centra Sur (estación 1).
        $recorrido = $this->recorridoEnCurso();
        $primera = $this->estacionDeOrden($recorrido->linea, 1);

        $this->actingAs($this->operadorTrebol())
            ->post(route('recorridos.llegadas.store', $recorrido), ['estacion_id' => $primera->id, 'pasajeros_a_bordo' => 40])
            ->assertForbidden();
        $this->assertSame(0, $recorrido->visitas()->count());

        $this->actingAs($this->operadorCentraSur())
            ->post(route('recorridos.llegadas.store', $recorrido), ['estacion_id' => $primera->id, 'pasajeros_a_bordo' => 40])
            ->assertSessionHas('ok');
        $this->assertSame(1, $recorrido->visitas()->count());
    }

    public function test_mi_estacion_muestra_los_buses_que_llegan_a_ella(): void
    {
        $recorrido = $this->recorridoEnCurso();

        $this->actingAs($this->operadorCentraSur())
            ->get(route('mi-estacion'))
            ->assertOk()
            ->assertSee('Mi estación: Centra Sur')
            ->assertSee($recorrido->bus->codigo)
            ->assertSee('Llega a esta estación');
    }

    public function test_el_administrador_no_tiene_pantalla_mi_estacion(): void
    {
        $this->actingAs($this->administrador())->get(route('mi-estacion'))->assertForbidden();
    }

    public function test_el_operador_solo_ve_y_atiende_alertas_de_su_estacion(): void
    {
        $operador = $this->operadorTrebol();
        $propia = $this->alertaEn($operador->estacion_id);
        $ajena = $this->alertaEn(1);
        $this->actingAs($operador);

        $this->get(route('alertas.show', $propia))->assertOk();
        $this->get(route('alertas.show', $ajena))->assertForbidden();
        $this->patch(route('alertas.atender', $ajena))->assertForbidden();
        $this->assertSame('activa', $ajena->fresh()->estado);

        $this->get(route('alertas.index'))
            ->assertOk()
            ->assertSee(route('alertas.show', $propia))
            ->assertDontSee(route('alertas.show', $ajena));
    }

    public function test_atender_una_alerta_registra_estado_y_fecha(): void
    {
        $alerta = $this->alertaEn($this->operadorTrebol()->estacion_id);

        $this->actingAs($this->operadorTrebol())
            ->from(route('alertas.show', $alerta))
            ->patch(route('alertas.atender', $alerta))
            ->assertRedirect(route('alertas.show', $alerta))
            ->assertSessionHas('ok', 'Alerta atendida.');

        $alerta->refresh();
        $this->assertSame('atendida', $alerta->estado);
        $this->assertNotNull($alerta->atendida_en);
        $this->assertDatabaseHas('bitacora', ['tabla' => 'alertas', 'registro_id' => $alerta->id, 'accion' => 'atender']);
    }

    public function test_el_listado_de_alertas_filtra_por_tipo_y_estado(): void
    {
        $activa = $this->alertaEn(8);
        $atendida = Alerta::create(['estacion_id' => 8, 'tipo' => 'asignacion', 'mensaje' => 'Atendida de la prueba.', 'estado' => 'atendida', 'atendida_en' => now()]);

        $this->actingAs($this->administrador())
            ->get(route('alertas.index', ['tipo' => 'incidencia', 'estado' => 'activa', 'estacion_id' => 8]))
            ->assertOk()
            ->assertSee(route('alertas.show', $activa))
            ->assertDontSee(route('alertas.show', $atendida));
    }
}
