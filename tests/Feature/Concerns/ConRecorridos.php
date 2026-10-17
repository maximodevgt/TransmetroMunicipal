<?php

namespace Tests\Feature\Concerns;

use App\Models\Bus;
use App\Models\Estacion;
use App\Models\Linea;
use App\Models\Piloto;
use App\Models\Recorrido;
use App\Services\RecorridoService;

/**
 * Escenarios propios para las pruebas de recorridos: no dependen de los datos de desarrollo de la base.
 * Todo se crea dentro de la transacción de la prueba y se revierte al terminar.
 */
trait ConRecorridos
{
    private int $consecutivo = 0;

    /**
     * Línea de prueba con las primeras $cantidad estaciones (Centra Sur, Monte María, Javier...)
     * a 1.5 km entre sí.
     */
    protected function lineaDePrueba(int $cantidad = 3): Linea
    {
        $linea = Linea::create(['municipalidad_id' => 1, 'codigo' => 'LP'.(++$this->consecutivo), 'nombre' => 'Línea de prueba', 'estado' => 'activa']);
        Estacion::orderBy('id')->take($cantidad)->get()->each(
            fn (Estacion $estacion, int $i) => $linea->estaciones()->attach($estacion->id, ['orden' => $i + 1, 'distancia_anterior_km' => $i === 0 ? 0 : 1.5]),
        );

        return $linea;
    }

    protected function busEn(?Linea $linea, int $capacidad = 100): Bus
    {
        $n = ++$this->consecutivo;

        return Bus::create([
            'linea_id' => $linea?->id, 'parqueo_id' => 1, 'placa' => "PRR-{$n}", 'codigo' => "BUS-PR{$n}",
            'capacidad_maxima' => $capacidad, 'estado' => 'disponible',
        ]);
    }

    protected function pilotoNuevo(string $estado = 'activo'): Piloto
    {
        $n = ++$this->consecutivo;

        return Piloto::create([
            'nombre' => "Piloto de prueba {$n}", 'dpi' => '30000000099'.str_pad((string) $n, 2, '0', STR_PAD_LEFT),
            'licencia' => "P-9900{$n}", 'residencia' => 'Zona 1', 'telefono' => '55000000', 'estado' => $estado,
        ]);
    }

    /**
     * Recorrido en curso en una línea de prueba, con un bus de la capacidad indicada.
     */
    protected function recorridoEnCurso(int $capacidad = 100, int $estaciones = 3): Recorrido
    {
        $linea = $this->lineaDePrueba($estaciones);

        return app(RecorridoService::class)->iniciar($linea, $this->busEn($linea, $capacidad), $this->pilotoNuevo());
    }

    protected function estacionDeOrden(Linea $linea, int $orden): Estacion
    {
        return $linea->estaciones()->wherePivot('orden', $orden)->firstOrFail();
    }
}
