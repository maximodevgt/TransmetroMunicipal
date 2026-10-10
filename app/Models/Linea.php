<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('lineas')]
#[Fillable(['municipalidad_id', 'codigo', 'nombre', 'estado'])]
class Linea extends Model
{
    public function municipalidad(): BelongsTo
    {
        return $this->belongsTo(Municipalidad::class, 'municipalidad_id');
    }

    /**
     * Estaciones de la linea en orden de recorrido (tabla linea_estacion).
     */
    public function estaciones(): BelongsToMany
    {
        return $this->belongsToMany(Estacion::class, 'linea_estacion', 'linea_id', 'estacion_id')
            ->withPivot('id', 'orden', 'distancia_anterior_km')
            ->withTimestamps()
            ->orderByPivot('orden');
    }

    public function buses(): HasMany
    {
        return $this->hasMany(Bus::class, 'linea_id');
    }
}
