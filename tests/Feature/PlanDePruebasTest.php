<?php

namespace Tests\Feature;

use App\Models\Acceso;
use App\Models\Alerta;
use App\Models\Bitacora;
use App\Models\Bus;
use App\Models\Estacion;
use App\Models\Guardia;
use App\Models\Linea;
use App\Models\Parqueo;
use App\Models\Pasajero;
use App\Models\Piloto;
use App\Models\Recorrido;
use App\Models\Tarjeta;
use App\Models\Transaccion;
use App\Models\User;
use App\Services\RecorridoService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Casos de prueba CP-01 a CP-50 del plan de pruebas (E7, sección 15.9), con los pasos y los datos
 * del plan. Los datos son los iniciales del script; cuando la base de desarrollo ya no los tiene
 * (por ejemplo, un saldo), la prueba los prepara dentro de su transacción, que se revierte al terminar.
 * CP-46 y CP-48 se completan en el navegador (tiempos y compatibilidad): ver docs/resultados_pruebas.md.
 */
class PlanDePruebasTest extends TestCase
{
    private function linea(string $codigo): Linea
    {
        return Linea::where('codigo', $codigo)->firstOrFail();
    }

    private function bus(string $codigo): Bus
    {
        return Bus::where('codigo', $codigo)->firstOrFail();
    }

    private function tarjeta(string $codigo): Tarjeta
    {
        return Tarjeta::where('codigo', $codigo)->firstOrFail();
    }

    private function saldo(string $codigo): string
    {
        return (string) DB::table('tarjetas')->where('codigo', $codigo)->value('saldo');
    }

    /** Deja la tarjeta con el saldo de los datos iniciales registrando un movimiento (el trigger calcula el saldo). */
    private function conSaldoInicial(string $codigo, float $inicial): void
    {
        $tarjeta = $this->tarjeta($codigo);
        $diferencia = round($inicial - (float) $tarjeta->saldo, 2);
        if ($diferencia != 0.0) {
            DB::table('transacciones')->insert(['tarjeta_id' => $tarjeta->id, 'tipo' => $diferencia > 0 ? 'recarga' : 'pago',
                'monto' => abs($diferencia), 'estado' => 'aprobada', 'created_at' => now()->subDay()]);
        }
        $this->assertSame(number_format($inicial, 2, '.', ''), $this->saldo($codigo));
    }

    private function orden(Linea $linea): array
    {
        return DB::table('linea_estacion')->where('linea_id', $linea->id)->orderBy('orden')->pluck('estacion_id')->all();
    }

    private function datosBus(array $cambios = []): array
    {
        return ['linea_id' => '', 'parqueo_id' => 1, 'placa' => 'QA-900', 'codigo' => 'BUS-QA900', 'capacidad_maxima' => 100, 'estado' => 'disponible', ...$cambios];
    }

    private function editarBus(Bus $bus, array $cambios)
    {
        return $this->put(route('buses.update', $bus), [
            'linea_id' => $bus->linea_id, 'parqueo_id' => $bus->parqueo_id, 'placa' => $bus->placa, 'codigo' => $bus->codigo,
            'capacidad_maxima' => $bus->capacidad_maxima, 'estado' => $bus->estado, ...$cambios,
        ]);
    }

    private function recorridoL12(string $bus): Recorrido
    {
        $piloto = Piloto::where('estado', 'activo')->whereDoesntHave('recorridos', fn ($q) => $q->where('estado', 'en_curso'))->orderBy('id')->firstOrFail();

        return app(RecorridoService::class)->iniciar($this->linea('L12'), $this->bus($bus), $piloto);
    }

    // --- 15.9.1 Autenticación y roles ---

    public function test_cp_01_inicio_de_sesion_valido(): void
    {
        $this->post('/login', ['email' => 'admin@transmetro.gt', 'password' => 'Transmetro2026'])->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk()->assertSee('<title>Panel · Transmetro Municipal</title>', false)->assertSee('Hola, Administrador Municipal');
    }

    public function test_cp_02_contrasena_incorrecta(): void
    {
        $this->post('/login', ['email' => 'admin@transmetro.gt', 'password' => 'incorrecta'])
            ->assertSessionHasErrors(['email' => 'Las credenciales no son válidas.']);
        $this->assertGuest();
    }

