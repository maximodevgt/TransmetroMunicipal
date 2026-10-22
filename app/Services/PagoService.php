<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Acceso;
use App\Models\Bitacora;
use App\Models\Pasajero;
use App\Models\Tarjeta;
use App\Models\Transaccion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Operaciones de la Tarjeta Transmetro. El saldo lo calculan los triggers:
 * aquí solo se insertan transacciones y luego se vuelve a leer la tarjeta.
 */
class PagoService
{
    public function __construct(private PasarelaSimulada $pasarela) {}

    public function emitir(Pasajero $pasajero): Tarjeta
    {
        // El código es único: si dos emisiones chocan, se reintenta con el siguiente.
        for ($intento = 0; ; $intento++) {
            try {
                $tarjeta = Tarjeta::create([
                    'pasajero_id' => $pasajero->id,
                    'codigo' => Tarjeta::siguienteCodigo(),
                    'estado' => 'activa',
                    'fecha_emision' => today(),
                ]);
                break;
            } catch (QueryException $e) {
                if ($intento >= 2 || ($e->errorInfo[1] ?? null) !== 1062) {
                    throw $e;
                }
            }
        }
        Bitacora::registrar('emitir', $tarjeta, "Tarjeta {$tarjeta->codigo} emitida a {$pasajero->nombre}");

        return $tarjeta->refresh();
    }

    public function bloquear(Tarjeta $tarjeta, string $motivo): void
    {
        $this->cambiarEstado($tarjeta, 'bloqueada', $motivo);
    }

    public function desbloquear(Tarjeta $tarjeta, string $motivo): void
    {
        $this->cambiarEstado($tarjeta, 'activa', $motivo);
    }

    /**
     * Cambia el estado de la tarjeta (activa, bloqueada, suspendida o vencida) con el motivo en la bitácora.
     * Solo con la tarjeta activa se pagan pasajes y se reciben recargas (lo verifica el trigger).
     */
    public function cambiarEstado(Tarjeta $tarjeta, string $estado, string $motivo): void
    {
        if ($tarjeta->estado === $estado) {
            throw new ReglaNegocioException("La tarjeta {$tarjeta->codigo} ya está ".Tarjeta::ESTADOS[$estado].'.');
        }
        $accion = match ($estado) {
            'activa' => 'desbloquear',
            'bloqueada' => 'bloquear',
            'suspendida' => 'suspender',
            default => 'cambiar_estado',
        };
        $texto = $estado === 'activa' ? 'activada' : mb_strtolower(Tarjeta::ESTADOS[$estado]);
        $tarjeta->update(['estado' => $estado]);
        Bitacora::registrar($accion, $tarjeta, "Tarjeta {$tarjeta->codigo} {$texto}: {$motivo}");
    }

    /**
     * Recarga en ventanilla. La registra un operador en su estación.
     */
    public function recargarEfectivo(Tarjeta $tarjeta, float $monto, User $operador): Transaccion
    {
        $transaccion = $this->registrar($tarjeta, [
            'tipo' => 'recarga', 'metodo' => 'efectivo', 'monto' => $monto,
            'user_id' => $operador->id, 'estacion_id' => $operador->estacion_id,
        ]);
        $this->anotarRecarga($transaccion, 'en efectivo');

        return $transaccion;
    }

    /**
     * Recarga en línea con la pasarela simulada. La llave de idempotencia identifica el intento:
     * si llega dos veces (doble clic, recarga de página) se devuelve la misma transacción sin cobrar otra vez.
     *
     * @return array{0: Transaccion, 1: bool} la transacción y si ya existía
     */
    public function recargarEnLinea(Tarjeta $tarjeta, float $monto, string $numero, string $llave, User $usuario): array
    {
        if ($existente = Transaccion::where('llave_idempotencia', $llave)->first()) {
            return [$existente, true];
        }

        // El número solo se usa aquí para decidir el resultado; no se guarda ni se registra.
        $cobro = $this->pasarela->cobrar($numero);
        $datos = [
            'tipo' => 'recarga', 'metodo' => 'tarjeta', 'monto' => $monto,
            'user_id' => $usuario->id, 'estacion_id' => $usuario->estacion_id,
            'tarjeta_marca' => $cobro['marca'], 'tarjeta_ultimos4' => $cobro['ultimos4'],
            'referencia' => $cobro['referencia'], 'llave_idempotencia' => $llave,
        ];

        try {
            $transaccion = $cobro['aprobada']
                ? $this->registrar($tarjeta, $datos)
                : $this->insertar($tarjeta, [...$datos, 'estado' => 'rechazada', 'motivo_rechazo' => $cobro['motivo']]);
        } catch (QueryException $e) {
            // Dos envíos simultáneos con la misma llave: gana el primero.
            if (($e->errorInfo[1] ?? null) === 1062) {
                return [Transaccion::where('llave_idempotencia', $llave)->firstOrFail(), true];
            }
            throw $e;
        }
        $this->anotarRecarga($transaccion, "en línea con {$transaccion->medioDePago()}");

        return [$transaccion, false];
    }

    /**
     * Cobra el pasaje en el torniquete. Devuelve null si la tarjeta no existe (no hay a qué registrarle el rechazo).
     */
    public function cobrarPasaje(string $codigo, Acceso $acceso, User $usuario): ?Transaccion
    {
        $tarjeta = Tarjeta::where('codigo', strtoupper(trim($codigo)))->first();
        if ($tarjeta === null) {
            return null;
        }

        return $this->registrar($tarjeta, [
            'tipo' => 'pago', 'monto' => config('transmetro.pasaje'),
            'acceso_id' => $acceso->id, 'estacion_id' => $acceso->estacion_id, 'user_id' => $usuario->id,
        ]);
    }

    /**
     * Intenta la transacción como aprobada. Si el trigger la rechaza (tarjeta no activa o saldo
     * insuficiente), se registra como rechazada con el motivo del trigger, sin tocar el saldo.
     */
    private function registrar(Tarjeta $tarjeta, array $datos): Transaccion
    {
        try {
            return $this->insertar($tarjeta, [...$datos, 'estado' => 'aprobada']);
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? null) !== '45000') {
                throw $e;
            }

            return $this->insertar($tarjeta, [...$datos, 'estado' => 'rechazada', 'motivo_rechazo' => mb_substr($e->errorInfo[2], 0, 150)]);
        }
    }

    private function insertar(Tarjeta $tarjeta, array $datos): Transaccion
    {
        $transaccion = DB::transaction(fn () => $tarjeta->transacciones()->create($datos));
        $tarjeta->refresh();

        // saldo_resultante lo escribió el trigger: se lee de la base.
        return $transaccion->refresh();
    }

    private function anotarRecarga(Transaccion $transaccion, string $como): void
    {
        $tarjeta = $transaccion->tarjeta;
        $monto = number_format((float) $transaccion->monto, 2);
        $detalle = $transaccion->aprobada()
            ? "Recarga {$como} de Q{$monto} a {$tarjeta->codigo}: saldo Q{$transaccion->saldo_resultante}"
            : "Recarga {$como} de Q{$monto} a {$tarjeta->codigo} rechazada: {$transaccion->motivo_rechazo}";
        Bitacora::registrar('recargar', $transaccion, $detalle);
    }
}
