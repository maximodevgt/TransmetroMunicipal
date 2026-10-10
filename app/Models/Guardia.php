<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('guardias')]
#[Fillable(['acceso_id', 'nombre', 'dpi', 'telefono', 'turno'])]
class Guardia extends Model
{
    public function acceso(): BelongsTo
    {
        return $this->belongsTo(Acceso::class, 'acceso_id');
    }
}
