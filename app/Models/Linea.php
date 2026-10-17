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
    /** Color de cada línea en el mapa; las líneas nuevas toman uno de la paleta según su id. */
    public const COLORES = ['L12' => '#0369a1', 'L1' => '#16a34a', 'L2' => '#9333ea'];

    private const PALETA = ['#0891b2', '#db2777', '#65a30d', '#7c3aed', '#c2410c', '#0f766e'];

    public function color(): string
    {
        return self::COLORES[$this->codigo] ?? self::PALETA[$this->id % count(self::PALETA)];
    }

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

    public function recorridos(): HasMany
    {
        return $this->hasMany(Recorrido::class, 'linea_id');
    }
}