    public function test_cp_03_usuario_desactivado(): void
    {
        // RN-17: la estación no puede quedarse sin operador, así que antes se le asigna otro.
        User::create(['name' => 'Operador de relevo', 'email' => 'relevo.trebol@transmetro.gt', 'password' => 'Transmetro2026', 'rol' => 'operador', 'estacion_id' => 8, 'activo' => true]);
        $operador = $this->operadorTrebol();
        $this->actingAs($this->administrador())->put(route('usuarios.update', $operador), [
            'name' => $operador->name, 'email' => $operador->email, 'rol' => 'operador', 'estacion_id' => 8, 'activo' => '0',
        ])->assertSessionHas('ok');
        $this->post('/logout');

        $this->post('/login', ['email' => $operador->email, 'password' => 'Transmetro2026'])
            ->assertSessionHasErrors(['email' => 'Su usuario está desactivado. Comuníquese con el administrador.']);
        $this->assertGuest();
    }

    public function test_cp_04_acceso_sin_sesion(): void
    {
        $this->get('/buses')->assertRedirect(route('login'));
    }

    public function test_cp_05_permisos_del_operador(): void
    {
        $this->actingAs($this->operadorTrebol());
        $this->get(route('pilotos.index'))->assertForbidden()->assertSee('Acceso restringido');
        $this->get(route('buses.create'))->assertForbidden()->assertSee('Acceso restringido');
        $this->post(route('buses.store'), $this->datosBus())->assertForbidden();
        $this->assertDatabaseMissing('buses', ['codigo' => 'BUS-QA900']);
    }

    public function test_cp_06_intentos_fallidos_repetidos(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->post('/login', ['email' => 'admin@transmetro.gt', 'password' => 'incorrecta'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => 'admin@transmetro.gt', 'password' => 'incorrecta'])->assertStatus(429)->assertSee('Demasiados intentos');
    }

    // --- 15.9.2 Líneas, estaciones y accesos ---

    public function test_cp_07_codigo_de_linea_duplicado(): void
    {
        $lineas = Linea::count();
        $this->actingAs($this->administrador())
            ->post(route('lineas.store'), ['municipalidad_id' => 1, 'codigo' => 'L12', 'nombre' => 'Línea duplicada', 'estado' => 'activa'])
            ->assertSessionHasErrors(['codigo' => 'Ya existe un registro con ese valor de código.']);
        $this->assertSame($lineas, Linea::count());
    }

    public function test_cp_08_y_cp_09_agregar_y_quitar_una_estacion_en_medio_de_l2(): void
    {
        $l2 = $this->linea('L2');
        $antes = $this->orden($l2);
        $distancia = (float) DB::table('vw_resumen_lineas')->where('id', $l2->id)->value('distancia_total_km');
        $this->actingAs($this->administrador());

        // CP-08: estación nueva en la posición 3 de L2.
        $this->post(route('estaciones.store'), ['municipalidad_id' => 1, 'nombre' => 'Estación CP-08', 'estado' => 'activa',
            'linea_id' => $l2->id, 'orden' => 3, 'distancia_anterior_km' => 0.45])->assertSessionHas('ok', 'Estación creada.');
        $nueva = Estacion::where('nombre', 'Estación CP-08')->firstOrFail();
        $this->assertSame([$antes[0], $antes[1], $nueva->id, ...array_slice($antes, 2)], $this->orden($l2), 'Las siguientes se corren una posición.');
        $this->assertSame(range(1, count($antes) + 1), DB::table('linea_estacion')->where('linea_id', $l2->id)->orderBy('orden')->pluck('orden')->all());
        $this->assertEquals(round($distancia + 0.45, 2), (float) DB::table('vw_resumen_lineas')->where('id', $l2->id)->value('distancia_total_km'));

        // CP-09: quitar la estación agregada. Como L2 es su única línea (RN-02), se quita eliminándola.
        $this->delete(route('lineas.estaciones.destroy', [$l2, $nueva]))->assertSessionHas('error');
        $this->delete(route('estaciones.destroy', $nueva))->assertSessionHas('ok', 'Estación eliminada.');
        $this->assertSame($antes, $this->orden($l2));
        $this->assertSame(range(1, count($antes)), DB::table('linea_estacion')->where('linea_id', $l2->id)->orderBy('orden')->pluck('orden')->all());
    }

