<?php

namespace Tests\Feature;

use App\Models\Alerta;
use App\Models\Bus;
use App\Models\Linea;
use Tests\TestCase;

/**
 * Mínimo (estaciones) y máximo (estaciones × 2) de buses por línea: App\Services\AsignacionService.
 */
class AsignacionBusesTest extends TestCase
{
    private int $siguiente = 0;

    private function nuevoBus(?int $lineaId): Bus
    {
        $n = ++$this->siguiente;

        return Bus::create(['linea_id' => $lineaId, 'parqueo_id' => 1, 'placa' => "PRA-{$n}", 'codigo' => "BUS-PRA{$n}", 'capacidad_maxima' => 80, 'estado' => 'disponible']);
    }

    /**
     * Deja la línea con exactamente $cantidad buses (la base es de desarrollo y sus datos cambian).
     */
    private function dejarBuses(Linea $linea, int $cantidad): void
    {
        $sobran = $linea->buses()->count() - $cantidad;
        if ($sobran > 0) {
            Bus::where('linea_id', $linea->id)->orderByDesc('id')->limit($sobran)->update(['linea_id' => null]);
        }
        for ($i = $sobran; $i < 0; $i++) {
            $this->nuevoBus($linea->id);
        }
        $this->assertSame($cantidad, $linea->buses()->count());
    }

    private function datosBus(Bus $bus, array $cambios): array
    {
        return [...$bus->only(['linea_id', 'parqueo_id', 'placa', 'codigo', 'capacidad_maxima', 'estado']), ...$cambios];
    }

    private function alertasActivas(Linea $linea): int
    {
        return Alerta::where('linea_id', $linea->id)->where('tipo', 'insuficiencia_unidades')->where('estado', 'activa')->count();
    }

    public function test_quitar_un_bus_de_la_l1_genera_alerta_de_insuficiencia(): void
    {
        $l1 = Linea::where('codigo', 'L1')->firstOrFail();
        $estaciones = $l1->estaciones()->count();
        $this->dejarBuses($l1, $estaciones);
        Alerta::where('linea_id', $l1->id)->where('tipo', 'insuficiencia_unidades')->update(['estado' => 'atendida']);
        $bus = $l1->buses()->firstOrFail();

        $this->actingAs($this->administrador())
            ->put(route('buses.update', $bus), $this->datosBus($bus, ['linea_id' => '']))
            ->assertRedirect(route('buses.show', $bus))
            ->assertSessionHas('ok', 'Bus actualizado.')
            ->assertSessionHas('advertencia', 'La línea L1 quedó con '.($estaciones - 1)." buses y necesita al menos {$estaciones} (uno por estación). Faltan 1.");

        // Se guarda igual: el bus queda sin línea y la alerta se registra para la línea.
        $this->assertNull($bus->fresh()->linea_id);
        $this->assertSame(1, $this->alertasActivas($l1));
        $this->assertDatabaseHas('bitacora', ['tabla' => 'alertas', 'accion' => 'crear']);
    }

    public function test_no_se_duplica_la_alerta_activa_de_insuficiencia(): void
    {
        $l1 = Linea::where('codigo', 'L1')->firstOrFail();
        $this->dejarBuses($l1, $l1->estaciones()->count());
        [$primero, $segundo] = $l1->buses()->take(2)->get()->all();
        $this->actingAs($this->administrador());

        $this->put(route('buses.update', $primero), $this->datosBus($primero, ['linea_id' => '']));
        $this->put(route('buses.update', $segundo), $this->datosBus($segundo, ['linea_id' => '']))
            ->assertSessionHas('advertencia', fn ($aviso) => str_contains($aviso, 'Faltan 2.'));

        $this->assertSame(1, $this->alertasActivas($l1));
        $this->assertStringContainsString('Faltan 2.', Alerta::where('linea_id', $l1->id)->where('tipo', 'insuficiencia_unidades')->where('estado', 'activa')->value('mensaje'));
    }

