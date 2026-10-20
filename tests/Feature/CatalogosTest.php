<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Estacion;
use App\Models\Linea;
use App\Models\Piloto;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concerns\ConRecorridos;
use Tests\TestCase;

/**
 * Catálogos del administrador: líneas (RF-01), estaciones (RF-02, RN-02, RN-12), orden de estaciones (RF-03),
 * buses (RF-05, RN-05, RN-08 a RN-10), pilotos (RF-08) y operadores por estación (RF-09, RN-17).
 */
class CatalogosTest extends TestCase
{
    use ConRecorridos;

    private function orden(Linea $linea): array
    {
        return DB::table('linea_estacion')->where('linea_id', $linea->id)->orderBy('orden')->pluck('estacion_id')->all();
    }

    // --- RF-01: líneas ---

    public function test_rf01_el_administrador_registra_y_edita_una_linea_con_su_municipalidad(): void
    {
        $this->actingAs($this->administrador());

        $this->post(route('lineas.store'), ['codigo' => 'LQA', 'nombre' => 'Línea QA', 'estado' => 'activa'])
            ->assertSessionHasErrors('municipalidad_id'); // RN-12
        $this->post(route('lineas.store'), ['municipalidad_id' => 1, 'codigo' => 'L12', 'nombre' => 'Repetida', 'estado' => 'activa'])
            ->assertSessionHasErrors('codigo');

        $this->post(route('lineas.store'), ['municipalidad_id' => 1, 'codigo' => 'LQA', 'nombre' => 'Línea QA', 'estado' => 'activa'])
            ->assertSessionHas('ok', 'Línea creada. Ahora agregue sus estaciones.');
        $linea = Linea::where('codigo', 'LQA')->firstOrFail();
        $this->assertDatabaseHas('bitacora', ['tabla' => 'lineas', 'registro_id' => $linea->id, 'accion' => 'crear']);

        $this->put(route('lineas.update', $linea), ['municipalidad_id' => 1, 'codigo' => 'LQA', 'nombre' => 'Línea QA editada', 'estado' => 'activa'])
            ->assertSessionHas('ok', 'Línea actualizada.');
        $this->assertSame('Línea QA editada', $linea->fresh()->nombre);
        $this->get(route('lineas.show', $linea))->assertOk()->assertSee('Línea QA editada');
    }

    // --- RF-02 y RN-02: estaciones ---

    public function test_rn02_la_estacion_nueva_nace_dentro_de_una_linea(): void
    {
        $l2 = Linea::where('codigo', 'L2')->firstOrFail();
        $antes = $this->orden($l2);
        $this->actingAs($this->administrador());
        $datos = ['municipalidad_id' => 1, 'nombre' => 'Estación QA', 'estado' => 'activa'];

        $this->post(route('estaciones.store'), $datos)
            ->assertSessionHasErrors(['linea_id' => 'Elija la línea a la que pertenece la estación (cada estación debe estar al menos en una).']);
        $this->assertDatabaseMissing('estaciones', ['nombre' => 'Estación QA']);

        $this->post(route('estaciones.store'), [...$datos, 'linea_id' => $l2->id, 'distancia_anterior_km' => 0.75])->assertSessionHas('ok', 'Estación creada.');
        $estacion = Estacion::where('nombre', 'Estación QA')->firstOrFail();
        $this->assertSame([...$antes, $estacion->id], $this->orden($l2), 'Entra al final del recorrido.');
        $this->assertSame('0.75', (string) DB::table('linea_estacion')->where('estacion_id', $estacion->id)->value('distancia_anterior_km'));
        $this->assertDatabaseHas('bitacora', ['tabla' => 'lineas', 'registro_id' => $l2->id, 'accion' => 'agregar_estacion']);
    }

    public function test_rn02_no_se_quita_la_unica_linea_de_una_estacion(): void
    {
        $l2 = Linea::where('codigo', 'L2')->firstOrFail();
        $soloEnL2 = $l2->estaciones()->get()->first(fn ($e) => $e->lineas()->count() === 1);

        $this->actingAs($this->administrador())->delete(route('lineas.estaciones.destroy', [$l2, $soloEnL2]))
            ->assertSessionHas('error', "No se puede quitar {$soloEnL2->nombre} de L2: es su única línea y cada estación debe pertenecer al menos a una (RN-02). Agréguela antes a otra línea o elimine la estación.");
        $this->assertContains($soloEnL2->id, $this->orden($l2));
    }

    public function test_eliminar_una_estacion_la_retira_de_su_linea_y_reordena(): void
    {
        $linea = $this->lineaDePrueba(3);
        $this->actingAs($this->administrador())
            ->post(route('estaciones.store'), ['municipalidad_id' => 1, 'nombre' => 'Estación QA temporal', 'estado' => 'activa', 'linea_id' => $linea->id, 'distancia_anterior_km' => 1]);
        $estacion = Estacion::where('nombre', 'Estación QA temporal')->firstOrFail();
        $this->assertCount(4, $this->orden($linea));

        $this->delete(route('estaciones.destroy', $estacion))->assertSessionHas('ok', 'Estación eliminada.');
        $this->assertModelMissing($estacion);
        $this->assertCount(3, $this->orden($linea));
        $this->assertSame([1, 2, 3], DB::table('linea_estacion')->where('linea_id', $linea->id)->orderBy('orden')->pluck('orden')->all());
    }

