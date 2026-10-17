<?php

namespace Tests\Feature;

use App\Models\Acceso;
use App\Models\Pasajero;
use App\Models\Tarjeta;
use App\Models\Transaccion;
use App\Models\User;
use App\Services\PagoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tarjeta Transmetro con pagos SIMULADOS. El saldo lo calculan los triggers: las pruebas
 * solo insertan por la aplicación y vuelven a leer la tarjeta de la base.
 */
class TarjetasPagosTest extends TestCase
{
    private const VISA_APROBADA = '4242 4242 4242 4242';

    private int $consecutivo = 0;

    /**
     * Tarjeta nueva (saldo Q0.00) de un pasajero nuevo, con una recarga opcional hecha por el operador de Trébol.
     */
    private function tarjeta(float $recarga = 0): Tarjeta
    {
        $n = ++$this->consecutivo;
        $pasajero = Pasajero::create(['nombre' => "Pasajero de prueba {$n}", 'dpi' => '30000000777'.str_pad((string) $n, 2, '0', STR_PAD_LEFT)]);
        $this->actingAs($this->operadorTrebol());
        $tarjeta = app(PagoService::class)->emitir($pasajero);
        if ($recarga > 0) {
            app(PagoService::class)->recargarEfectivo($tarjeta, $recarga, $this->operadorTrebol());
        }

        return $tarjeta->refresh();
    }

    private function saldo(Tarjeta $tarjeta): string
    {
        return (string) DB::table('tarjetas')->where('id', $tarjeta->id)->value('saldo');
    }

    private function pagoEnLinea(Tarjeta $tarjeta, string $numero, array $extra = [])
    {
        return $this->post(route('recargas.en-linea', $tarjeta), [
            'monto' => 25, 'llave' => (string) Str::uuid(), 'numero' => $numero,
            'vencimiento' => '12/'.substr((string) (now()->year + 3), 2), 'cvv' => '123', 'nombre' => 'PRUEBA', ...$extra,
        ]);
    }

    private function accesoDeTrebol(): Acceso
    {
        return Acceso::where('estacion_id', $this->operadorTrebol()->estacion_id)->firstOrFail();
    }

    // --- Emisión, bloqueo ---

    public function test_emitir_tarjeta_genera_el_siguiente_codigo_y_queda_en_bitacora(): void
    {
        $esperado = Tarjeta::siguienteCodigo();

        $this->actingAs($this->administrador())
            ->post(route('tarjetas.store'), ['nombre' => 'Ana Nueva', 'dpi' => '3000000088801', 'telefono' => '55112233'])
            ->assertSessionHas('ok', "Tarjeta {$esperado} emitida a Ana Nueva.");

        $tarjeta = Tarjeta::where('codigo', $esperado)->firstOrFail();
        $this->assertMatchesRegularExpression('/^TM-\d{6}$/', $tarjeta->codigo);
        $this->assertSame('0.00', $this->saldo($tarjeta));
        $this->assertDatabaseHas('bitacora', ['tabla' => 'tarjetas', 'registro_id' => $tarjeta->id, 'accion' => 'emitir']);
    }

    public function test_bloquear_y_desbloquear_piden_motivo_y_quedan_en_bitacora(): void
    {
        $tarjeta = $this->tarjeta();
        $this->actingAs($this->administrador());

        $this->patch(route('tarjetas.bloquear', $tarjeta), ['motivo' => ''])->assertSessionHasErrors('motivo');
        $this->patch(route('tarjetas.bloquear', $tarjeta), ['motivo' => 'Reporte de extravío'])->assertSessionHas('ok');
        $this->assertSame('bloqueada', $tarjeta->fresh()->estado);
        $this->assertDatabaseHas('bitacora', ['tabla' => 'tarjetas', 'registro_id' => $tarjeta->id, 'detalle' => "Tarjeta {$tarjeta->codigo} bloqueada: Reporte de extravío"]);

        $this->patch(route('tarjetas.desbloquear', $tarjeta), ['motivo' => 'La recuperó el titular'])->assertSessionHas('ok');
        $this->assertSame('activa', $tarjeta->fresh()->estado);
    }

    public function test_el_saldo_no_se_puede_asignar_desde_php(): void
    {
        $this->assertNotContains('saldo', (new Tarjeta)->getFillable());
        $this->assertNotContains('saldo_resultante', (new Transaccion)->getFillable());
    }

    // --- Recarga en efectivo ---

