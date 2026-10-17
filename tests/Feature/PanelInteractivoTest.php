<?php

namespace Tests\Feature;

use App\Models\Acceso;
use App\Models\Alerta;
use App\Models\Bitacora;
use App\Models\Bus;
use App\Models\Parqueo;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Todo lo que muestra el panel lleva a su detalle; la bitácora completa es solo del administrador.
 */
class PanelInteractivoTest extends TestCase
{
    private function panel($usuario): TestResponse
    {
        return $this->actingAs($usuario)->get(route('dashboard'))->assertOk();
    }

    public function test_las_tarjetas_de_indicadores_completas_son_enlaces(): void
    {
        $html = $this->panel($this->administrador())->getContent();

        foreach (['lineas.index' => 'lineas', 'estaciones.index' => 'estaciones', 'buses.index' => 'buses', 'pilotos.index' => 'pilotos'] as $ruta => $indicador) {
            $this->assertMatchesRegularExpression('#<a\s+href="'.preg_quote(route($ruta), '#').'"\s+class="[^"]*clicable[^"]*"\s+data-indicador="'.$indicador.'"#', $html);
        }
    }

    public function test_para_el_operador_la_tarjeta_de_pilotos_no_es_clicable(): void
    {
        $html = $this->panel($this->operadorTrebol())->getContent();

        $this->assertMatchesRegularExpression('#<div\s+class="tarjeta flex items-center gap-4 p-5"\s+data-indicador="pilotos"#', $html);
        $this->assertStringNotContainsString(route('pilotos.index'), $html);
        $this->assertStringContainsString('data-indicador="buses"', $html);
    }

    public function test_el_resumen_por_linea_lleva_a_la_linea_y_a_sus_buses(): void
    {
        $this->panel($this->administrador())
            ->assertSee('href="'.route('lineas.show', 1).'"', false)
            ->assertSee('href="'.route('buses.index', ['linea_id' => 1]).'"', false);
    }

    public function test_el_estado_de_la_flota_lleva_a_buses_filtrados(): void
    {
        $respuesta = $this->panel($this->operadorTrebol());

        foreach (Bus::ESTADOS as $estado) {
            $respuesta->assertSee('href="'.route('buses.index', ['estado' => $estado]).'"', false);
        }
    }

    public function test_cada_alerta_lleva_a_su_detalle_y_se_atiende_desde_el_panel(): void
    {
        $alerta = Alerta::create(['estacion_id' => 8, 'tipo' => 'incidencia', 'mensaje' => 'Alerta del panel.', 'estado' => 'activa']);

        $this->panel($this->operadorTrebol())
            ->assertSee('href="'.route('alertas.show', $alerta).'"', false)
            ->assertSee('action="'.route('alertas.atender', $alerta).'"', false);

        $this->from(route('dashboard'))->patch(route('alertas.atender', $alerta))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('ok', 'Alerta atendida.');
        $this->assertSame('atendida', $alerta->fresh()->estado);
    }

    public function test_la_actividad_reciente_enlaza_solo_lo_que_todavia_existe(): void
    {
        $this->actingAs($this->administrador());
        $existente = Parqueo::create(['nombre' => 'Predio enlace', 'capacidad' => 5]);
        $borrado = Parqueo::create(['nombre' => 'Predio borrado', 'capacidad' => 5]);
        Bitacora::registrar('crear', $existente, 'Parqueo que sigue existiendo');
        Bitacora::registrar('crear', $borrado, 'Parqueo que ya no existe');
        $borrado->delete();

        $this->get(route('dashboard'))
            ->assertSee('href="'.route('parqueos.show', $existente).'"', false)
            ->assertDontSee('href="'.route('parqueos.show', $borrado).'"', false)
            ->assertSee('Parqueo que ya no existe')
            ->assertSee('href="'.route('bitacora.index').'"', false);
    }

    public function test_los_enlaces_de_bitacora_llegan_a_la_estacion_de_accesos_y_guardias(): void
    {
        $this->actingAs($this->administrador());
        $acceso = Acceso::with('guardias')->has('guardias')->firstOrFail();
        Bitacora::registrar('editar', $acceso, 'Acceso de la prueba');
        Bitacora::registrar('editar', $acceso->guardias->first(), 'Guardia de la prueba');

        $enlaces = Bitacora::enlaces(Bitacora::orderByDesc('id')->limit(2)->get());

        $this->assertSame([route('estaciones.show', $acceso->estacion_id)], array_values(array_unique($enlaces)));
    }

    public function test_la_bitacora_filtra_por_usuario_tabla_accion_y_fecha(): void
    {
        $this->actingAs($this->administrador());
        $parqueo = Parqueo::create(['nombre' => 'Predio filtro', 'capacidad' => 5]);
        Bitacora::registrar('crear', $parqueo, 'Registro para filtrar la bitácora');

        $this->get(route('bitacora.index', ['user_id' => $this->administrador()->id, 'tabla' => 'parqueos', 'accion' => 'crear', 'fecha' => now()->format('Y-m-d')]))
            ->assertOk()
            ->assertSee('Registro para filtrar la bitácora');

        $this->get(route('bitacora.index', ['tabla' => 'buses']))
            ->assertOk()
            ->assertDontSee('Registro para filtrar la bitácora');
    }

    public function test_el_operador_no_ve_la_bitacora(): void
    {
        $this->actingAs($this->operadorTrebol());

        $this->get(route('bitacora.index'))->assertForbidden();
        $this->get(route('dashboard'))->assertDontSee('Actividad reciente');
    }
}
