<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('pilotos')]
#[Fillable(['nombre', 'dpi', 'licencia', 'residencia', 'telefono', 'email', 'estado'])]
class Piloto extends Model
{
    public function historialEducativo(): HasMany
    {
        return $this->hasMany(HistorialEducativo::class, 'piloto_id')->orderBy('anio');
    }
}
