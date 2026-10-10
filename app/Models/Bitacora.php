<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Registro de auditoria. La tabla solo tiene created_at, con valor por defecto en MySQL.
 */
#[Table('bitacora', timestamps: false)]
#[Fillable(['user_id', 'accion', 'tabla', 'registro_id', 'detalle'])]
class Bitacora extends Model
{
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function registrar(string $accion, Model $registro, string $detalle): void
    {
        static::create([
            'user_id' => Auth::id(),
            'accion' => $accion,
            'tabla' => $registro->getTable(),
            'registro_id' => $registro->getKey(),
            'detalle' => mb_substr($detalle, 0, 255),
        ]);
    }
}