    public function test_cp_10_campos_obligatorios(): void
    {
        $this->actingAs($this->administrador())
            ->post(route('estaciones.store'), ['municipalidad_id' => 1, 'estado' => 'activa', 'linea_id' => $this->linea('L2')->id, 'distancia_anterior_km' => 1])
            ->assertSessionHasErrors(['nombre' => 'El campo nombre es obligatorio.']);
    }

    public function test_cp_11_estacion_en_varias_lineas(): void
    {
        $sanSebastian = Estacion::where('nombre', 'San Sebastián')->firstOrFail();
        $this->assertEqualsCanonicalizing(['L1', 'L2'], $sanSebastian->lineas()->pluck('codigo')->all());

        $this->actingAs($this->administrador())->get(route('estaciones.show', $sanSebastian))->assertOk()->assertSee('L1')->assertSee('L2');
    }

    public function test_cp_12_y_cp_13_registrar_acceso_y_guardia(): void
    {
        $trebol = Estacion::where('nombre', 'Trébol')->firstOrFail();
        $this->actingAs($this->administrador());

        // CP-12 (RN-11: el acceso nace con su primer guardia).
        $this->post(route('estaciones.accesos.store', $trebol), ['nombre' => 'Acceso Oriente',
            'guardia_nombre' => 'Guardia CP-12', 'guardia_dpi' => '3000000012120', 'guardia_telefono' => '55001212', 'guardia_turno' => 'matutino'])
            ->assertSessionHas('ok');
        $acceso = Acceso::where('estacion_id', $trebol->id)->where('nombre', 'Acceso Oriente')->firstOrFail();
        $this->get(route('estaciones.show', $trebol))->assertSee('Acceso Oriente');
        $this->get(route('lineas.show', $this->linea('L12')))->assertSee('Acceso Oriente');

        // CP-13
        $this->post(route('accesos.guardias.store', $acceso), ['nombre' => 'Guardia CP-13', 'dpi' => '3000000013130', 'telefono' => '55001313', 'turno' => 'nocturno'])
            ->assertSessionHas('ok');
        $this->assertDatabaseHas('guardias', ['acceso_id' => $acceso->id, 'nombre' => 'Guardia CP-13', 'turno' => 'nocturno']);
        $this->get(route('estaciones.show', $trebol))->assertSee('Guardia CP-13')->assertSee('Nocturno');
    }

    public function test_cp_14_acceso_sin_guardia(): void
    {
        $acceso = Acceso::has('guardias', '=', 1)->with('guardias')->firstOrFail();
        $guardia = $acceso->guardias->first();

        $this->actingAs($this->administrador())->delete(route('guardias.destroy', $guardia))
            ->assertSessionHas('error', "No se puede eliminar a {$guardia->nombre}: es el último guardia del acceso {$acceso->nombre} y todo acceso debe tener al menos uno (RN-11).");
        $this->assertModelExists($guardia);
    }

    // --- 15.9.3 Buses, parqueos y pilotos ---

    public function test_cp_15_superar_el_maximo_de_buses(): void
    {
        $l2 = $this->linea('L2');
        // El plan supone L2 con 10 buses (su máximo); los datos iniciales traen 5: se completan antes.
        for ($n = $l2->buses()->count(); $n < 10; $n++) {
            Bus::create(['linea_id' => $l2->id, 'parqueo_id' => 3, 'placa' => "QA-L2{$n}", 'codigo' => "BUS-QAL2{$n}", 'capacidad_maxima' => 80, 'estado' => 'disponible']);
        }
        $this->assertSame(10, $l2->buses()->count());

        $this->actingAs($this->administrador())->post(route('buses.store'), $this->datosBus(['linea_id' => $l2->id]))
            ->assertSessionHas('error', 'La línea ya tiene el máximo de buses permitido (estaciones × 2).');
        $this->assertSame(10, $l2->buses()->count());
    }

