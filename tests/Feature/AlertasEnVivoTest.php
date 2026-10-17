<?php

namespace Tests\Feature;

use App\Models\Alerta;
use App\Models\User;
use Tests\TestCase;

/**
 * GET /alertas/nuevas: campana y avisos en vivo. Las cuentas se calculan desde la base porque
 * transmetro es de desarrollo y sus alertas cambian.
 */
class AlertasEnVivoTest extends TestCase
{
    private function usuario(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function crearAlerta(int $estacionId, string $tipo = 'alta_demanda', string $estado = 'activa'): Alerta
    {
        return Alerta::create([
            'estacion_id' => $estacionId,
            'linea_id' => 1,
            'tipo' => $tipo,
            'mensaje' => 'Alerta creada por la prueba.',
            'estado' => $estado,
        ]);
    }

    public function test_un_invitado_no_puede_consultar_alertas(): void
    {
        $this->getJson(route('alertas.nuevas'))->assertUnauthorized();
    }

    public function test_sin_desde_devuelve_el_estado_actual_sin_alertas_nuevas(): void
    {
        $respuesta = $this->actingAs($this->usuario('admin@transmetro.gt'))
            ->getJson(route('alertas.nuevas'))
            ->assertOk()
            ->assertJsonStructure([
                'activas', 'ultimo_id', 'nuevas',
                'recientes' => [['id', 'tipo', 'tipo_texto', 'mensaje', 'lugar', 'fecha', 'url']],
            ]);

        $this->assertSame(Alerta::where('estado', 'activa')->count(), $respuesta->json('activas'));
        $this->assertSame(Alerta::where('estado', 'activa')->max('id'), $respuesta->json('ultimo_id'));
        $this->assertSame([], $respuesta->json('nuevas'));
    }

    public function test_con_desde_devuelve_solo_las_alertas_activas_posteriores(): void
    {
        $desde = Alerta::max('id');
        $nueva = $this->crearAlerta(8, 'insuficiencia_unidades');
        $this->crearAlerta(8, 'incidencia', 'atendida');

        $respuesta = $this->actingAs($this->usuario('admin@transmetro.gt'))
            ->getJson(route('alertas.nuevas', ['desde' => $desde]))
            ->assertOk()
            ->assertJsonCount(1, 'nuevas')
            ->assertJsonPath('nuevas.0.id', $nueva->id)
            ->assertJsonPath('nuevas.0.tipo_texto', 'Insuficiencia de unidades')
            ->assertJsonPath('nuevas.0.lugar', 'Estación Trébol · L12')
            ->assertJsonPath('ultimo_id', $nueva->id);

        $this->assertSame(route('dashboard', ['alerta' => $nueva->id]).'#alerta-'.$nueva->id, $respuesta->json('nuevas.0.url'));
    }

    public function test_el_operador_solo_recibe_alertas_de_su_estacion(): void
    {
        $desde = Alerta::max('id');
        $deTrebol = $this->crearAlerta(8);
        $this->crearAlerta(1);

        $this->actingAs($this->usuario('operador.trebol@transmetro.gt'))
            ->getJson(route('alertas.nuevas', ['desde' => $desde]))
            ->assertOk()
            ->assertJsonCount(1, 'nuevas')
            ->assertJsonPath('nuevas.0.id', $deTrebol->id)
            ->assertJsonPath('activas', Alerta::where('estado', 'activa')->where('estacion_id', 8)->count());
    }

    public function test_el_operador_de_otra_estacion_no_ve_las_alertas_ajenas(): void
    {
        $ajena = $this->crearAlerta(8);

        $respuesta = $this->actingAs($this->usuario('operador.centra.sur@transmetro.gt'))
            ->getJson(route('alertas.nuevas', ['desde' => 0]))
            ->assertOk()
            ->assertJsonPath('activas', Alerta::where('estado', 'activa')->where('estacion_id', 1)->count());

        $this->assertNotContains($ajena->id, array_column($respuesta->json('nuevas'), 'id'));
        $this->assertNotContains(8, Alerta::whereIn('id', array_column($respuesta->json('recientes'), 'id'))->pluck('estacion_id')->all());
    }

    public function test_el_panel_muestra_como_maximo_5_alertas_recientes(): void
    {
        foreach (range(1, 6) as $i) {
            $this->crearAlerta(8);
        }

        $this->actingAs($this->usuario('admin@transmetro.gt'))
            ->getJson(route('alertas.nuevas'))
            ->assertOk()
            ->assertJsonCount(5, 'recientes');
    }

    public function test_desde_invalido_responde_422(): void
    {
        $this->actingAs($this->usuario('admin@transmetro.gt'))
            ->getJson(route('alertas.nuevas', ['desde' => 'abc']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('desde');
    }
}
