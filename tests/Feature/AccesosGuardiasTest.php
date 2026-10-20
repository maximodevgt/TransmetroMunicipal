<?php

namespace Tests\Feature;

use App\Models\Acceso;
use App\Models\Estacion;
use App\Models\Guardia;
use App\Models\Linea;
use Tests\TestCase;

/**
 * Accesos y guardias desde la ficha de la estación. RN-11: todo acceso tiene al menos un guardia.
 */
class AccesosGuardiasTest extends TestCase
{
    private function estacion(): Estacion
    {
        return Estacion::create(['municipalidad_id' => 1, 'nombre' => 'Estación de prueba', 'estado' => 'activa']);
    }

    private function accesoConGuardias(int $guardias): Acceso
    {
        $acceso = $this->estacion()->accesos()->create(['nombre' => 'Acceso Prueba']);
        foreach (range(1, $guardias) as $i) {
            $acceso->guardias()->create(['nombre' => "Guardia {$i}", 'dpi' => '999000000000'.$i, 'turno' => 'matutino']);
        }

        return $acceso;
    }

    private function datosAcceso(array $cambios = []): array
    {
        return [
            'nombre' => 'Acceso Oriente',
            'guardia_nombre' => 'Ana López Pérez',
            'guardia_dpi' => '3000000000101',
            'guardia_telefono' => '55123456',
            'guardia_turno' => 'nocturno',
            ...$cambios,
        ];
    }

    public function test_un_acceso_nuevo_nace_con_su_primer_guardia(): void
    {
        $estacion = $this->estacion();

        $this->actingAs($this->administrador())
            ->post(route('estaciones.accesos.store', $estacion), $this->datosAcceso())
            ->assertRedirect(route('estaciones.show', $estacion))
            ->assertSessionHas('ok', 'Acceso creado: Acceso Oriente, con el guardia Ana López Pérez.');

        $acceso = $estacion->accesos()->where('nombre', 'Acceso Oriente')->firstOrFail();
        $this->assertSame(1, $acceso->guardias()->count());
        $this->assertDatabaseHas('guardias', ['acceso_id' => $acceso->id, 'dpi' => '3000000000101', 'turno' => 'nocturno']);
        $this->assertDatabaseHas('bitacora', ['tabla' => 'accesos', 'registro_id' => $acceso->id, 'accion' => 'crear']);
    }

    public function test_sin_guardia_no_se_crea_el_acceso(): void
    {
        $estacion = $this->estacion();

        $this->actingAs($this->administrador())
            ->post(route('estaciones.accesos.store', $estacion), ['nombre' => 'Acceso Oriente'])
            ->assertSessionHasErrors(['guardia_nombre', 'guardia_dpi', 'guardia_turno']);

        $this->assertSame(0, $estacion->accesos()->count());
    }

    public function test_el_dpi_debe_tener_13_digitos_y_ser_unico(): void
    {
        $estacion = $this->estacion();
        $dpiExistente = Guardia::firstOrFail()->dpi;

        $this->actingAs($this->administrador())
            ->post(route('estaciones.accesos.store', $estacion), $this->datosAcceso(['guardia_dpi' => '12345']))
            ->assertSessionHasErrors(['guardia_dpi' => 'El DPI debe tener exactamente 13 dígitos.']);

        $this->post(route('estaciones.accesos.store', $estacion), $this->datosAcceso(['guardia_dpi' => $dpiExistente]))
            ->assertSessionHasErrors(['guardia_dpi' => 'Ya existe un guardia con ese DPI.']);

        $this->assertSame(0, $estacion->accesos()->count());
    }

