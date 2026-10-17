<?php

namespace Tests\Feature;

use App\Models\Acceso;
use App\Models\Linea;
use App\Models\Parqueo;
use Tests\TestCase;

/**
 * El operador consulta parqueos, estaciones y líneas, pero no crea, edita ni elimina en estos módulos.
 */
class PermisosOperadorTest extends TestCase
{
    public function test_el_operador_no_puede_crear_editar_ni_eliminar(): void
    {
        $parqueo = Parqueo::firstOrFail();
        $acceso = Acceso::has('guardias')->firstOrFail();
        $guardia = $acceso->guardias()->firstOrFail();
        $linea = Linea::has('estaciones')->firstOrFail();
        $estacionDeLinea = $linea->estaciones()->firstOrFail();

        $peticiones = [
            ['get', route('parqueos.create')],
            ['post', route('parqueos.store')],
            ['get', route('parqueos.edit', $parqueo)],
            ['put', route('parqueos.update', $parqueo)],
            ['delete', route('parqueos.destroy', $parqueo)],
            ['get', route('estaciones.accesos.create', $acceso->estacion_id)],
            ['post', route('estaciones.accesos.store', $acceso->estacion_id)],
            ['get', route('accesos.edit', $acceso)],
            ['put', route('accesos.update', $acceso)],
            ['delete', route('accesos.destroy', $acceso)],
            ['get', route('accesos.guardias.create', $acceso)],
            ['post', route('accesos.guardias.store', $acceso)],
            ['get', route('guardias.edit', $guardia)],
            ['put', route('guardias.update', $guardia)],
            ['delete', route('guardias.destroy', $guardia)],
            ['delete', route('lineas.estaciones.destroy', [$linea, $estacionDeLinea])],
        ];

        $this->actingAs($this->operadorTrebol());
        foreach ($peticiones as [$metodo, $url]) {
            $this->{$metodo}($url)->assertForbidden();
        }

        $this->assertModelExists($parqueo);
        $this->assertModelExists($acceso);
        $this->assertModelExists($guardia);
    }

    public function test_el_operador_consulta_sin_ver_botones_de_administracion(): void
    {
        $estacion = $this->operadorTrebol()->estacion;

        $this->actingAs($this->operadorTrebol())
            ->get(route('estaciones.show', $estacion))
            ->assertOk()
            ->assertSee('Accesos y guardias')
            ->assertDontSee('Nuevo acceso')
            ->assertDontSee(route('estaciones.accesos.create', $estacion));

        $this->get(route('parqueos.index'))->assertOk()->assertDontSee(route('parqueos.create'));
    }
}
