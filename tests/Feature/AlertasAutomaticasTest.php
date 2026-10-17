<?php

namespace Tests\Feature;

use App\Models\Alerta;
use App\Models\Bus;
use App\Services\AlertaService;
use App\Services\RecorridoService;
use Tests\Feature\Concerns\ConRecorridos;
use Tests\TestCase;

/**
 * Reglas de App\Services\AlertaService: alta demanda (≥ 150 %), baja ocupación (< 25 %),
 * sin duplicados y atención automática de la insuficiencia de unidades.
 */
class AlertasAutomaticasTest extends TestCase
{
    use ConRecorridos;

    private function alertasActivas(string $tipo, array $donde): int
    {
        return Alerta::where('tipo', $tipo)->where('estado', 'activa')->where($donde)->count();
    }

    private function llegar(int $recorridoId, int $estacionId, int $pasajeros)
    {
        return $this->actingAs($this->administrador())
            ->post(route('recorridos.llegadas.store', $recorridoId), ['estacion_id' => $estacionId, 'pasajeros_a_bordo' => $pasajeros]);
    }

    public function test_la_capacidad_de_referencia_es_el_bus_en_ruta_mas_grande_o_el_mas_grande_asignado(): void
    {
        $linea = $this->lineaDePrueba(1);
        $estacion = $this->estacionDeOrden($linea, 1);
        // Se aísla la estación: solo cuentan los buses de la línea de prueba.
        $otrasLineas = $estacion->lineas()->where('lineas.id', '!=', $linea->id)->pluck('lineas.id');
        Bus::whereIn('linea_id', $otrasLineas)->update(['linea_id' => null]);

        $grande = $this->busEn($linea, 150);
        $mediano = $this->busEn($linea, 90);
        $servicio = app(AlertaService::class);

        $this->assertSame([150, $grande->id], [$servicio->capacidadReferencia($estacion)[0], $servicio->capacidadReferencia($estacion)[1]->id]);

        app(RecorridoService::class)->iniciar($linea, $mediano, $this->pilotoNuevo());
        $this->assertSame(90, $servicio->capacidadReferencia($estacion)[0]);
    }

    public function test_alta_demanda_con_150_por_ciento_de_la_capacidad_de_referencia(): void
    {
        $operador = $this->operadorTrebol();
        $estacion = $operador->estacion;
        Alerta::where('estacion_id', $estacion->id)->where('tipo', 'alta_demanda')->update(['estado' => 'atendida']);
        [$capacidad] = app(AlertaService::class)->capacidadReferencia($estacion);
        $umbral = (int) ceil($capacidad * 1.5);

        $this->actingAs($operador)->post(route('mi-estacion.pasajeros'), ['cantidad' => $umbral - 1])
            ->assertSessionHas('ok')
            ->assertSessionMissing('advertencia');
        $this->assertSame(0, $this->alertasActivas('alta_demanda', ['estacion_id' => $estacion->id]));

        $this->post(route('mi-estacion.pasajeros'), ['cantidad' => $umbral])
            ->assertSessionHas('advertencia', fn ($aviso) => str_contains($aviso, "Estación {$estacion->nombre} con {$umbral} pasajeros en espera"));
        $this->assertSame(1, $this->alertasActivas('alta_demanda', ['estacion_id' => $estacion->id]));
        $this->assertDatabaseHas('registros_pasajeros', ['estacion_id' => $estacion->id, 'user_id' => $operador->id, 'cantidad' => $umbral]);
    }

    public function test_baja_ocupacion_con_menos_del_25_por_ciento_agrega_5_minutos_de_espera(): void
    {
        $recorrido = $this->recorridoEnCurso(capacidad: 100);
        $primera = $this->estacionDeOrden($recorrido->linea, 1);

        $this->llegar($recorrido->id, $primera->id, 24)
            ->assertSessionHas('advertencia', "{$recorrido->bus->codigo} llegó a {$primera->nombre} con 24 de 100 pasajeros (24 %): esperar 5 minutos adicionales.");

        $this->assertSame(5, $recorrido->visitas()->first()->espera_extra_min);
        $this->assertSame(1, $this->alertasActivas('baja_ocupacion', ['bus_id' => $recorrido->bus_id, 'estacion_id' => $primera->id]));
    }