    public function test_reasignar_un_bus_revisa_la_linea_anterior(): void
    {
        $l1 = Linea::where('codigo', 'L1')->firstOrFail();
        $l12 = Linea::where('codigo', 'L12')->firstOrFail();
        $this->dejarBuses($l1, $l1->estaciones()->count());
        $this->dejarBuses($l12, $l12->estaciones()->count());
        $bus = $l1->buses()->firstOrFail();

        $this->actingAs($this->administrador())
            ->put(route('buses.update', $bus), $this->datosBus($bus, ['linea_id' => $l12->id]))
            ->assertSessionHas('advertencia', fn ($aviso) => str_starts_with($aviso, 'La línea L1 quedó con'));

        $this->assertSame($l12->id, $bus->fresh()->linea_id);
        $this->assertSame(1, $this->alertasActivas($l1));
    }

    public function test_eliminar_un_bus_tambien_revisa_el_minimo(): void
    {
        $l1 = Linea::where('codigo', 'L1')->firstOrFail();
        $this->dejarBuses($l1, $l1->estaciones()->count());
        $bus = $this->nuevoBus(null);
        $l1->buses()->first()->update(['linea_id' => null]);
        $bus->update(['linea_id' => $l1->id]);

        $this->actingAs($this->administrador())
            ->delete(route('buses.destroy', $bus))
            ->assertSessionHas('ok', 'Bus eliminado.')
            ->assertSessionHas('advertencia');

        $this->assertSame(1, $this->alertasActivas($l1));
    }

    public function test_si_la_linea_sigue_cubierta_no_hay_alerta_ni_aviso(): void
    {
        $linea = Linea::create(['municipalidad_id' => 1, 'codigo' => 'LPRA', 'nombre' => 'Línea de prueba', 'estado' => 'activa']);
        $linea->estaciones()->attach(1, ['orden' => 1, 'distancia_anterior_km' => 0]);
        $this->nuevoBus($linea->id);
        $bus = $this->nuevoBus($linea->id);

        $this->actingAs($this->administrador())
            ->put(route('buses.update', $bus), $this->datosBus($bus, ['linea_id' => '']))
            ->assertSessionMissing('advertencia');

        $this->assertSame(0, $this->alertasActivas($linea));
    }

    public function test_quitar_una_estacion_que_romperia_el_maximo_se_rechaza(): void
    {
        $l2 = Linea::where('codigo', 'L2')->firstOrFail();
        $estaciones = $l2->estaciones()->count();
        $this->dejarBuses($l2, $estaciones * 2);
        $estacion = $l2->estaciones()->firstOrFail();

        $this->actingAs($this->administrador())
            ->from(route('lineas.show', $l2))
            ->delete(route('lineas.estaciones.destroy', [$l2, $estacion]))
            ->assertRedirect(route('lineas.show', $l2))
            ->assertSessionHas('error', "No se puede quitar {$estacion->nombre}: la línea L2 tiene ".($estaciones * 2).' buses y con '.($estaciones - 1)
                .' estaciones el máximo sería '.(($estaciones - 1) * 2).' (estaciones × 2). Reasigne 2 buses antes de quitarla.');

        $this->assertSame($estaciones, $l2->estaciones()->count());
    }

    public function test_quitar_una_estacion_dentro_del_maximo_se_permite(): void
    {
        $l2 = Linea::where('codigo', 'L2')->firstOrFail();
        $estaciones = $l2->estaciones()->count();
        $this->dejarBuses($l2, ($estaciones - 1) * 2);
        $ultima = $l2->estaciones()->get()->last();

        $this->actingAs($this->administrador())
            ->delete(route('lineas.estaciones.destroy', [$l2, $ultima]))
            ->assertSessionHas('ok', "Estación {$ultima->nombre} retirada de la línea.");

        $this->assertSame($estaciones - 1, $l2->estaciones()->count());
    }
}
