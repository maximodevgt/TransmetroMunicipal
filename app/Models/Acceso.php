<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('accesos')]
#[Fillable(['estacion_id', 'nombre'])]
class Acceso extends Model
{
    public function estacion(): BelongsTo
    {
        return $this->belongsTo(Estacion::class, 'estacion_id');
    }

    public function guardias(): HasMany
    {
        return $this->hasMany(Guardia::class, 'acceso_id');
    }
}