    public function test_cp_16_asignar_bus_a_una_linea(): void
    {
        $l12 = $this->linea('L12');
        $bus = $this->bus('BUS-132');
        $this->assertNull($bus->linea_id);
        $antes = $l12->buses()->count();

        $this->actingAs($this->administrador())->editarBus($bus, ['linea_id' => $l12->id])->assertSessionHas('ok', 'Bus actualizado.');
        $this->assertSame($antes + 1, $l12->buses()->count());
        $this->assertSame(17, $antes + 1, 'Con los datos iniciales L12 pasa de 16 a 17 buses.');
    }

    public function test_cp_17_bus_sin_parqueo(): void
    {
        $this->actingAs($this->administrador())->post(route('buses.store'), $this->datosBus(['parqueo_id' => '']))
            ->assertSessionHasErrors(['parqueo_id' => 'El campo parqueo es obligatorio.']);
    }

    public function test_cp_18_cambiar_de_parqueo(): void
    {
        $bus = $this->bus('BUS-101');
        $centro = Parqueo::where('nombre', 'like', 'Predio Centro Hist%')->firstOrFail();

        $this->actingAs($this->administrador())->editarBus($bus, ['parqueo_id' => $centro->id])->assertSessionHas('ok');
        $this->assertSame($centro->id, (int) $bus->fresh()->parqueo_id);
        $this->editarBus($bus->fresh(), ['parqueo_id' => ''])->assertSessionHasErrors('parqueo_id');
        $this->assertSame($centro->id, (int) $bus->fresh()->parqueo_id, 'Nunca queda sin parqueo.');
    }

    public function test_cp_19_menos_buses_que_el_minimo(): void
    {
        $l1 = $this->linea('L1');
        $this->assertSame(10, $l1->buses()->count());
        $bus = $l1->buses()->where('estado', '!=', 'en_ruta')->orderBy('id')->firstOrFail();

        $this->actingAs($this->administrador())->editarBus($bus, ['linea_id' => ''])->assertSessionHas('ok');
        $this->assertSame(9, $l1->buses()->count());
        $this->assertDatabaseHas('alertas', ['linea_id' => $l1->id, 'tipo' => 'insuficiencia_unidades', 'estado' => 'activa']);
    }

    public function test_cp_20_placa_duplicada(): void
    {
        $this->actingAs($this->administrador())->post(route('buses.store'), $this->datosBus(['placa' => 'TM-101']))
            ->assertSessionHasErrors(['placa' => 'Ya existe un registro con ese valor de placa.']);
    }

    public function test_cp_21_registrar_parqueo(): void
    {
        $this->actingAs($this->administrador())->post(route('parqueos.store'), ['nombre' => 'Predio CP-21', 'direccion' => 'Zona 4', 'capacidad' => 12])->assertSessionHas('ok');
        $parqueo = Parqueo::where('nombre', 'Predio CP-21')->firstOrFail();

        $this->get(route('parqueos.index'))->assertSee('Predio CP-21')->assertSee('0 / 12');
        $this->get(route('parqueos.show', $parqueo))->assertOk()->assertSee('12');
    }

    public function test_cp_22_registrar_piloto_con_historial(): void
    {
        $this->actingAs($this->administrador())->post(route('pilotos.store'), ['nombre' => 'Piloto CP-22', 'dpi' => '3000000022220', 'licencia' => 'A-CP22',
            'residencia' => 'Zona 5, Guatemala', 'telefono' => '55002222', 'email' => 'cp22@transmetro.gt', 'estado' => 'activo'])->assertSessionHas('ok');
        $piloto = Piloto::where('dpi', '3000000022220')->firstOrFail();
        $this->post(route('pilotos.historial.store', $piloto), ['nivel' => 'primaria', 'institucion' => 'Escuela CP-22', 'titulo' => 'Sexto Primaria', 'anio' => 2005]);
        $this->post(route('pilotos.historial.store', $piloto), ['nivel' => 'diversificado', 'institucion' => 'Instituto CP-22', 'titulo' => 'Bachiller', 'anio' => 2011]);

        $this->assertSame(2, $piloto->historialEducativo()->count());
        $this->get(route('pilotos.show', $piloto))->assertSee('Zona 5, Guatemala')->assertSee('55002222')->assertSee('Escuela CP-22')->assertSee('Instituto CP-22');
    }

