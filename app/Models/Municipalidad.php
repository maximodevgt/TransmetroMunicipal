<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('municipalidades')]
#[Fillable(['nombre', 'departamento'])]
class Municipalidad extends Model
{
    public function lineas(): HasMany
    {
        return $this->hasMany(Linea::class, 'municipalidad_id');
    }

    public function estaciones(): HasMany
    {
        return $this->hasMany(Estacion::class, 'municipalidad_id');
    }
}
