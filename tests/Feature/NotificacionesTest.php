<?php

namespace Tests\Feature;

use App\Models\Alerta;
use App\Models\Bus;
use App\Models\Linea;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Toasts del layout, ventana de confirmación y campana de alertas.
 */
class NotificacionesTest extends TestCase
{
    private function admin(): User
    {
        return User::where('email', 'admin@transmetro.gt')->firstOrFail();
    }

    /**
     * Avisos que el layout entrega a resources/js/notificaciones.js.
     */
    private function avisos(TestResponse $respuesta): array
    {
        preg_match('#<script type="application/json" data-notificaciones-iniciales>(.*?)</script>#s', $respuesta->getContent(), $coincidencia);
        $this->assertNotEmpty($coincidencia, 'El layout no incluye los avisos iniciales.');

        return json_decode($coincidencia[1], true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_el_mensaje_ok_se_entrega_como_toast_de_exito(): void
    {
        $respuesta = $this->actingAs($this->admin())->withSession(['ok' => 'Línea actualizada.'])->get(route('dashboard'));

        $respuesta->assertOk();
        $this->assertSame([['tipo' => 'exito', 'mensaje' => 'Línea actualizada.']], $this->avisos($respuesta));
    }

    public function test_sin_mensajes_no_hay_toasts(): void
    {
        $this->assertSame([], $this->avisos($this->actingAs($this->admin())->get(route('dashboard'))));
    }

    public function test_los_cuatro_tipos_de_sesion_se_convierten_en_toasts(): void
    {
        $respuesta = $this->actingAs($this->admin())->withSession([
            'ok' => 'a', 'error' => 'b', 'advertencia' => 'c', 'info' => 'd',
        ])->get(route('dashboard'));

        $this->assertSame(['exito', 'error', 'advertencia', 'info'], array_column($this->avisos($respuesta), 'tipo'));
    }

    public function test_un_error_de_mysql_del_trigger_sale_como_toast_de_error(): void
    {
        // Línea sin estaciones: su máximo es 0 buses, así que el trigger rechaza cualquier asignación.
        $linea = Linea::create(['municipalidad_id' => 1, 'codigo' => 'LPRB', 'nombre' => 'Línea de prueba', 'estado' => 'activa']);

        $respuesta = $this->actingAs($this->admin())
            ->from(route('buses.create'))
            ->followingRedirects()
            ->post(route('buses.store'), [
                'linea_id' => $linea->id,
                'parqueo_id' => 1,
                'placa' => 'PRB-001',
                'codigo' => 'BUS-PRB',
                'capacidad_maxima' => 80,
                'estado' => 'disponible',
            ]);

        $respuesta->assertOk();
        $this->assertSame([[
            'tipo' => 'error',
            'mensaje' => 'La línea ya tiene el máximo de buses permitido (estaciones × 2).',
        ]], $this->avisos($respuesta));
        $this->assertDatabaseMissing('buses', ['codigo' => 'BUS-PRB']);
    }

    public function test_los_errores_de_validacion_muestran_un_aviso_de_advertencia(): void
    {
        $respuesta = $this->actingAs($this->admin())
            ->from(route('buses.create'))
            ->followingRedirects()
            ->post(route('buses.store'), []);

        $aviso = $this->avisos($respuesta)[0];
        $this->assertSame('advertencia', $aviso['tipo']);
        $this->assertSame('Revise el formulario', $aviso['titulo']);
    }

    public function test_los_toasts_de_error_usan_role_alert_y_los_demas_role_status(): void
    {
        $html = $this->actingAs($this->admin())->get(route('dashboard'))->getContent();

        $this->assertMatchesRegularExpression('#data-plantilla-toast="error">\s*<div data-toast role="alert"#', $html);
        foreach (['exito', 'advertencia', 'info'] as $tipo) {
            $this->assertMatchesRegularExpression("#data-plantilla-toast=\"{$tipo}\">\\s*<div data-toast role=\"status\"#", $html);
        }
        $this->assertStringContainsString('aria-label="Cerrar notificación"', $html);
    }

    public function test_el_layout_incluye_la_ventana_de_confirmacion_y_la_campana(): void
    {
        $this->actingAs(User::where('email', 'operador.trebol@transmetro.gt')->firstOrFail())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-confirmacion', false)
            ->assertSee('data-url="'.route('alertas.nuevas').'"', false)
            ->assertSee('aria-label="Alertas activas"', false)
            ->assertSee('role="switch"', false);
    }

    public function test_eliminar_pide_confirmacion_con_titulo_y_boton_propios(): void
    {
        $bus = Bus::create(['parqueo_id' => 1, 'placa' => 'PRB-002', 'codigo' => 'BUS-PRB2', 'capacidad_maxima' => 80, 'estado' => 'disponible']);

        $this->actingAs($this->admin())
            ->get(route('buses.show', $bus))
            ->assertOk()
            ->assertSee('data-confirmar-titulo="¿Eliminar el bus BUS-PRB2?"', false)
            ->assertSee('data-confirmar-boton="Sí, eliminar"', false)
            ->assertDontSee('window.confirm(', false);
    }

    public function test_las_alertas_del_panel_llevan_a_su_detalle(): void
    {
        $alerta = Alerta::create(['estacion_id' => 8, 'tipo' => 'baja_ocupacion', 'mensaje' => 'Alerta de la prueba.', 'estado' => 'activa']);

        $this->actingAs($this->admin())
            ->get(route('dashboard'))
            ->assertSee('href="'.route('alertas.show', $alerta).'"', false)
            ->assertSee('Baja ocupación');
    }
}
