<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function recorridos(): HasMany
    {
        return $this->hasMany(Recorrido::class, 'bus_id');
    }

    public function recorridoEnCurso(): HasOne
    {
        return $this->hasOne(Recorrido::class, 'bus_id')->where('estado', 'en_curso');
    }

    /**
     * Un bus está en ruta mientras tenga un recorrido en curso.
     */
    public function estaEnRuta(): bool
    {
        return $this->recorridoEnCurso()->exists();
    }
}
