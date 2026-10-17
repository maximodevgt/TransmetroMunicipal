<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('alertas')]
#[Fillable(['estacion_id', 'bus_id', 'linea_id', 'tipo', 'mensaje', 'estado', 'atendida_en'])]
class Alerta extends Model
{
    public const TIPOS = [
        'alta_demanda' => 'Alta demanda',
        'baja_ocupacion' => 'Baja ocupación',
        'asignacion' => 'Asignación',
        'insuficiencia_unidades' => 'Insuficiencia de unidades',
        'incidencia' => 'Incidencia',
    ];

    protected function casts(): array
    {
        return ['atendida_en' => 'datetime'];
    }

    public function estacion(): BelongsTo
    {
        return $this->belongsTo(Estacion::class, 'estacion_id');
    }

    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class, 'bus_id');
    }

    public function linea(): BelongsTo
    {
        return $this->belongsTo(Linea::class, 'linea_id');
    }

    /**
     * Alertas que puede ver el usuario: el administrador todas, el operador solo las de su estación.
     */
    public function scopeVisiblesPara(Builder $query, User $usuario): void
    {
        $query->when(! $usuario->esAdministrador(), fn ($q) => $q->where('estacion_id', $usuario->estacion_id));
    }

    public function scopeActivasPara(Builder $query, User $usuario): void
    {
        $query->visiblesPara($usuario)->where('estado', 'activa');
    }

    public function puedeVerla(User $usuario): bool
    {
        return $usuario->esAdministrador() || ($usuario->estacion_id !== null && $this->estacion_id === $usuario->estacion_id);
    }

    public function activa(): bool
    {
        return $this->estado === 'activa';
    }

    public function tipoTexto(): string
    {
        return self::TIPOS[$this->tipo] ?? ucfirst(str_replace('_', ' ', $this->tipo));
    }

    /**
     * Estación, bus o línea donde ocurre la alerta, para mostrarla en una línea.
     */
    public function lugar(): string
    {
        return collect([
            $this->estacion ? "Estación {$this->estacion->nombre}" : null,
            $this->bus?->codigo,
            $this->linea?->codigo,
        ])->filter()->implode(' · ');
    }
}
