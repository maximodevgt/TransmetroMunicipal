<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Llegada de un bus a una estación durante un recorrido.
 */
#[Table('recorrido_estaciones')]
#[Fillable(['recorrido_id', 'estacion_id', 'hora_llegada', 'hora_salida', 'pasajeros_a_bordo', 'espera_extra_min'])]
class RecorridoEstacion extends Model
{
    protected function casts(): array
    {
        return ['hora_llegada' => 'datetime', 'hora_salida' => 'datetime'];
    }

    public function recorrido(): BelongsTo
    {
        return $this->belongsTo(Recorrido::class, 'recorrido_id');
    }

    public function estacion(): BelongsTo
    {
        return $this->belongsTo(Estacion::class, 'estacion_id');
    }
}