    public function test_la_recarga_en_efectivo_cambia_el_saldo_por_el_trigger(): void
    {
        $tarjeta = $this->tarjeta(10);

        $this->actingAs($this->operadorTrebol())
            ->post(route('recargas.efectivo', $tarjeta), ['monto' => 20])
            ->assertSessionHas('ok', 'Recarga aprobada. Saldo nuevo: Q30.00.');

        $recarga = Transaccion::where('tarjeta_id', $tarjeta->id)->latest('id')->firstOrFail();
        $this->assertSame('30.00', $this->saldo($tarjeta));
        $this->assertSame('30.00', (string) $recarga->saldo_resultante);
        $this->assertSame(['efectivo', $this->operadorTrebol()->estacion_id], [$recarga->metodo, $recarga->estacion_id]);
        $this->get(route('transacciones.show', $recarga))
            ->assertOk()
            ->assertSee($recarga->numeroComprobante())
            ->assertSee('Trébol')
            ->assertSee($this->operadorTrebol()->name)
            ->assertSee('Q30.00');
    }

    public function test_el_monto_va_de_q1_a_q500_y_el_administrador_no_cobra_en_ventanilla(): void
    {
        $tarjeta = $this->tarjeta();
        $this->actingAs($this->operadorTrebol());
        $this->post(route('recargas.efectivo', $tarjeta), ['monto' => 0.5])->assertSessionHasErrors('monto');
        $this->post(route('recargas.efectivo', $tarjeta), ['monto' => 501])->assertSessionHasErrors('monto');

        $this->actingAs($this->administrador())->post(route('recargas.efectivo', $tarjeta), ['monto' => 10])->assertForbidden();
        $this->assertSame('0.00', $this->saldo($tarjeta));
    }

    // --- Recarga en línea (pasarela simulada) ---

    public function test_las_tarjetas_de_prueba_dan_su_resultado(): void
    {
        $tarjeta = $this->tarjeta();
        $this->actingAs($this->administrador());

        $this->pagoEnLinea($tarjeta, self::VISA_APROBADA)->assertSessionHas('ok', 'Recarga aprobada. Saldo nuevo: Q25.00.');
        $this->pagoEnLinea($tarjeta, '4000 0000 0000 0002')->assertSessionHas('error', 'Recarga rechazada: Fondos insuficientes.');
        $this->pagoEnLinea($tarjeta, '4000 0000 0000 0069')->assertSessionHas('error', 'Recarga rechazada: Tarjeta vencida.');
        $this->pagoEnLinea($tarjeta, '5555 5555 5555 4444')->assertSessionHas('ok', 'Recarga aprobada. Saldo nuevo: Q50.00.');

        $movimientos = Transaccion::where('tarjeta_id', $tarjeta->id)->where('metodo', 'tarjeta')->orderBy('id')->get();
        $this->assertSame(['aprobada', 'rechazada', 'rechazada', 'aprobada'], $movimientos->pluck('estado')->all());
        $this->assertSame([null, 'Fondos insuficientes', 'Tarjeta vencida', null], $movimientos->pluck('motivo_rechazo')->all());
        $this->assertSame(['Visa', 'Visa', 'Visa', 'Mastercard'], $movimientos->pluck('tarjeta_marca')->all());
        // Los rechazos no tocaron el saldo.
        $this->assertSame('50.00', $this->saldo($tarjeta));
    }

    public function test_un_numero_que_no_pasa_luhn_no_llega_a_la_pasarela(): void
    {
        $tarjeta = $this->tarjeta();

        $this->actingAs($this->administrador())
            ->pagoEnLinea($tarjeta, '4242 4242 4242 4241')
            ->assertSessionHasErrors(['numero' => 'El número de tarjeta no es válido.']);

        $this->assertSame(0, Transaccion::where('tarjeta_id', $tarjeta->id)->where('metodo', 'tarjeta')->count());
    }

    public function test_la_llave_de_idempotencia_evita_la_doble_recarga(): void
    {
        $tarjeta = $this->tarjeta();
        $llave = (string) Str::uuid();
        $this->actingAs($this->administrador());

        $this->pagoEnLinea($tarjeta, self::VISA_APROBADA, ['llave' => $llave])->assertSessionHas('ok');
        $this->pagoEnLinea($tarjeta, self::VISA_APROBADA, ['llave' => $llave])
            ->assertSessionHas('info', 'Este pago ya se había procesado: no se cobró otra vez.');

        $this->assertSame(1, Transaccion::where('llave_idempotencia', $llave)->count());
        $this->assertSame('25.00', $this->saldo($tarjeta));
    }

