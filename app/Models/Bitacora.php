<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Registro de auditoría. La tabla solo tiene created_at, con valor por defecto en MySQL.
 */
#[Table('bitacora', timestamps: false)]
#[Fillable(['user_id', 'accion', 'tabla', 'registro_id', 'detalle'])]
class Bitacora extends Model
{
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Enlace al elemento afectado por cada registro, o null si ya no existe o no tiene pantalla.
     * Consulta una vez por tabla para no hacer una consulta por registro.
     *
     * @return array<int, string|null> id de bitácora => url
     */
    public static function enlaces(Collection $registros): array
    {
        $rutas = [
            'buses' => fn ($ids) => Bus::whereKey($ids)->pluck('id')->mapWithKeys(fn ($id) => [$id => route('buses.show', $id)]),
            'lineas' => fn ($ids) => Linea::whereKey($ids)->pluck('id')->mapWithKeys(fn ($id) => [$id => route('lineas.show', $id)]),
            'estaciones' => fn ($ids) => Estacion::whereKey($ids)->pluck('id')->mapWithKeys(fn ($id) => [$id => route('estaciones.show', $id)]),
            'parqueos' => fn ($ids) => Parqueo::whereKey($ids)->pluck('id')->mapWithKeys(fn ($id) => [$id => route('parqueos.show', $id)]),
            'pilotos' => fn ($ids) => Piloto::whereKey($ids)->pluck('id')->mapWithKeys(fn ($id) => [$id => route('pilotos.show', $id)]),
            'users' => fn ($ids) => User::whereKey($ids)->pluck('id')->mapWithKeys(fn ($id) => [$id => route('usuarios.edit', $id)]),
            'alertas' => fn ($ids) => Alerta::whereKey($ids)->pluck('id')->mapWithKeys(fn ($id) => [$id => route('alertas.show', $id)]),
            'recorridos' => fn ($ids) => Recorrido::whereKey($ids)->pluck('id')->mapWithKeys(fn ($id) => [$id => route('recorridos.show', $id)]),
            // Accesos, guardias y registros de pasajeros se ven en la ficha de su estación.
            'accesos' => fn ($ids) => Acceso::whereKey($ids)->pluck('estacion_id', 'id')->map(fn ($estacion) => route('estaciones.show', $estacion)),
            'guardias' => fn ($ids) => Guardia::with('acceso')->whereKey($ids)->get()->mapWithKeys(fn ($g) => [$g->id => route('estaciones.show', $g->acceso->estacion_id)]),
            'registros_pasajeros' => fn ($ids) => RegistroPasajeros::whereKey($ids)->pluck('estacion_id', 'id')->map(fn ($estacion) => route('estaciones.show', $estacion)),
        ];

        $urls = $registros->mapWithKeys(fn ($b) => [$b->id => null])->all();

        foreach ($registros->whereNotNull('registro_id')->groupBy('tabla') as $tabla => $grupo) {
            if (! isset($rutas[$tabla])) {
                continue;
            }
            $existentes = $rutas[$tabla]($grupo->pluck('registro_id')->unique()->values());
            foreach ($grupo as $b) {
                $urls[$b->id] = $existentes[$b->registro_id] ?? null;
            }
        }

        return $urls;
    }

    public static function registrar(string $accion, Model $registro, string $detalle): void
    {
        static::create([
            'user_id' => Auth::id(),
            'accion' => $accion,
            'tabla' => $registro->getTable(),
            'registro_id' => $registro->getKey(),
            'detalle' => mb_substr($detalle, 0, 255),
        ]);
    }
}