    public function test_cp_23_dpi_duplicado(): void
    {
        $this->actingAs($this->administrador())->post(route('pilotos.store'), ['nombre' => 'Piloto CP-23', 'dpi' => '2000340530101', 'licencia' => 'A-CP23',
            'residencia' => 'Zona 1', 'telefono' => '55002323', 'estado' => 'activo'])
            ->assertSessionHasErrors(['dpi' => 'Ya existe un registro con ese valor de DPI.']);
    }

    // --- 15.9.4 Recorridos y seguimiento de unidades ---

    public function test_cp_24_a_cp_28_recorrido_completo_de_bus_103(): void
    {
        $l12 = $this->linea('L12');
        $bus = $this->bus('BUS-103');
        $piloto = Piloto::where('estado', 'activo')->whereDoesntHave('recorridos', fn ($q) => $q->where('estado', 'en_curso'))->orderBy('id')->firstOrFail();
        $this->actingAs($this->administrador());

        // CP-24: iniciar.
        $this->post(route('recorridos.store'), ['linea_id' => $l12->id, 'bus_id' => $bus->id, 'piloto_id' => $piloto->id])->assertSessionHas('ok');
        $recorrido = Recorrido::where('bus_id', $bus->id)->where('estado', 'en_curso')->firstOrFail();
        $this->assertSame('en_ruta', $bus->fresh()->estado);

        // CP-25: llegada a la primera estación con 45 pasajeros.
        $primera = $l12->estaciones()->wherePivot('orden', 1)->firstOrFail();
        $this->post(route('recorridos.llegadas.store', $recorrido), ['estacion_id' => $primera->id, 'pasajeros_a_bordo' => 45])->assertSessionHas('ok');
        $visita = $recorrido->visitas()->firstOrFail();
        $this->assertSame([45, $primera->id], [$visita->pasajeros_a_bordo, $visita->estacion_id]);
        $this->assertNotNull($visita->hora_llegada);

        // CP-26: seguimiento.
        $this->get(route('seguimiento.index'))->assertOk();
        $datos = collect($this->getJson(route('seguimiento.datos'))->assertOk()->json('buses'))->firstWhere('codigo', 'BUS-103');
        $this->assertSame('L12', $datos['linea']);
        $this->assertSame($primera->nombre, $datos['estacion_actual']);
        $this->assertSame($l12->estaciones()->wherePivot('orden', 2)->value('nombre'), $datos['proxima_estacion']);
        $this->assertSame(45, $datos['ocupacion']);
        $this->assertSame($visita->hora_llegada->format('H:i:s'), $datos['actualizado']);
        $this->assertIsArray($datos['alertas']);

        // CP-28: otro recorrido con el bus en ruta.
        $otro = Piloto::where('estado', 'activo')->whereKeyNot($piloto->id)->whereDoesntHave('recorridos', fn ($q) => $q->where('estado', 'en_curso'))->firstOrFail();
        $this->post(route('recorridos.store'), ['linea_id' => $l12->id, 'bus_id' => $bus->id, 'piloto_id' => $otro->id])
            ->assertSessionHas('error', "El bus BUS-103 ya está en ruta (recorrido #{$recorrido->id} en curso).");

        // CP-27: finalizar.
        $this->patch(route('recorridos.finalizar', $recorrido))->assertSessionHas('ok');
        $this->assertSame('finalizado', $recorrido->fresh()->estado);
        $this->assertSame('disponible', $bus->fresh()->estado);
    }

    // --- 15.9.5 Capacidad de pasajeros y alertas ---