    public function test_rn02_la_estacion_que_esta_en_dos_lineas_se_puede_quitar_de_una(): void
    {
        $l2 = Linea::where('codigo', 'L2')->firstOrFail();
        $compartida = $l2->estaciones()->get()->first(fn ($e) => $e->lineas()->count() > 1);
        $this->assertNotNull($compartida, 'San Sebastián está en L1 y L2.');
        DB::table('buses')->where('linea_id', $l2->id)->orderByDesc('id')->limit(max(0, $l2->buses()->count() - ($l2->estaciones()->count() - 1) * 2))->update(['linea_id' => null]);

        $this->actingAs($this->administrador())->delete(route('lineas.estaciones.destroy', [$l2, $compartida]))
            ->assertSessionHas('ok', "Estación {$compartida->nombre} retirada de la línea.");
        $this->assertNotContains($compartida->id, $this->orden($l2));
        $this->assertGreaterThan(0, $compartida->lineas()->count(), 'Sigue en su otra línea.');
    }

    // --- RF-03: orden de las estaciones ---

    public function test_rf03_subir_y_bajar_una_estacion_reordena_el_recorrido(): void
    {
        $linea = $this->lineaDePrueba(4);
        [$a, $b, $c, $d] = $this->orden($linea);
        $this->actingAs($this->administrador());

        $this->patch(route('lineas.estaciones.mover', [$linea, $d]), ['posicion' => 1])->assertSessionHas('ok');
        $this->assertSame([$d, $a, $b, $c], $this->orden($linea));
        // La nueva primera estación no tiene distancia anterior.
        $this->assertSame('0.00', (string) DB::table('linea_estacion')->where('linea_id', $linea->id)->where('orden', 1)->value('distancia_anterior_km'));

        $this->patch(route('lineas.estaciones.mover', [$linea, $a]), ['posicion' => 4])->assertSessionHas('ok');
        $this->assertSame([$d, $b, $c, $a], $this->orden($linea));
        $this->assertDatabaseHas('bitacora', ['tabla' => 'lineas', 'registro_id' => $linea->id, 'accion' => 'mover_estacion']);

        $this->patch(route('lineas.estaciones.mover', [$linea, $a]), ['posicion' => 9])->assertSessionHasErrors('posicion');
        $this->get(route('lineas.show', $linea))->assertOk()->assertSee('aria-label="Subir', false)->assertSee('aria-label="Bajar', false);
    }

    public function test_rf03_no_se_cambia_el_orden_con_recorridos_en_curso(): void
    {
        $recorrido = $this->recorridoEnCurso();
        $antes = $this->orden($recorrido->linea);

        $this->actingAs($this->administrador())->patch(route('lineas.estaciones.mover', [$recorrido->linea, $antes[2]]), ['posicion' => 1])
            ->assertSessionHas('error', "No se cambia el orden de {$recorrido->linea->codigo} mientras tenga recorridos en curso: finalícelos primero.");
        $this->assertSame($antes, $this->orden($recorrido->linea));
    }

    // --- RF-05 y RN-05, RN-08 a RN-10: buses ---

    public function test_rf05_registrar_un_bus_exige_parqueo_y_capacidad(): void
    {
        $this->actingAs($this->administrador());
        $datos = ['parqueo_id' => 1, 'placa' => 'QA-001', 'codigo' => 'BUS-QA1', 'capacidad_maxima' => 90, 'estado' => 'disponible'];

        $this->post(route('buses.store'), [...$datos, 'parqueo_id' => ''])->assertSessionHasErrors('parqueo_id'); // RN-09
        $this->post(route('buses.store'), [...$datos, 'capacidad_maxima' => 0])->assertSessionHasErrors('capacidad_maxima'); // RF-12
        $this->post(route('buses.store'), $datos)->assertSessionHas('ok', 'Bus registrado.');
        $bus = Bus::where('codigo', 'BUS-QA1')->firstOrFail();
        $this->assertNull($bus->linea_id, 'RN-06: puede quedar sin línea.');

        // RN-10: cambia de parqueo, pero nunca queda sin uno.
        $editar = fn (array $cambios) => $this->put(route('buses.update', $bus), [...$datos, ...$cambios]);
        $editar(['parqueo_id' => 2])->assertSessionHas('ok', 'Bus actualizado.');
        $this->assertSame(2, (int) $bus->fresh()->parqueo_id);
        $editar(['parqueo_id' => ''])->assertSessionHasErrors('parqueo_id');
        $this->assertSame(2, (int) $bus->fresh()->parqueo_id);

        // RN-05: una sola línea; al reasignarlo deja la anterior.
        $editar(['parqueo_id' => 2, 'linea_id' => Linea::where('codigo', 'L12')->value('id')]);
        $editar(['parqueo_id' => 2, 'linea_id' => Linea::where('codigo', 'L1')->value('id')]);
        $this->assertSame('L1', $bus->fresh()->linea->codigo);
        $this->assertSame(1, Bus::where('codigo', 'BUS-QA1')->count());
    }

