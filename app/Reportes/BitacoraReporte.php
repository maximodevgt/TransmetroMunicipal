<?php

namespace App\Reportes;

use App\Models\Bitacora;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bitácora por usuario, acción y fecha. El operador ve solo lo que hizo él.
 */
class BitacoraReporte extends Reporte
{
    public function clave(): string
    {
        return 'bitacora';
    }

    public function titulo(): string
    {
        return 'Bitácora';
    }

    public function descripcion(): string
    {
        return 'Operaciones registradas por usuario, acción y fecha.';
    }

    public function icono(): string
    {
        return 'bitacora';
    }

    public function usaFechas(): bool
    {
        return true;
    }

    public function camposFiltro(User $usuario): array
    {
        $propios = fn ($q) => $q->when(! $usuario->esAdministrador(), fn ($q) => $q->where('user_id', $usuario->id));

        return [
            ...($usuario->esAdministrador()
                ? ['user_id' => ['etiqueta' => 'Usuario', 'vacio' => 'Todos', 'opciones' => User::whereIn('id', Bitacora::select('user_id')->distinct())
                    ->orderBy('name')->pluck('name', 'id')->all()]]
                : []),
            'accion' => ['etiqueta' => 'Acción', 'vacio' => 'Todas', 'opciones' => Bitacora::query()->tap($propios)->distinct()->orderBy('accion')
                ->pluck('accion')->mapWithKeys(fn ($a) => [$a => ucfirst(str_replace('_', ' ', $a))])->all()],
        ];
    }

    public function columnas(array $filtros, User $usuario): array
    {
        return [
            'created_at' => ['Fecha', 'fecha'],
            'usuario' => ['Usuario', 'texto'],
            'accion' => ['Acción', 'estado'],
            'tabla' => ['Tabla', 'texto'],
            'registro_id' => ['Registro', 'texto'],
            'detalle' => ['Detalle', 'texto', 'min-w-64'],
        ];
    }

    public function consulta(array $filtros, User $usuario): Builder
    {
        return $this->enRango(DB::table('bitacora as b'), 'b.created_at', $filtros)
            ->join('users as u', 'u.id', '=', 'b.user_id')
            ->select('b.id', 'b.created_at', 'u.name as usuario', 'b.accion', 'b.tabla', 'b.registro_id', 'b.detalle')
            ->when(! $usuario->esAdministrador(), fn ($q) => $q->where('b.user_id', $usuario->id))
            ->when(isset($filtros['user_id']), fn ($q) => $q->where('b.user_id', $filtros['user_id']))
            ->when(isset($filtros['accion']), fn ($q) => $q->where('b.accion', $filtros['accion']))
            ->orderByDesc('b.id');
    }

    public function totales(array $filtros, User $usuario): array
    {
        $t = $this->agregar($filtros, $usuario, 'COUNT(*) AS registros, COUNT(DISTINCT usuario) AS usuarios, COUNT(DISTINCT accion) AS acciones, COUNT(DISTINCT tabla) AS tablas');

        return [
            ['Registros', $t->registros, 'numero'],
            ['Usuarios', $t->usuarios ?? 0, 'numero'],
            ['Acciones distintas', $t->acciones ?? 0, 'numero'],
            ['Tablas afectadas', $t->tablas ?? 0, 'numero'],
        ];
    }

    public function resumen(array $filtros, User $usuario): ?array
    {
        $filas = DB::query()->fromSub($this->consulta($filtros, $usuario)->reorder(), 't')
            ->groupBy('usuario')->orderByDesc('registros')
            ->selectRaw('usuario, COUNT(*) AS registros, COUNT(DISTINCT accion) AS acciones, MAX(created_at) AS ultimo')
            ->get()->map(fn ($f) => (array) $f);

        return [
            'titulo' => 'Por usuario',
            'columnas' => [
                'usuario' => ['Usuario', 'texto'],
                'registros' => ['Registros', 'numero'],
                'acciones' => ['Acciones distintas', 'numero'],
                'ultimo' => ['Último registro', 'fecha'],
            ],
            'filas' => $filas->all(),
        ];
    }

    public function enlaces(Collection $filas, User $usuario): array
    {
        // Las pantallas enlazadas (usuarios, pilotos...) son del administrador.
        return $usuario->esAdministrador() ? Bitacora::enlaces($filas) : [];
    }
}
