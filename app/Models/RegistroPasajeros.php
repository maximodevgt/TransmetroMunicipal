<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pasajeros en espera que el operador registra en su estación.
 */
#[Table('registros_pasajeros')]
#[Fillable(['estacion_id', 'user_id', 'cantidad', 'registrado_en'])]
class RegistroPasajeros extends Model
{
    protected function casts(): array
    {
        return ['registrado_en' => 'datetime'];
    }

    public function estacion(): BelongsTo
    {
        return $this->belongsTo(Estacion::class, 'estacion_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