    public function test_rn08_no_se_supera_el_maximo_de_buses_de_la_linea(): void
    {
        $linea = $this->lineaDePrueba(1); // 1 estación: máximo 2 buses
        $this->busEn($linea);
        $this->busEn($linea);

        $this->actingAs($this->administrador())->from(route('buses.create'))
            ->post(route('buses.store'), ['linea_id' => $linea->id, 'parqueo_id' => 1, 'placa' => 'QA-MAX', 'codigo' => 'BUS-QAMAX', 'capacidad_maxima' => 80, 'estado' => 'disponible'])
            ->assertSessionHas('error', 'La línea ya tiene el máximo de buses permitido (estaciones × 2).');
        $this->assertSame(2, $linea->buses()->count());
    }

    // --- RF-08: pilotos ---

    public function test_rf08_piloto_con_datos_de_contacto_e_historial_educativo(): void
    {
        $this->actingAs($this->administrador());
        $datos = ['nombre' => 'Piloto QA', 'dpi' => '3000000444401', 'licencia' => 'A-QA01', 'residencia' => 'Zona 1, Guatemala',
            'telefono' => '55443322', 'email' => 'piloto.qa@transmetro.gt', 'estado' => 'activo'];

        $this->post(route('pilotos.store'), [...$datos, 'dpi' => '123'])->assertSessionHasErrors('dpi');
        $this->post(route('pilotos.store'), [...$datos, 'residencia' => ''])->assertSessionHasErrors('residencia');
        $this->post(route('pilotos.store'), $datos)->assertSessionHas('ok');
        $piloto = Piloto::where('dpi', '3000000444401')->firstOrFail();

        $this->post(route('pilotos.historial.store', $piloto), ['nivel' => 'diversificado', 'institucion' => 'Instituto QA', 'titulo' => 'Perito en Mecánica', 'anio' => 2010])
            ->assertSessionHas('ok', 'Estudio agregado al historial.');
        $this->get(route('pilotos.show', $piloto))->assertOk()
            ->assertSee('Piloto QA')->assertSee('Zona 1, Guatemala')->assertSee('55443322')->assertSee('Instituto QA')->assertSee('Perito en Mecánica');
    }

    // --- RF-09 y RN-17: operadores por estación ---

    public function test_rf09_el_operador_se_registra_con_su_estacion_y_contrasena_con_hash(): void
    {
        $this->actingAs($this->administrador());
        $datos = ['name' => 'Operador QA', 'email' => 'operador.qa@transmetro.gt', 'rol' => 'operador', 'password' => 'ClaveQA2026', 'password_confirmation' => 'ClaveQA2026', 'activo' => '1'];

        $this->post(route('usuarios.store'), $datos)->assertSessionHasErrors(['estacion_id' => 'Un operador debe estar asignado a una estación.']);
        $this->post(route('usuarios.store'), [...$datos, 'estacion_id' => 8, 'password_confirmation' => 'otra'])->assertSessionHasErrors('password');
        $this->post(route('usuarios.store'), [...$datos, 'estacion_id' => 8])->assertSessionHas('ok', 'Usuario Operador QA creado.');

        $usuario = User::where('email', 'operador.qa@transmetro.gt')->firstOrFail();
        $this->assertSame(8, (int) $usuario->estacion_id);
        $this->assertTrue(Hash::check('ClaveQA2026', $usuario->password));
    }

    public function test_rn17_no_se_deja_una_estacion_sin_operador(): void
    {
        $operador = $this->operadorTrebol();
        $this->assertSame(1, User::where('estacion_id', $operador->estacion_id)->where('rol', 'operador')->where('activo', true)->count(), 'Trébol tiene un solo operador.');
        $this->actingAs($this->administrador());
        $datos = ['name' => $operador->name, 'email' => $operador->email, 'rol' => 'operador', 'estacion_id' => $operador->estacion_id];

        foreach ([['activo' => '0'], ['activo' => '1', 'estacion_id' => 1], ['activo' => '1', 'rol' => 'administrador']] as $cambio) {
            $this->put(route('usuarios.update', $operador), [...$datos, ...$cambio])
                ->assertSessionHas('error', 'La estación Trébol se quedaría sin operador (RN-17): asigne antes otro operador activo a esa estación.');
            $this->assertTrue($operador->fresh()->activo);
            $this->assertSame(8, (int) $operador->fresh()->estacion_id);
        }

        // Con otro operador activo en Trébol, ya se puede desactivar.
        User::create(['name' => 'Relevo QA', 'email' => 'relevo.qa@transmetro.gt', 'password' => 'ClaveQA2026', 'rol' => 'operador', 'estacion_id' => 8, 'activo' => true]);
        $this->put(route('usuarios.update', $operador), [...$datos, 'activo' => '0'])->assertSessionHas('ok');
        $this->assertFalse($operador->fresh()->activo);
    }
}
