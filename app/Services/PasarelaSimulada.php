<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Pasarela de pagos SIMULADA para la recarga en línea. No se conecta a ningún banco.
 * Recibe el número solo para decidir el resultado; lo único que devuelve para guardar
 * es la marca, los últimos 4 dígitos y una referencia inventada.
 */
class PasarelaSimulada
{
    public static function limpiar(string $numero): string
    {
        return preg_replace('/\D/', '', $numero);
    }

    public static function marca(string $numero): string
    {
        $numero = self::limpiar($numero);

        return match (true) {
            (bool) preg_match('/^4/', $numero) => 'Visa',
            (bool) preg_match('/^(5[1-5]|2(2[2-9]|[3-6]\d|7[01]|720))/', $numero) => 'Mastercard',
            (bool) preg_match('/^3[47]/', $numero) => 'American Express',
            default => 'Tarjeta',
        };
    }

    /**
     * Algoritmo de Luhn (dígito verificador de las tarjetas bancarias).
     */
    public static function luhn(string $numero): bool
    {
        $numero = self::limpiar($numero);
        if (strlen($numero) < 13 || strlen($numero) > 19) {
            return false;
        }

        $suma = 0;
        foreach (array_reverse(str_split($numero)) as $i => $digito) {
            $valor = (int) $digito;
            if ($i % 2 === 1) {
                $valor *= 2;
                $valor = $valor > 9 ? $valor - 9 : $valor;
            }
            $suma += $valor;
        }

        return $suma % 10 === 0;
    }

    /**
     * Decide el resultado del cobro simulado.
     *
     * @return array{aprobada: bool, motivo: ?string, marca: string, ultimos4: string, referencia: string}
     */
    public function cobrar(string $numero): array
    {
        $numero = self::limpiar($numero);
        $pruebas = config('transmetro.pasarela.tarjetas_prueba');
        $motivo = $pruebas[$numero] ?? null;

        return [
            'aprobada' => $motivo === null,
            'motivo' => $motivo,
            'marca' => self::marca($numero),
            'ultimos4' => substr($numero, -4),
            'referencia' => 'SIM-'.Str::upper(Str::random(10)),
        ];
    }
}