    public function test_cp_29_y_cp_32_alta_demanda_y_atender_la_alerta(): void
    {
        $operador = $this->operadorTrebol();

        // CP-29: 160 en espera con referencia de 100 (160 %). Los datos iniciales ya tienen una alerta activa de Trébol:
        // no se duplica, se actualiza con la nueva cantidad.
        $this->actingAs($operador)->post(route('mi-estacion.pasajeros'), ['cantidad' => 160])->assertSessionHas('ok');
        $alerta = Alerta::where('estacion_id', $operador->estacion_id)->where('tipo', 'alta_demanda')->where('estado', 'activa')->firstOrFail();
        $this->assertSame('Estación Trébol con 160 pasajeros en espera (160 % de 100): enviar otro bus.', $alerta->mensaje);

        // CP-32
        $this->patch(route('alertas.atender', $alerta), ['nota' => 'Se envió otra unidad (CP-32)'])->assertSessionHas('ok', 'Alerta atendida.');
        $this->assertSame('atendida', $alerta->fresh()->estado);
        $this->assertNotNull($alerta->fresh()->atendida_en);
    }

    public function test_cp_30_baja_ocupacion(): void
    {
        $recorrido = $this->recorridoL12('BUS-104');
        $primera = $recorrido->linea->estaciones()->wherePivot('orden', 1)->firstOrFail();

        $this->actingAs($this->administrador())->post(route('recorridos.llegadas.store', $recorrido), ['estacion_id' => $primera->id, 'pasajeros_a_bordo' => 20]);
        $this->assertDatabaseHas('alertas', ['bus_id' => $recorrido->bus_id, 'tipo' => 'baja_ocupacion', 'estado' => 'activa']);
        $this->assertSame(5, $recorrido->visitas()->firstOrFail()->espera_extra_min);
    }

    public function test_cp_31_ocupacion_normal(): void
    {
        $recorrido = $this->recorridoL12('BUS-105');
        $primera = $recorrido->linea->estaciones()->wherePivot('orden', 1)->firstOrFail();
        $antes = Alerta::count();

        $this->actingAs($this->administrador())->post(route('recorridos.llegadas.store', $recorrido), ['estacion_id' => $primera->id, 'pasajeros_a_bordo' => 60]);
        $this->assertSame($antes, Alerta::count());
        $this->assertSame(0, $recorrido->visitas()->firstOrFail()->espera_extra_min);
    }

    public function test_cp_33_alertas_por_estacion(): void
    {
        $trebol = Alerta::create(['estacion_id' => 8, 'tipo' => 'incidencia', 'mensaje' => 'Incidencia CP-33 en Trébol', 'estado' => 'activa']);
        $centraSur = User::where('email', 'operador.centra.sur@transmetro.gt')->firstOrFail();

        $this->actingAs($centraSur)->get(route('alertas.index'))->assertOk()->assertDontSee('Incidencia CP-33 en Trébol');
        $this->get(route('alertas.show', $trebol))->assertForbidden();
        $this->actingAs($this->operadorTrebol())->get(route('alertas.index'))->assertSee('Incidencia CP-33 en Trébol');
    }

    // --- 15.9.6 Tarjeta Transmetro ---

    public function test_cp_34_recarga(): void
    {
        $this->conSaldoInicial('TM-000004', 4.00);
        $tarjeta = $this->tarjeta('TM-000004');

        $this->actingAs($this->operadorTrebol())->post(route('recargas.efectivo', $tarjeta), ['monto' => 10])
            ->assertSessionHas('ok', 'Recarga aprobada. Saldo nuevo: Q14.00.');
        $this->assertSame('14.00', $this->saldo('TM-000004'));
        $this->assertDatabaseHas('transacciones', ['tarjeta_id' => $tarjeta->id, 'tipo' => 'recarga', 'monto' => 10, 'estado' => 'aprobada', 'saldo_resultante' => 14]);
    }

    public function test_cp_35_pago_con_saldo(): void
    {
        $this->conSaldoInicial('TM-000001', 18.00);
        $acceso = Acceso::where('estacion_id', 8)->firstOrFail();

        $this->actingAs($this->operadorTrebol())->postJson(route('torniquete.validar', $acceso), ['codigo' => 'TM-000001'])
            ->assertOk()->assertJson(['aprobado' => true, 'titulo' => 'Bienvenido', 'mensaje' => 'Saldo: Q17.00']);
        $this->assertSame('17.00', $this->saldo('TM-000001'));
    }

