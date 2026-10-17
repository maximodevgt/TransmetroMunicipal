<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Titular de una Tarjeta Transmetro.
 */
#[Table('pasajeros')]
#[Fillable(['nombre', 'dpi', 'telefono'])]
class Pasajero extends Model
{
    public function tarjetas(): HasMany
    {
        return $this->hasMany(Tarjeta::class, 'pasajero_id');
    }
}
