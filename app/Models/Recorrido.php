<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Table('recorridos')]
#[Fillable(['linea_id', 'bus_id', 'piloto_id', 'inicio', 'fin', 'estado'])]
class Recorrido extends Model
{
    public const ESTADOS = ['en_curso' => 'En curso', 'finalizado' => 'Finalizado', 'cancelado' => 'Cancelado'];

    protected function casts(): array
    {
        return ['inicio' => 'datetime', 'fin' => 'datetime'];
    }

    public function linea(): BelongsTo
    {
        return $this->belongsTo(Linea::class, 'linea_id');
    }

    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class, 'bus_id');
    }

    public function piloto(): BelongsTo
    {
        return $this->belongsTo(Piloto::class, 'piloto_id');
    }

    /**
     * Estaciones visitadas en orden de llegada.
     */
    public function visitas(): HasMany
    {
        return $this->hasMany(RecorridoEstacion::class, 'recorrido_id')->orderBy('hora_llegada')->orderBy('id');
    }

    public function enCurso(): bool
    {
        return $this->estado === 'en_curso';
    }

    public function ultimaVisita(): ?RecorridoEstacion
    {
        return $this->visitas()->reorder()->orderByDesc('hora_llegada')->orderByDesc('id')->first();
    }

    /**
     * Siguiente estación según el orden de la línea, o null si ya pasó por todas.
     */
    public function siguienteEstacion(): ?Estacion
    {
        $ultima = $this->ultimaVisita();
        $ordenActual = $ultima
            ? (int) DB::table('linea_estacion')->where('linea_id', $this->linea_id)->where('estacion_id', $ultima->estacion_id)->value('orden')
            : 0;

        return $this->linea->estaciones()->wherePivot('orden', $ordenActual + 1)->first();
    }

    /**
     * Distancia recorrida: suma de distancia_anterior_km de las estaciones visitadas en esta línea.
     */
    public function distanciaRecorrida(): float
    {
        return (float) DB::table('recorrido_estaciones as re')
            ->join('linea_estacion as le', fn ($j) => $j->on('le.estacion_id', '=', 're.estacion_id')->where('le.linea_id', $this->linea_id))
            ->where('re.recorrido_id', $this->id)
            ->sum('le.distancia_anterior_km');
    }
}
