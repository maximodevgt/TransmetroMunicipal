<?php

namespace Tests\Unit;

use App\Support\Interpolacion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class InterpolacionTest extends TestCase
{
    private const TREBOL = ['lat' => 14.620000, 'lng' => -90.530000];

    private const SANTA_CECILIA = ['lat' => 14.625000, 'lng' => -90.526000];

    public static function momentos(): array
    {
        return [
            'al salir' => [100.0, 0.0],
            'a la mitad' => [105.0, 0.5],
            'a tres cuartos' => [107.5, 0.75],
            'al llegar' => [110.0, 1.0],
            'antes de salir (se queda en 0)' => [90.0, 0.0],
            'después de llegar (se queda en 1)' => [130.0, 1.0],
        ];
    }

    #[DataProvider('momentos')]
    public function test_la_fraccion_del_tramo_depende_del_tiempo(float $ahora, float $esperada): void
    {
        $this->assertEqualsWithDelta($esperada, Interpolacion::fraccion(100.0, 110.0, $ahora), 1e-9);
    }

    public function test_un_tramo_sin_duracion_salta_al_final_solo_al_llegar(): void
    {
        $this->assertSame(0.0, Interpolacion::fraccion(100.0, 100.0, 99.0));
        $this->assertSame(1.0, Interpolacion::fraccion(100.0, 100.0, 100.0));
    }

    public function test_la_posicion_queda_entre_las_dos_estaciones(): void
    {
        $mitad = Interpolacion::punto(self::TREBOL, self::SANTA_CECILIA, 0.5);

        $this->assertEqualsWithDelta(14.6225, $mitad['lat'], 1e-6);
        $this->assertEqualsWithDelta(-90.528, $mitad['lng'], 1e-6);

        foreach ([0.1, 0.33, 0.8] as $fraccion) {
            $punto = Interpolacion::punto(self::TREBOL, self::SANTA_CECILIA, $fraccion);
            $this->assertGreaterThan(self::TREBOL['lat'], $punto['lat']);
            $this->assertLessThan(self::SANTA_CECILIA['lat'], $punto['lat']);
            $this->assertGreaterThan(self::TREBOL['lng'], $punto['lng']);
            $this->assertLessThan(self::SANTA_CECILIA['lng'], $punto['lng']);
        }
    }

    public function test_en_los_extremos_la_posicion_es_la_de_cada_estacion(): void
    {
        $this->assertSame(self::TREBOL, Interpolacion::punto(self::TREBOL, self::SANTA_CECILIA, 0.0));
        $this->assertSame(self::SANTA_CECILIA, Interpolacion::punto(self::TREBOL, self::SANTA_CECILIA, 1.0));
        $this->assertSame(self::SANTA_CECILIA, Interpolacion::punto(self::TREBOL, self::SANTA_CECILIA, 1.7), 'La fracción no se sale del tramo.');
    }
}
