<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('estaciones')]
#[Fillable(['municipalidad_id', 'nombre', 'direccion', 'estado'])]
class Estacion extends Model
{
    public function municipalidad(): BelongsTo
    {
        return $this->belongsTo(Municipalidad::class, 'municipalidad_id');
    }

    public function lineas(): BelongsToMany
    {
        return $this->belongsToMany(Linea::class, 'linea_estacion', 'estacion_id', 'linea_id')
            ->withPivot('orden', 'distancia_anterior_km')
            ->withTimestamps();
    }

    public function accesos(): HasMany
    {
        return $this->hasMany(Acceso::class, 'estacion_id');
    }

    public function operadores(): HasMany
    {
        return $this->hasMany(User::class, 'estacion_id');
    }
}
