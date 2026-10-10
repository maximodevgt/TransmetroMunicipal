<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('historial_educativo')]
#[Fillable(['piloto_id', 'nivel', 'institucion', 'titulo', 'anio'])]
class HistorialEducativo extends Model
{
    public const NIVELES = ['primaria', 'basico', 'diversificado', 'universitario', 'otro'];

    public function piloto(): BelongsTo
    {
        return $this->belongsTo(Piloto::class, 'piloto_id');
    }
}
