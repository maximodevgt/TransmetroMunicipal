<?php

namespace Tests\Feature;

use App\Models\Alerta;
use App\Models\Bitacora;
use App\Models\Linea;
use App\Models\Recorrido;
use App\Models\User;
use App\Services\RecorridoService;
use App\Services\ResolucionAlertaService;
use Tests\Feature\Concerns\ConRecorridos;
use Tests\TestCase;

/**
 * "Resolver": cada alerta lleva a la pantalla donde se soluciona (?alerta=ID) y queda atendida al guardar.
 */
class ResolverAlertasTest extends TestCase
{
    use ConRecorridos;

    private function alerta(array $datos): Alerta
    {
        return Alerta::create(['mensaje' => 'Alerta de la prueba.', 'estado' => 'activa', ...$datos]);
    }

    private function bitacoraDe(Alerta $alerta): array
    {
        return Bitacora::where('tabla', 'alertas')->where('registro_id', $alerta->id)->orderBy('id')->pluck('detalle')->all();
    }

    // --- 1. Alta demanda: iniciar recorrido ---

    public function test_alta_demanda_lleva_a_iniciar_recorrido_con_la_linea_elegida_y_la_estacion_resaltada(): void
    {
        $linea = $this->lineaDePrueba();
        $this->busEn($linea);
        $alerta = $this->alerta(['tipo' => 'alta_demanda', 'estacion_id' => $this->estacionDeOrden($linea, 2)->id, 'linea_id' => $linea->id]);

        $accion = app(ResolucionAlertaService::class)->accion($alerta, $this->administrador());
        $this->assertSame(route('recorridos.create', ['linea_id' => $linea->id, 'alerta' => $alerta->id]), $accion['url']);

        $this->actingAs($this->administrador())->get($accion['url'])
            ->assertOk()
            ->assertSee("Resolviendo alerta #{$alerta->id}")
            ->assertSee('<option value="'.$linea->id.'" selected>', false)
            ->assertSee('data-estacion-alerta', false)
            ->assertSee('name="alerta" value="'.$alerta->id.'"', false);
    }

    public function test_al_iniciar_el_recorrido_la_alerta_de_alta_demanda_queda_atendida(): void
    {
        $linea = $this->lineaDePrueba();
        $bus = $this->busEn($linea);
        $estacion = $this->estacionDeOrden($linea, 2);
        $alerta = $this->alerta(['tipo' => 'alta_demanda', 'estacion_id' => $estacion->id, 'linea_id' => $linea->id]);
        $solicitud = $this->alerta(['tipo' => 'asignacion', 'estacion_id' => $estacion->id, 'linea_id' => $linea->id]);

        $respuesta = $this->actingAs($this->administrador())->post(route('recorridos.store'), [
            'linea_id' => $linea->id, 'bus_id' => $bus->id, 'piloto_id' => $this->pilotoNuevo()->id, 'alerta' => $alerta->id,
        ]);

        $recorrido = Recorrido::where('bus_id', $bus->id)->firstOrFail();
        $hecho = "se inició el recorrido #{$recorrido->id} con {$bus->codigo} en la línea {$linea->codigo}";
        $respuesta->assertRedirect(route('alertas.show', $alerta))->assertSessionHas('ok', "Alerta #{$alerta->id} resuelta: {$hecho}.");

        $this->assertSame('atendida', $alerta->fresh()->estado);
        $this->assertNotNull($alerta->fresh()->atendida_en);
        $this->assertContains("Alerta #{$alerta->id} atendida: {$hecho}", $this->bitacoraDe($alerta));
        $this->assertSame('atendida', $solicitud->fresh()->estado, 'La solicitud de unidad de la estación también se cierra.');
    }

