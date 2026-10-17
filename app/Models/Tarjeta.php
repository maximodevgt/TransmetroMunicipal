<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tarjeta Transmetro. El saldo SOLO lo cambian los triggers de transacciones:
 * no está en $fillable y nunca se calcula ni se asigna desde PHP.
 */
#[Table('tarjetas')]
#[Fillable(['pasajero_id', 'codigo', 'estado', 'fecha_emision'])]
class Tarjeta extends Model
{
    public const ESTADOS = ['activa' => 'Activa', 'bloqueada' => 'Bloqueada', 'suspendida' => 'Suspendida', 'vencida' => 'Vencida'];

    protected function casts(): array
    {
        return ['saldo' => 'decimal:2', 'fecha_emision' => 'date'];
    }

    public function pasajero(): BelongsTo
    {
        return $this->belongsTo(Pasajero::class, 'pasajero_id');
    }

    public function transacciones(): HasMany
    {
        return $this->hasMany(Transaccion::class, 'tarjeta_id');
    }

    public function activa(): bool
    {
        return $this->estado === 'activa';
    }

    /**
     * Siguiente código TM-000NNN a partir del mayor existente.
     */
    public static function siguienteCodigo(): string
    {
        $ultimo = (int) static::where('codigo', 'like', 'TM-%')->selectRaw('MAX(CAST(SUBSTRING(codigo, 4) AS UNSIGNED)) AS n')->value('n');

        return sprintf('TM-%06d', $ultimo + 1);
    }
}
