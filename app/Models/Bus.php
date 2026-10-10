<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('buses')]
#[Fillable(['linea_id', 'parqueo_id', 'placa', 'codigo', 'capacidad_maxima', 'estado'])]
class Bus extends Model
{
    public const ESTADOS = ['disponible', 'en_ruta', 'mantenimiento', 'inactivo'];

    public function linea(): BelongsTo
    {
        return $this->belongsTo(Linea::class, 'linea_id');
    }

    public function parqueo(): BelongsTo
    {
        return $this->belongsTo(Parqueo::class, 'parqueo_id');
    }
}