    public function test_un_recorrido_de_una_linea_que_no_pasa_por_la_estacion_no_resuelve_la_alerta(): void
    {
        $linea = $this->lineaDePrueba();
        $bus = $this->busEn($linea);
        // Jocotenango no está en la línea de prueba.
        $alerta = $this->alerta(['tipo' => 'alta_demanda', 'estacion_id' => 28, 'linea_id' => 3]);

        $this->actingAs($this->administrador())
            ->post(route('recorridos.store'), ['linea_id' => $linea->id, 'bus_id' => $bus->id, 'piloto_id' => $this->pilotoNuevo()->id, 'alerta' => $alerta->id])
            ->assertSessionHas('error', "Para resolver la alerta #{$alerta->id} el recorrido debe ser de una línea que pase por Jocotenango.");

        $this->assertSame(0, Recorrido::where('bus_id', $bus->id)->count());
        $this->assertSame('activa', $alerta->fresh()->estado);
    }

    // --- 2. Baja ocupación: confirmar la espera ---

    public function test_baja_ocupacion_lleva_a_la_llegada_y_al_confirmar_la_espera_queda_atendida(): void
    {
        $this->actingAs($this->administrador());
        $recorrido = $this->recorridoEnCurso(capacidad: 100);
        $estacion = $this->estacionDeOrden($recorrido->linea, 1);
        [, $alerta] = app(RecorridoService::class)->registrarLlegada($recorrido, $estacion, 10);

        $accion = app(ResolucionAlertaService::class)->accion($alerta, $this->administrador());
        $this->assertSame(route('recorridos.show', ['recorrido' => $recorrido, 'alerta' => $alerta->id]).'#visita-'.$estacion->id, $accion['url']);

        $this->actingAs($this->administrador())->get($accion['url'])
            ->assertOk()
            ->assertSee("Resolviendo alerta #{$alerta->id}")
            ->assertSee('Confirmar espera de 5 min')
            ->assertSee('data-cuenta-regresiva', false);

        $hecho = "se confirmó la espera de 5 min de {$recorrido->bus->codigo} en {$estacion->nombre} (recorrido #{$recorrido->id})";
        $this->post(route('recorridos.confirmar-espera', $recorrido), ['alerta' => $alerta->id])
            ->assertRedirect(route('alertas.show', $alerta))
            ->assertSessionHas('ok', "Alerta #{$alerta->id} resuelta: {$hecho}.");

        $this->assertSame('atendida', $alerta->fresh()->estado);
        $this->assertContains("Alerta #{$alerta->id} atendida: {$hecho}", $this->bitacoraDe($alerta));
    }

    public function test_confirmar_la_espera_en_otro_recorrido_se_rechaza(): void
    {
        $this->actingAs($this->administrador());
        $recorrido = $this->recorridoEnCurso(capacidad: 100);
        [, $alerta] = app(RecorridoService::class)->registrarLlegada($recorrido, $this->estacionDeOrden($recorrido->linea, 1), 10);
        $otro = $this->recorridoEnCurso();

        $this->actingAs($this->administrador())
            ->post(route('recorridos.confirmar-espera', $otro), ['alerta' => $alerta->id])
            ->assertSessionHas('error', "La alerta #{$alerta->id} no corresponde a este recorrido.");

        $this->assertSame('activa', $alerta->fresh()->estado);
    }

    // --- 3. Insuficiencia de unidades: asignar buses ---

    private function lineaConInsuficiencia(int $faltan): array
    {
        $linea = $this->lineaDePrueba($faltan + 1);
        $this->busEn($linea);
        $alerta = $this->alerta(['tipo' => 'insuficiencia_unidades', 'linea_id' => $linea->id]);

        return [$linea, $alerta];
    }

    public function test_insuficiencia_lleva_a_buses_sin_linea_con_lo_que_falta(): void
    {
        [$linea, $alerta] = $this->lineaConInsuficiencia(1);
        $libre = $this->busEn(null);

        $accion = app(ResolucionAlertaService::class)->accion($alerta, $this->administrador());
        $this->assertSame(route('buses.index', ['linea_id' => 'sin', 'alerta' => $alerta->id]), $accion['url']);

        $this->actingAs($this->administrador())->get($accion['url'])
            ->assertOk()
            ->assertSee("La línea {$linea->codigo} necesita 1 bus más")
            ->assertSee("Asignar a {$linea->codigo}")
            ->assertSee('action="'.route('buses.asignar-linea', $libre).'"', false);
    }

