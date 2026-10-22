<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;

abstract class Controller
{
    /**
     * Convierte un error de MySQL (trigger, llave foránea, duplicado o CHECK) en un mensaje para el usuario.
     */
    protected function mensajeErrorBd(QueryException $e): string
    {
        [$sqlState, $codigo, $mensaje] = array_pad($e->errorInfo ?? [], 3, null);

        return match (true) {
            $sqlState === '45000' => $mensaje,
            $codigo === 1451 => 'No se puede eliminar: el registro está siendo usado por otros datos.',
            $codigo === 1452 => 'El registro relacionado no existe.',
            $codigo === 1062 => 'Ya existe un registro con ese valor único.',
            $codigo === 3819 => 'Un valor no cumple las reglas de la base de datos.',
            default => throw $e,
        };
    }

    protected function volverConErrorBd(QueryException $e): RedirectResponse
    {
        return back()->withInput()->with('error', $this->mensajeErrorBd($e));
    }
}