    public function test_el_sondeo_de_alertas_no_agota_el_limite_de_las_recargas(): void
    {
        $tarjeta = $this->tarjeta();
        $this->actingAs($this->administrador());

        // Más consultas de alertas que el cupo de 20 recargas por minuto: cada ruta lleva su propio contador.
        for ($i = 0; $i < 25; $i++) {
            $this->getJson(route('alertas.nuevas'))->assertOk();
        }

        $this->pagoEnLinea($tarjeta, self::VISA_APROBADA)->assertSessionHas('ok');
    }

    public function test_en_la_base_nunca_queda_el_numero_completo_ni_el_cvv(): void
    {
        $tarjeta = $this->tarjeta();
        $this->actingAs($this->administrador());
        $this->pagoEnLinea($tarjeta, self::VISA_APROBADA, ['cvv' => '987']);
        $this->pagoEnLinea($tarjeta, '4000 0000 0000 0002', ['cvv' => '987']);

        $filas = DB::table('transacciones')->where('tarjeta_id', $tarjeta->id)->where('metodo', 'tarjeta')->get();
        $bitacora = DB::table('bitacora')->where('tabla', 'transacciones')->whereIn('registro_id', $filas->pluck('id'))->pluck('detalle');
        $todo = json_encode([$filas, $bitacora]);

        $this->assertStringNotContainsString('4242424242424242', $todo);
        $this->assertStringNotContainsString('4000000000000002', $todo);
        $this->assertStringNotContainsString('987', $todo);
        $this->assertSame(['4242', '0002'], $filas->pluck('tarjeta_ultimos4')->all());
        $this->assertTrue($filas->every(fn ($f) => str_starts_with($f->referencia, 'SIM-')));
    }

    public function test_los_datos_de_la_tarjeta_no_se_guardan_en_la_sesion_al_haber_errores(): void
    {
        $tarjeta = $this->tarjeta();

        $this->actingAs($this->administrador())
            ->pagoEnLinea($tarjeta, self::VISA_APROBADA, ['monto' => 9999, 'cvv' => '987'])
            ->assertSessionHasErrors('monto')
            ->assertSessionMissing('_old_input.numero')
            ->assertSessionMissing('_old_input.cvv');
    }

    // --- Torniquete ---

    private function pasar(string $codigo, ?Acceso $acceso = null, ?User $usuario = null)
    {
        return $this->actingAs($usuario ?? $this->operadorTrebol())
            ->postJson(route('torniquete.validar', $acceso ?? $this->accesoDeTrebol()), ['codigo' => $codigo]);
    }

    public function test_pasaje_con_saldo_cobra_el_pasaje(): void
    {
        $tarjeta = $this->tarjeta(5);

        $this->pasar($tarjeta->codigo)->assertOk()->assertJson(['aprobado' => true, 'titulo' => 'Bienvenido', 'mensaje' => 'Saldo: Q4.00']);

        $this->assertSame('4.00', $this->saldo($tarjeta));
        $pasaje = Transaccion::where('tarjeta_id', $tarjeta->id)->where('tipo', 'pago')->firstOrFail();
        $this->assertSame((float) config('transmetro.pasaje'), (float) $pasaje->monto);
        $this->assertSame($this->accesoDeTrebol()->id, $pasaje->acceso_id);
    }

    public function test_pasaje_sin_saldo_se_rechaza_y_se_registra(): void
    {
        $tarjeta = $this->tarjeta();

        $this->pasar(strtolower($tarjeta->codigo))->assertOk()->assertJson(['aprobado' => false, 'titulo' => 'Saldo insuficiente']);

        $this->assertDatabaseHas('transacciones', ['tarjeta_id' => $tarjeta->id, 'tipo' => 'pago', 'estado' => 'rechazada', 'motivo_rechazo' => 'Saldo insuficiente en la tarjeta.']);
        $this->assertSame('0.00', $this->saldo($tarjeta));
    }

    public function test_pasaje_con_tarjeta_bloqueada_se_rechaza_y_se_registra(): void
    {
        $tarjeta = $this->tarjeta(5);
        app(PagoService::class)->bloquear($tarjeta, 'Prueba de bloqueo');

        $this->pasar($tarjeta->codigo)->assertJson(['aprobado' => false, 'titulo' => 'Tarjeta bloqueada']);

        $this->assertDatabaseHas('transacciones', ['tarjeta_id' => $tarjeta->id, 'tipo' => 'pago', 'estado' => 'rechazada', 'motivo_rechazo' => 'La tarjeta no se encuentra activa.']);
        $this->assertSame('5.00', $this->saldo($tarjeta));
    }