    public function test_no_se_elimina_el_ultimo_guardia_de_un_acceso(): void
    {
        $acceso = $this->accesoConGuardias(1);
        $guardia = $acceso->guardias()->firstOrFail();

        $this->actingAs($this->administrador())
            ->from(route('estaciones.show', $acceso->estacion_id))
            ->delete(route('guardias.destroy', $guardia))
            ->assertRedirect(route('estaciones.show', $acceso->estacion_id))
            ->assertSessionHas('error', 'No se puede eliminar a Guardia 1: es el último guardia del acceso Acceso Prueba y todo acceso debe tener al menos uno (RN-11).');

        $this->assertModelExists($guardia);
    }

    public function test_se_elimina_un_guardia_si_el_acceso_tiene_otros(): void
    {
        $acceso = $this->accesoConGuardias(2);
        $guardia = $acceso->guardias()->firstOrFail();

        $this->actingAs($this->administrador())
            ->delete(route('guardias.destroy', $guardia))
            ->assertSessionHas('ok', 'Guardia eliminado: Guardia 1.');

        $this->assertModelMissing($guardia);
        $this->assertSame(1, $acceso->guardias()->count());
    }

    public function test_se_agrega_y_edita_un_guardia(): void
    {
        $acceso = $this->accesoConGuardias(1);
        $this->actingAs($this->administrador());

        $this->post(route('accesos.guardias.store', $acceso), ['nombre' => 'Luis Gómez', 'dpi' => '3000000000202', 'turno' => 'vespertino'])
            ->assertSessionHasNoErrors();
        $guardia = $acceso->guardias()->where('dpi', '3000000000202')->firstOrFail();

        $this->put(route('guardias.update', $guardia), ['nombre' => 'Luis Gómez Ruiz', 'dpi' => '3000000000202', 'telefono' => '55998877', 'turno' => 'nocturno'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('guardias', ['id' => $guardia->id, 'nombre' => 'Luis Gómez Ruiz', 'turno' => 'nocturno', 'telefono' => '55998877']);
    }

    public function test_eliminar_un_acceso_retira_sus_guardias(): void
    {
        $acceso = $this->accesoConGuardias(2);

        $this->actingAs($this->administrador())
            ->delete(route('accesos.destroy', $acceso))
            ->assertSessionHas('ok', 'Acceso eliminado: Acceso Prueba.');

        $this->assertModelMissing($acceso);
        $this->assertSame(0, Guardia::where('acceso_id', $acceso->id)->count());
    }

    public function test_el_nombre_del_acceso_es_unico_en_la_estacion(): void
    {
        $acceso = $this->accesoConGuardias(1);

        $this->actingAs($this->administrador())
            ->post(route('estaciones.accesos.store', $acceso->estacion_id), $this->datosAcceso(['nombre' => 'Acceso Prueba']))
            ->assertSessionHasErrors(['nombre' => 'La estación ya tiene un acceso con ese nombre.']);
    }

    public function test_la_ficha_de_la_linea_muestra_los_accesos_y_guardias_de_sus_estaciones(): void
    {
        $linea = Linea::with('estaciones.accesos.guardias')->where('codigo', 'L12')->firstOrFail();
        $trebol = $this->operadorTrebol()->estacion_id;
        $guardiaPropio = $linea->estaciones->firstWhere('id', $trebol)->accesos->flatMap->guardias->firstOrFail();
        $guardiaAjeno = $linea->estaciones->firstWhere('id', '!=', $trebol)->accesos->flatMap->guardias->firstOrFail();

        // El administrador ve los de todas las estaciones de la línea (RF-04).
        $this->actingAs($this->administrador())->get(route('lineas.show', $linea))->assertOk()
            ->assertSee('Accesos y guardias de la línea')
            ->assertSee($guardiaPropio->nombre)
            ->assertSee($guardiaAjeno->nombre);

        // El operador, solo los de su estación.
        $this->actingAs($this->operadorTrebol())->get(route('lineas.show', $linea))->assertOk()
            ->assertSee('Accesos y guardias de su estación')
            ->assertSee($guardiaPropio->nombre)
            ->assertDontSee($guardiaAjeno->nombre);
    }
}
