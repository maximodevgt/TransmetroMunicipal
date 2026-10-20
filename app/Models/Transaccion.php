<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Recarga o pago (pasaje) de una Tarjeta Transmetro. saldo_resultante lo escribe el trigger
 * trg_transacciones_validar; por eso no está en $fillable.
 * Nunca guarda el número completo de una tarjeta bancaria ni el CVV.
 */
#[Table('transacciones')]
#[Fillable([
    'tarjeta_id', 'acceso_id', 'estacion_id', 'user_id', 'tipo', 'metodo', 'monto', 'estado', 'motivo_rechazo',
    'tarjeta_marca', 'tarjeta_ultimos4', 'referencia', 'llave_idempotencia',
])]
class Transaccion extends Model
{
    protected function casts(): array
    {
        return ['monto' => 'decimal:2', 'saldo_resultante' => 'decimal:2'];
    }

    public function tarjeta(): BelongsTo
    {
        return $this->belongsTo(Tarjeta::class, 'tarjeta_id');
    }

    public function acceso(): BelongsTo
    {
        return $this->belongsTo(Acceso::class, 'acceso_id');
    }

    public function estacion(): BelongsTo
    {
        return $this->belongsTo(Estacion::class, 'estacion_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function aprobada(): bool
    {
        return $this->estado === 'aprobada';
    }

    public function numeroComprobante(): string
    {
        return sprintf('C-%07d', $this->id);
    }

    public function descripcion(): string
    {
        return match (true) {
            $this->tipo === 'pago' => 'Pasaje',
            $this->metodo === 'tarjeta' => 'Recarga en línea',
            $this->metodo === 'efectivo' => 'Recarga en efectivo',
            default => 'Recarga',
        };
    }

    public function medioDePago(): ?string
    {
        return $this->tarjeta_ultimos4 ? "{$this->tarjeta_marca} •••• {$this->tarjeta_ultimos4}" : null;
    }

    /**
     * Transacciones que puede ver el usuario: el administrador todas; el operador las de su estación.
     * Los registros del script inicial no tienen estación: cuenta la de su acceso.
     * Un operador sin estación no ve ninguna (0 no coincide con nada).
     */
    public function scopeVisiblesPara(Builder $query, User $usuario): void
    {
        $estacion = (int) $usuario->estacion_id;
        $query->when(! $usuario->esAdministrador(), fn ($q) => $q->where(fn ($w) => $w
            ->where('estacion_id', $estacion)
            ->orWhere(fn ($v) => $v->whereNull('estacion_id')->whereIn('acceso_id', Acceso::where('estacion_id', $estacion)->select('id')))));
    }

    public function puedeVerla(User $usuario): bool
    {
        $estacion = $this->estacion_id ?? $this->acceso?->estacion_id;

        return $usuario->esAdministrador()
            || ($usuario->estacion_id !== null && $estacion === $usuario->estacion_id)
            || $this->user_id === $usuario->id;
    }
}