    public function test_tarjeta_inexistente(): void
    {
        $antes = Transaccion::count();

        $this->pasar('TM-999999')->assertJson(['aprobado' => false, 'titulo' => 'Tarjeta inexistente']);

        $this->assertSame($antes, Transaccion::count());
    }

    public function test_el_operador_no_usa_el_acceso_de_otra_estacion(): void
    {
        $ajeno = Acceso::where('estacion_id', '!=', $this->operadorTrebol()->estacion_id)->firstOrFail();
        $tarjeta = $this->tarjeta(5);

        $this->actingAs($this->operadorTrebol())->get(route('torniquete.show', $ajeno))->assertForbidden();
        $this->pasar($tarjeta->codigo, $ajeno)->assertForbidden();

        $this->assertSame('5.00', $this->saldo($tarjeta));
        $this->actingAs($this->operadorTrebol())->get(route('torniquete.index'))
            ->assertOk()
            ->assertDontSee('href="'.route('torniquete.show', $ajeno).'"', false);
    }

    // --- Consulta pública ---

    public function test_la_consulta_publica_muestra_saldo_sin_datos_personales(): void
    {
        $tarjeta = $this->tarjeta(12);
        $tarjeta->load('pasajero');
        auth()->logout();

        $this->post(route('consulta-saldo.consultar'), ['codigo' => strtolower($tarjeta->codigo), 'dpi4' => substr($tarjeta->pasajero->dpi, -4)])
            ->assertOk()
            ->assertSee('Q12.00')
            ->assertSee('Recarga en efectivo')
            ->assertDontSee($tarjeta->pasajero->nombre)
            ->assertDontSee($tarjeta->pasajero->dpi);
    }

    public function test_la_consulta_publica_con_dpi_incorrecto_falla(): void
    {
        $tarjeta = $this->tarjeta(12);
        auth()->logout();

        $this->post(route('consulta-saldo.consultar'), ['codigo' => $tarjeta->codigo, 'dpi4' => '0000'])
            ->assertRedirect(route('consulta-saldo'))
            ->assertSessionHasErrors(['codigo' => 'Los datos no coinciden con ninguna tarjeta.']);
    }

    public function test_la_consulta_publica_limita_los_intentos(): void
    {
        foreach (range(1, 5) as $intento) {
            $this->post(route('consulta-saldo.consultar'), ['codigo' => 'TM-000001', 'dpi4' => '0000'])->assertRedirect();
        }

        $this->post(route('consulta-saldo.consultar'), ['codigo' => 'TM-000001', 'dpi4' => '0000'])
            ->assertStatus(429)
            ->assertSee('Demasiados intentos');
    }

    public function test_el_login_enlaza_la_consulta_de_saldo(): void
    {
        $this->get(route('login'))->assertSee('href="'.route('consulta-saldo').'"', false);
        $this->get(route('consulta-saldo'))->assertOk()->assertSee('Consulta de saldo');
    }

    // --- Panel y permisos ---

    public function test_el_panel_muestra_recaudacion_y_rechazos_de_hoy(): void
    {
        $tarjeta = $this->tarjeta(20);
        $this->pasar($tarjeta->codigo);
        $sinSaldo = $this->tarjeta();
        $this->pasar($sinSaldo->codigo);

        $caja = Transaccion::visiblesPara($this->operadorTrebol())->where('created_at', '>=', today());
        $recargas = (clone $caja)->where('tipo', 'recarga')->where('estado', 'aprobada')->sum('monto');
        $rechazados = (clone $caja)->where('estado', 'rechazada')->count();

        $this->actingAs($this->operadorTrebol())->get(route('dashboard'))
            ->assertSee('Recaudación de hoy')
            ->assertSee('Q'.number_format((float) $recargas, 2))
            ->assertSee('href="'.e(route('transacciones.index', ['periodo' => 'hoy', 'estado' => 'rechazada'])).'"', false)
            ->assertSeeInOrder(['Pagos rechazados hoy', (string) $rechazados]);
    }

    public function test_el_operador_no_ve_el_comprobante_de_otra_estacion(): void
    {
        $tarjeta = $this->tarjeta(10);
        $recarga = Transaccion::where('tarjeta_id', $tarjeta->id)->firstOrFail();
        $otro = User::where('email', 'operador.centra.sur@transmetro.gt')->firstOrFail();

        $this->actingAs($otro)->get(route('transacciones.show', $recarga))->assertForbidden();
        $this->actingAs($this->administrador())->get(route('transacciones.show', $recarga))->assertOk();
    }
}