    public function test_cp_36_pago_con_tarjeta_bloqueada(): void
    {
        $saldo = $this->saldo('TM-000005');
        $this->assertSame('bloqueada', $this->tarjeta('TM-000005')->estado);
        $acceso = Acceso::where('estacion_id', 8)->firstOrFail();

        $this->actingAs($this->operadorTrebol())->postJson(route('torniquete.validar', $acceso), ['codigo' => 'TM-000005'])
            ->assertOk()->assertJson(['aprobado' => false, 'titulo' => 'Tarjeta bloqueada']);
        $this->assertSame($saldo, $this->saldo('TM-000005'));
        $this->assertSame('La tarjeta no se encuentra activa.', Transaccion::where('tarjeta_id', $this->tarjeta('TM-000005')->id)->latest('id')->value('motivo_rechazo'));
    }

    public function test_cp_37_saldo_insuficiente(): void
    {
        $this->conSaldoInicial('TM-000002', 9.00);
        $tarjeta = $this->tarjeta('TM-000002');

        // Pago de Q50.00: lo rechaza el trigger de la base aunque llegue sin pasar por la aplicación.
        try {
            DB::table('transacciones')->insert(['tarjeta_id' => $tarjeta->id, 'tipo' => 'pago', 'monto' => 50, 'estado' => 'aprobada']);
            $this->fail('El pago de Q50.00 con Q9.00 debía rechazarse.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('Saldo insuficiente en la tarjeta.', $e->getMessage());
        }
        $this->assertSame('9.00', $this->saldo('TM-000002'));
    }

    public function test_cp_38_cambiar_estado_de_tarjeta(): void
    {
        $tarjeta = $this->tarjeta('TM-000003');

        $this->actingAs($this->administrador())->patch(route('tarjetas.estado', $tarjeta), ['estado' => 'suspendida', 'motivo' => 'Prueba CP-38'])
            ->assertSessionHas('ok', 'Tarjeta TM-000003: estado cambiado a suspendida.');
        $this->assertSame('suspendida', $tarjeta->fresh()->estado);

        $this->actingAs($this->operadorTrebol())->patch(route('tarjetas.estado', $tarjeta), ['estado' => 'activa', 'motivo' => 'Prueba CP-38'])->assertForbidden();
        $this->assertSame('suspendida', $tarjeta->fresh()->estado);
        $this->get(route('tarjetas.show', $tarjeta))->assertOk()->assertDontSee('data-cambiar-estado', false);
    }

    public function test_cp_39_historial_de_movimientos(): void
    {
        $tarjeta = $this->tarjeta('TM-000001');
        $movimiento = $tarjeta->transacciones()->latest('id')->firstOrFail();

        $this->actingAs($this->administrador())->get(route('tarjetas.show', $tarjeta))->assertOk()
            ->assertSee('Fecha')->assertSee('Movimiento')->assertSee('Monto')->assertSee('Saldo')->assertSee('Estado')
            ->assertSee($movimiento->created_at->format('d/m/Y H:i'))
            ->assertSee('Q'.number_format((float) $movimiento->monto, 2))
            ->assertSee('Q'.number_format((float) $movimiento->saldo_resultante, 2));
    }

    public function test_cp_40_emitir_tarjeta(): void
    {
        $pasajero = Pasajero::orderBy('id')->firstOrFail();
        $codigo = Tarjeta::siguienteCodigo();

        $this->actingAs($this->administrador())->post(route('tarjetas.store'), ['pasajero_id' => $pasajero->id])->assertSessionHas('ok');
        $tarjeta = Tarjeta::where('codigo', $codigo)->firstOrFail();
        $this->assertSame([$pasajero->id, 'activa', '0.00'], [(int) $tarjeta->pasajero_id, $tarjeta->estado, (string) $tarjeta->saldo]);
        $this->assertSame(1, Tarjeta::where('codigo', $codigo)->count());
    }

    // --- 15.9.7 Reportes, panel y requerimientos no funcionales ---

    public function test_cp_41_reporte_de_lineas(): void
    {
        $this->actingAs($this->administrador())->get(route('reportes.show', 'lineas'))->assertOk()
            ->assertSeeInOrder(['Código', 'Nombre', 'Estaciones', 'Distancia', 'Buses asignados', 'Mínimo', 'Máximo'])
            ->assertSee('9.40 km')->assertSee('L12');
    }

    public function test_cp_42_reporte_de_estaciones(): void
    {
        $this->actingAs($this->administrador())->get(route('reportes.show', 'estaciones'))->assertOk()
            ->assertSeeInOrder(['Estación', 'Municipalidad', 'Líneas', 'Orden', 'Accesos', 'Guardias', 'Operador'])
            ->assertSee('Villa Nueva')->assertSee('Karla Juárez Méndez');
    }

    public function test_cp_43_reporte_de_buses_asignados(): void
    {
        $csv = $this->actingAs($this->administrador())->get(route('reportes.csv', 'buses'))->streamedContent();
        $lineas = array_map(fn ($l) => str_getcsv($l, ';', '"', ''), explode("\n", trim(substr($csv, 3))));
        $porLinea = array_column(array_slice($lineas, 1), 0);

        $this->assertContains('Sin línea', $porLinea, 'Incluye los buses sin línea.');
        $this->assertSame(Bus::whereNull('linea_id')->count(), count(array_keys($porLinea, 'Sin línea')));
        // Agrupados: cada línea aparece en un solo bloque y los sin línea al final.
        $bloques = array_values(array_unique($porLinea));
        $this->assertSame('Sin línea', end($bloques));
        $this->assertSame(Bus::count(), count($porLinea));
    }

    public function test_cp_44_imprimir_reporte(): void
    {
        $html = $this->actingAs($this->administrador())->get(route('reportes.imprimir', 'estaciones'))->assertOk()->getContent();

        $this->assertStringNotContainsString('data-menu', $html, 'Sin menú lateral.');
        $this->assertStringNotContainsString('Cerrar sesión', $html);
        $this->assertSame(Estacion::count(), substr_count($html, '<tr data-fila'), 'La tabla va completa, sin paginar.');
    }

    public function test_cp_45_indicadores_del_panel(): void
    {
        $respuesta = $this->actingAs($this->administrador())->get(route('dashboard'))->assertOk();
        foreach (['lineas' => Linea::count(), 'estaciones' => Estacion::count(), 'buses' => Bus::count(), 'pilotos' => Piloto::where('estado', 'activo')->count()] as $indicador => $total) {
            $this->assertMatchesRegularExpression('/data-indicador="'.$indicador.'".*?<p class="text-2xl font-bold text-celeste-900">'.$total.'<\/p>/s', $respuesta->getContent(), "Indicador {$indicador}");
        }
    }

    public function test_cp_46_tiempo_de_respuesta_del_listado_de_buses(): void
    {
        $this->actingAs($this->administrador())->get(route('buses.index'))->assertOk();
        $tiempos = [];
        for ($i = 0; $i < 10; $i++) {
            $inicio = microtime(true);
            $this->get(route('buses.index'))->assertOk();
            $tiempos[] = microtime(true) - $inicio;
        }
        $this->assertLessThanOrEqual(2.0, array_sum($tiempos) / count($tiempos), 'Promedio de 2 segundos o menos.');
    }

    public function test_cp_47_bitacora(): void
    {
        $this->actingAs($this->administrador())->post(route('buses.store'), $this->datosBus())->assertSessionHas('ok', 'Bus registrado.');
        $registro = Bitacora::where('tabla', 'buses')->where('accion', 'crear')->latest('id')->firstOrFail();

        $this->get(route('dashboard'))->assertOk()
            ->assertSee('Registro del bus BUS-QA900')
            ->assertSee('Administrador Municipal · '.$registro->created_at->format('d/m/Y H:i'));
    }

    public function test_cp_49_inyeccion_sql(): void
    {
        $estaciones = Estacion::count();
        $this->actingAs($this->administrador())->get(route('estaciones.index', ['buscar' => "' OR 1=1 --"]))->assertOk()->assertSee('No se encontraron estaciones.');
        $this->assertSame($estaciones, Estacion::count());
    }

    public function test_cp_50_contrasenas_cifradas(): void
    {
        $this->assertSame(0, User::where('password', 'not like', '$2y$%')->count());
        $this->assertSame(0, User::where('password', 'Transmetro2026')->count());
    }
}