    public function test_ocupacion_normal_desde_el_25_por_ciento_no_genera_alerta(): void
    {
        $recorrido = $this->recorridoEnCurso(capacidad: 100);

        $this->llegar($recorrido->id, $this->estacionDeOrden($recorrido->linea, 1)->id, 25)
            ->assertSessionMissing('advertencia');

        $this->assertSame(0, $recorrido->visitas()->first()->espera_extra_min);
        $this->assertSame(0, $this->alertasActivas('baja_ocupacion', ['bus_id' => $recorrido->bus_id]));
    }

    public function test_en_la_ultima_estacion_no_hay_baja_ocupacion(): void
    {
        $recorrido = $this->recorridoEnCurso(capacidad: 100, estaciones: 2);
        $this->llegar($recorrido->id, $this->estacionDeOrden($recorrido->linea, 1)->id, 60);

        // En la terminal bajan todos: 0 pasajeros no es baja ocupación.
        $this->llegar($recorrido->id, $this->estacionDeOrden($recorrido->linea, 2)->id, 0)->assertSessionMissing('advertencia');

        $this->assertSame(0, $this->alertasActivas('baja_ocupacion', ['bus_id' => $recorrido->bus_id]));
    }

    public function test_no_se_duplican_alertas_activas(): void
    {
        $recorrido = $this->recorridoEnCurso(capacidad: 100);
        $this->llegar($recorrido->id, $this->estacionDeOrden($recorrido->linea, 1)->id, 10);
        $this->llegar($recorrido->id, $this->estacionDeOrden($recorrido->linea, 2)->id, 12);
        $this->assertSame(1, $this->alertasActivas('baja_ocupacion', ['bus_id' => $recorrido->bus_id]));
        // La alerta existente se actualiza con la última estación.
        $this->assertStringContainsString('con 12 de 100', Alerta::where('bus_id', $recorrido->bus_id)->where('estado', 'activa')->value('mensaje'));

        $operador = $this->operadorTrebol();
        [$capacidad] = app(AlertaService::class)->capacidadReferencia($operador->estacion);
        $this->actingAs($operador);
        $this->post(route('mi-estacion.pasajeros'), ['cantidad' => $capacidad * 2]);
        $this->post(route('mi-estacion.pasajeros'), ['cantidad' => $capacidad * 3]);
        $this->assertSame(1, $this->alertasActivas('alta_demanda', ['estacion_id' => $operador->estacion_id]));
    }

    public function test_al_recuperar_el_minimo_la_alerta_de_insuficiencia_se_atiende_sola(): void
    {
        $linea = $this->lineaDePrueba(2);
        $primero = $this->busEn($linea);
        $segundo = $this->busEn($linea);
        $this->actingAs($this->administrador());

        // Quitar un bus deja la línea con 1 de 2: se crea la alerta.
        $this->put(route('buses.update', $segundo), [...$segundo->only(['parqueo_id', 'placa', 'codigo', 'capacidad_maxima', 'estado']), 'linea_id' => '']);
        $this->assertSame(1, $this->alertasActivas('insuficiencia_unidades', ['linea_id' => $linea->id]));

        // Devolverlo recupera el mínimo: la alerta queda atendida con su fecha.
        $this->put(route('buses.update', $segundo), [...$segundo->only(['parqueo_id', 'placa', 'codigo', 'capacidad_maxima', 'estado']), 'linea_id' => $linea->id])
            ->assertSessionHas('info', "La línea {$linea->codigo} recuperó el mínimo de buses: su alerta de insuficiencia quedó atendida.");

        $alerta = Alerta::where('linea_id', $linea->id)->where('tipo', 'insuficiencia_unidades')->firstOrFail();
        $this->assertSame('atendida', $alerta->estado);
        $this->assertNotNull($alerta->atendida_en);
        $this->assertModelExists($primero);
    }
}