    public function test_al_asignar_el_bus_que_completa_el_minimo_la_alerta_queda_atendida(): void
    {
        [$linea, $alerta] = $this->lineaConInsuficiencia(1);
        $libre = $this->busEn(null);

        $this->actingAs($this->administrador())
            ->post(route('buses.asignar-linea', $libre), ['alerta' => $alerta->id])
            ->assertRedirect(route('alertas.show', $alerta))
            ->assertSessionHas('ok', fn ($m) => str_starts_with($m, "Alerta #{$alerta->id} resuelta: se asignó {$libre->codigo} a la línea {$linea->codigo}"));

        $this->assertSame($linea->id, $libre->fresh()->linea_id);
        $this->assertSame('atendida', $alerta->fresh()->estado);
    }

    public function test_si_aun_falta_un_bus_la_alerta_sigue_activa_y_se_sigue_resolviendo(): void
    {
        [$linea, $alerta] = $this->lineaConInsuficiencia(2);
        $libre = $this->busEn(null);

        $this->actingAs($this->administrador())
            ->post(route('buses.asignar-linea', $libre), ['alerta' => $alerta->id])
            ->assertRedirect(route('buses.index', ['linea_id' => 'sin', 'alerta' => $alerta->id]))
            ->assertSessionHas('ok', "{$libre->codigo} asignado a la línea {$linea->codigo}. Falta 1 bus.");

        $this->assertSame('activa', $alerta->fresh()->estado);
    }

    public function test_no_se_asigna_un_bus_que_ya_tiene_linea(): void
    {
        [, $alerta] = $this->lineaConInsuficiencia(1);
        $conLinea = $this->busEn(Linea::where('codigo', 'L12')->firstOrFail());

        $this->actingAs($this->administrador())
            ->post(route('buses.asignar-linea', $conLinea), ['alerta' => $alerta->id])
            ->assertSessionHas('error', "El bus {$conLinea->codigo} ya está asignado a una línea; elija uno sin línea.");

        $this->assertSame('activa', $alerta->fresh()->estado);
    }

    // --- 4. Otras alertas: atender con lo que se hizo ---

    public function test_otras_alertas_se_atienden_en_su_detalle_con_lo_que_se_hizo_y_quedan_en_el_historial(): void
    {
        $alerta = $this->alerta(['tipo' => 'incidencia', 'estacion_id' => $this->operadorTrebol()->estacion_id]);

        $accion = app(ResolucionAlertaService::class)->accion($alerta, $this->operadorTrebol());
        $this->assertSame(route('alertas.show', ['alerta' => $alerta, 'resolver' => 1]), $accion['url']);

        $this->actingAs($this->operadorTrebol())->get($accion['url'])
            ->assertOk()
            ->assertSee('¿Qué se hizo?')
            ->assertSee('autofocus', false);

        $this->from($accion['url'])
            ->patch(route('alertas.atender', $alerta), ['nota' => 'se cambió el lector del torniquete'])
            ->assertRedirect($accion['url'])
            ->assertSessionHas('ok', 'Alerta atendida.');

        $this->assertSame('atendida', $alerta->fresh()->estado);
        $this->get(route('alertas.show', $alerta))
            ->assertSee("Alerta #{$alerta->id} atendida: se cambió el lector del torniquete")
            ->assertSee($this->operadorTrebol()->name);
    }

    public function test_una_alerta_atendida_ya_no_ofrece_resolver(): void
    {
        $alerta = $this->alerta(['tipo' => 'incidencia', 'estacion_id' => 8, 'estado' => 'atendida', 'atendida_en' => now()]);

        $this->assertNull(app(ResolucionAlertaService::class)->accion($alerta, $this->administrador()));
    }

    // --- 5. Permisos ---

    public function test_el_operador_no_inicia_recorridos_ni_asigna_buses(): void
    {
        [, $insuficiencia] = $this->lineaConInsuficiencia(1);
        $alta = $this->alerta(['tipo' => 'alta_demanda', 'estacion_id' => 8, 'linea_id' => 1]);
        $this->actingAs($this->operadorTrebol());

        $this->get(route('recorridos.create', ['alerta' => $alta->id]))->assertForbidden();
        $this->post(route('buses.asignar-linea', $this->busEn(null)), ['alerta' => $insuficiencia->id])->assertForbidden();

        $this->assertSame('activa', $alta->fresh()->estado);
        $this->assertSame('activa', $insuficiencia->fresh()->estado);
    }

