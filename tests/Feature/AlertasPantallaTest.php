<?php

namespace Tests\Feature;

use App\Models\Alerta;
use App\Services\AlertaService;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Pantalla /alertas: filas clicables, Resolver / Atender / Solicitar unidad, contadores, fecha y paginación.
 */
class AlertasPantallaTest extends TestCase
{
    private function alerta(array $datos = []): Alerta
    {
        return Alerta::create(['estacion_id' => 8, 'tipo' => 'incidencia', 'mensaje' => 'Alerta de la pantalla.', 'estado' => 'activa', ...$datos]);
    }

    private function lista(array $filtros = [], $usuario = null): TestResponse
    {
        return $this->actingAs($usuario ?? $this->administrador())->get(route('alertas.index', $filtros))->assertOk();
    }

    public function test_cada_fila_lleva_al_detalle_de_la_alerta(): void
    {
        $alerta = $this->alerta();

        $this->assertMatchesRegularExpression(
            '#data-fila-alerta="'.$alerta->id.'">\s*<td[^>]*>\s*<a href="'.preg_quote(route('alertas.show', $alerta), '#').'" class="enlace-extendido#',
            $this->lista(['estado' => 'activa'])->getContent(),
        );
    }

    public function test_las_activas_tienen_resolver_y_atender_con_ventana(): void
    {
        $alerta = $this->alerta();

        $this->lista(['estado' => 'activa'])
            ->assertSee('href="'.route('alertas.show', ['alerta' => $alerta, 'resolver' => 1]).'"', false)
            ->assertSee('action="'.route('alertas.atender', $alerta).'"', false)
            ->assertSee('data-atender="Alerta #'.$alerta->id.' · Incidencia"', false)
            ->assertSee('data-dialogo-atender', false)
            ->assertSee('¿Qué se hizo?');
    }

    public function test_al_operador_la_alta_demanda_le_ofrece_solicitar_unidad(): void
    {
        $operador = $this->operadorTrebol();
        $alta = $this->alerta(['tipo' => 'alta_demanda', 'estacion_id' => $operador->estacion_id, 'linea_id' => 1]);

        $this->lista(['tipo' => 'alta_demanda', 'estado' => 'activa'], $operador)
            ->assertSee('action="'.route('alertas.solicitar-unidad', $alta).'"', false)
            ->assertSee('Solicitar unidad')
            ->assertDontSee(route('recorridos.create', ['linea_id' => 1, 'alerta' => $alta->id]), false);
    }

    public function test_las_atendidas_muestran_ver_y_quien_las_atendio(): void
    {
        $alerta = $this->alerta();
        $this->actingAs($this->administrador());
        app(AlertaService::class)->atender($alerta->fresh(), 'se revisó en sitio');

        $this->lista(['estado' => 'atendida', 'tipo' => 'incidencia'])
            ->assertSee('href="'.route('alertas.show', $alerta).'" class="btn-secundario', false)
            ->assertSee('por Administrador Municipal')
            ->assertSee('a las '.now()->format('H:i'))
            ->assertDontSee('action="'.route('alertas.atender', $alerta).'"', false);
    }

    public function test_atender_desde_la_lista_guarda_lo_que_se_hizo_y_vuelve_a_la_lista(): void
    {
        $alerta = $this->alerta();

        $this->actingAs($this->administrador())
            ->from(route('alertas.index'))
            ->patch(route('alertas.atender', $alerta), ['nota' => 'se reinició el validador'])
            ->assertRedirect(route('alertas.index'))
            ->assertSessionHas('ok', 'Alerta atendida.');

        $this->assertDatabaseHas('bitacora', ['tabla' => 'alertas', 'registro_id' => $alerta->id, 'detalle' => "Alerta #{$alerta->id} atendida: se reinició el validador"]);
    }

    public function test_los_contadores_cuentan_y_funcionan_como_filtro_rapido(): void
    {
        $operador = $this->operadorTrebol();
        $this->alerta(['tipo' => 'alta_demanda', 'estacion_id' => $operador->estacion_id]);
        $this->alerta(['tipo' => 'baja_ocupacion', 'estacion_id' => 1]);
        $visibles = Alerta::visiblesPara($operador);

        $respuesta = $this->lista([], $operador);

        $esperado = [
            ['estado' => 'activa', 'n' => (clone $visibles)->where('estado', 'activa')->count()],
            ['estado' => 'activa', 'tipo' => 'alta_demanda', 'n' => (clone $visibles)->where('estado', 'activa')->where('tipo', 'alta_demanda')->count()],
        ];
        foreach ($esperado as $caso) {
            $n = $caso['n'];
            unset($caso['n']);
            $this->assertMatchesRegularExpression('#href="'.preg_quote(e(route('alertas.index', $caso)), '#').'" data-contador[^>]*>\s*<p[^>]*>'.$n.'</p>#', $respuesta->getContent());
        }

        // Al usar un contador, queda marcado y la lista se filtra.
        $this->lista(['estado' => 'activa', 'tipo' => 'alta_demanda'], $operador)
            ->assertSee('aria-current="true"', false)
            ->assertDontSee('Insuficiencia de unidades #');
    }

    public function test_por_defecto_primero_las_activas(): void
    {
        $this->alerta(['estado' => 'atendida', 'atendida_en' => now()]);
        $activa = $this->alerta();

        preg_match_all('#data-fila-alerta="(\d+)"#', $this->lista()->getContent(), $filas);
        $estados = Alerta::whereIn('id', $filas[1])->pluck('estado', 'id');

        $this->assertSame('activa', $estados[(int) $filas[1][0]]);
        $this->assertContains((string) $activa->id, $filas[1]);
    }

    public function test_el_filtro_de_fecha_separa_hoy_ultimos_7_dias_y_todas(): void
    {
        $hoy = $this->alerta(['mensaje' => 'Generada hoy.']);
        $hace3 = $this->alerta(['mensaje' => 'Generada hace 3 días.']);
        $hace10 = $this->alerta(['mensaje' => 'Generada hace 10 días.']);
        Alerta::whereKey($hace3->id)->update(['created_at' => now()->subDays(3)]);
        Alerta::whereKey($hace10->id)->update(['created_at' => now()->subDays(10)]);
        $filtro = ['tipo' => 'incidencia', 'estado' => 'activa', 'estacion_id' => 8];

        $this->lista([...$filtro, 'periodo' => 'hoy'])->assertSee('Generada hoy.')->assertDontSee('Generada hace 3 días.')->assertDontSee('Generada hace 10 días.');
        $this->lista([...$filtro, 'periodo' => '7dias'])->assertSee('Generada hoy.')->assertSee('Generada hace 3 días.')->assertDontSee('Generada hace 10 días.');
        $this->lista([...$filtro, 'periodo' => 'todas'])->assertSee('Generada hace 10 días.');
        $this->assertNotNull($hoy);
    }

    public function test_la_lista_se_pagina(): void
    {
        foreach (range(1, 16) as $i) {
            $this->alerta(['mensaje' => "Paginada {$i}."]);
        }

        $this->lista(['tipo' => 'incidencia', 'estado' => 'activa'])
            ->assertSee('page=2', false);
        $this->lista(['tipo' => 'incidencia', 'estado' => 'activa', 'page' => 2])
            ->assertOk();
    }

    public function test_la_barra_superior_es_opaca_para_no_dejar_ver_el_titulo_debajo(): void
    {
        $this->lista()->assertDontSee('bg-white/80 px-8', false)->assertSee('scroll-pt-16', false);
    }
}
