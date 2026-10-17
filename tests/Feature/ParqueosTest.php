<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Bus;
use App\Models\Parqueo;
use Tests\TestCase;

class ParqueosTest extends TestCase
{
    private function parqueo(int $capacidad = 5): Parqueo
    {
        return Parqueo::create(['nombre' => 'Predio de prueba', 'direccion' => 'Zona 4', 'capacidad' => $capacidad]);
    }

    private function busEn(Parqueo $parqueo, string $codigo = 'BUS-PRQ1'): Bus
    {
        return Bus::create(['parqueo_id' => $parqueo->id, 'placa' => substr($codigo, -6), 'codigo' => $codigo, 'capacidad_maxima' => 80, 'estado' => 'disponible']);
    }

    public function test_el_administrador_registra_un_parqueo_y_queda_en_bitacora(): void
    {
        $this->actingAs($this->administrador())
            ->post(route('parqueos.store'), ['nombre' => 'Predio Mixco', 'direccion' => 'Zona 1, Mixco', 'capacidad' => 12])
            ->assertRedirect()
            ->assertSessionHas('ok', 'Parqueo registrado.');

        $parqueo = Parqueo::where('nombre', 'Predio Mixco')->firstOrFail();
        $this->assertSame(12, $parqueo->capacidad);
        $this->assertDatabaseHas('bitacora', ['tabla' => 'parqueos', 'registro_id' => $parqueo->id, 'accion' => 'crear']);
    }

    public function test_un_parqueo_con_buses_no_se_elimina(): void
    {
        $parqueo = $this->parqueo();
        $this->busEn($parqueo);

        $this->actingAs($this->administrador())
            ->from(route('parqueos.show', $parqueo))
            ->delete(route('parqueos.destroy', $parqueo))
            ->assertRedirect(route('parqueos.show', $parqueo))
            ->assertSessionHas('error', 'No se puede eliminar el parqueo Predio de prueba: tiene 1 bus asignado. Reubíquelos primero.');

        $this->assertModelExists($parqueo);
        $this->assertFalse(Bitacora::where('tabla', 'parqueos')->where('registro_id', $parqueo->id)->where('accion', 'eliminar')->exists());
    }

    public function test_un_parqueo_sin_buses_se_elimina(): void
    {
        $parqueo = $this->parqueo();

        $this->actingAs($this->administrador())
            ->delete(route('parqueos.destroy', $parqueo))
            ->assertRedirect(route('parqueos.index'))
            ->assertSessionHas('ok', 'Parqueo eliminado.');

        $this->assertModelMissing($parqueo);
    }

    public function test_la_capacidad_no_puede_quedar_debajo_de_sus_buses(): void
    {
        $parqueo = $this->parqueo(5);
        $this->busEn($parqueo, 'BUS-PRQ1');
        $this->busEn($parqueo, 'BUS-PRQ2');

        $this->actingAs($this->administrador())
            ->put(route('parqueos.update', $parqueo), ['nombre' => $parqueo->nombre, 'capacidad' => 1])
            ->assertSessionHasErrors(['capacidad' => 'La capacidad no puede ser menor que los 2 buses que ya tiene el parqueo.']);

        $this->assertSame(5, $parqueo->fresh()->capacidad);
    }

    public function test_el_nombre_del_parqueo_es_unico(): void
    {
        $existente = Parqueo::firstOrFail();

        $this->actingAs($this->administrador())
            ->post(route('parqueos.store'), ['nombre' => $existente->nombre, 'capacidad' => 5])
            ->assertSessionHasErrors('nombre');
    }

    public function test_la_ficha_muestra_la_ocupacion_y_sus_buses(): void
    {
        $parqueo = $this->parqueo(4);
        $this->busEn($parqueo);

        $this->actingAs($this->operadorTrebol())
            ->get(route('parqueos.show', $parqueo))
            ->assertOk()
            ->assertSee('1 / 4')
            ->assertSee('25 %')
            ->assertSee('BUS-PRQ1')
            ->assertDontSee(route('parqueos.edit', $parqueo));
    }

    public function test_el_listado_muestra_la_ocupacion_de_cada_parqueo(): void
    {
        $parqueo = Parqueo::withCount('buses')->firstOrFail();

        $this->actingAs($this->operadorTrebol())
            ->get(route('parqueos.index'))
            ->assertOk()
            ->assertSee($parqueo->nombre)
            ->assertSee("{$parqueo->buses_count} / {$parqueo->capacidad}")
            ->assertDontSee('Nuevo parqueo');
    }
}