    public function test_el_operador_solicita_una_unidad_y_el_administrador_la_recibe_en_vivo(): void
    {
        $operador = $this->operadorTrebol();
        Alerta::where('estacion_id', $operador->estacion_id)->where('tipo', 'asignacion')->update(['estado' => 'atendida']);
        $alta = $this->alerta(['tipo' => 'alta_demanda', 'estacion_id' => $operador->estacion_id, 'linea_id' => 1]);
        $desde = Alerta::max('id');

        $this->assertSame('Solicitar unidad', app(ResolucionAlertaService::class)->accion($alta, $operador)['texto']);
        $this->actingAs($operador)->get(route('alertas.show', $alta))->assertSee('action="'.route('alertas.solicitar-unidad', $alta).'"', false);

        $this->post(route('alertas.solicitar-unidad', $alta))
            ->assertRedirect(route('alertas.show', $alta))
            ->assertSessionHas('ok', 'Solicitud enviada: el administrador recibirá el aviso para enviar otro bus.');

        $solicitud = Alerta::where('tipo', 'asignacion')->where('estacion_id', $operador->estacion_id)->where('estado', 'activa')->firstOrFail();
        $this->assertSame('activa', $alta->fresh()->estado);

        // El administrador la recibe en la consulta en vivo (campana y toast) con su botón Resolver.
        $this->actingAs($this->administrador())
            ->getJson(route('alertas.nuevas', ['desde' => $desde]))
            ->assertJsonFragment(['id' => $solicitud->id, 'tipo' => 'asignacion'])
            ->assertJsonPath('nuevas.0.resolver.texto', 'Resolver');
    }

    public function test_el_operador_solo_resuelve_alertas_de_su_estacion(): void
    {
        $ajena = $this->alerta(['tipo' => 'alta_demanda', 'estacion_id' => 1, 'linea_id' => 1]);
        $this->actingAs($this->administrador());
        $recorrido = $this->recorridoEnCurso(capacidad: 100);
        [, $bajaAjena] = app(RecorridoService::class)->registrarLlegada($recorrido, $this->estacionDeOrden($recorrido->linea, 1), 10);
        $this->actingAs($this->operadorTrebol());

        $this->post(route('alertas.solicitar-unidad', $ajena))->assertForbidden();
        $this->post(route('recorridos.confirmar-espera', $recorrido), ['alerta' => $bajaAjena->id])
            ->assertSessionHas('error', 'La alerta indicada no existe, ya fue atendida o no corresponde a esta acción.');

        $this->assertSame('activa', $ajena->fresh()->estado);
        $this->assertSame('activa', $bajaAjena->fresh()->estado);
        $this->assertSame(0, Alerta::where('tipo', 'asignacion')->where('estacion_id', 1)->where('mensaje', 'like', "%alerta #{$ajena->id}%")->count());
    }

    public function test_el_administrador_no_usa_solicitar_unidad(): void
    {
        $alta = $this->alerta(['tipo' => 'alta_demanda', 'estacion_id' => 8, 'linea_id' => 1]);

        $this->actingAs($this->administrador())->post(route('alertas.solicitar-unidad', $alta))->assertForbidden();
    }

    public function test_el_operador_de_la_estacion_confirma_la_espera_de_un_bus_en_su_estacion(): void
    {
        // La línea de prueba empieza en Centra Sur (estación 1).
        $operador = User::where('email', 'operador.centra.sur@transmetro.gt')->firstOrFail();
        $this->actingAs($this->administrador());
        $recorrido = $this->recorridoEnCurso(capacidad: 100);
        [, $alerta] = app(RecorridoService::class)->registrarLlegada($recorrido, $this->estacionDeOrden($recorrido->linea, 1), 10);

        $this->actingAs($operador)
            ->post(route('recorridos.confirmar-espera', $recorrido), ['alerta' => $alerta->id])
            ->assertRedirect(route('alertas.show', $alerta));

        $this->assertSame('atendida', $alerta->fresh()->estado);
    }
}
