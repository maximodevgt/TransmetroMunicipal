<?php

namespace App\Http\Controllers;

use App\Models\Acceso;
use App\Models\Bitacora;
use App\Models\Guardia;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Guardias de seguridad de cada acceso. RN-11: todo acceso debe tener al menos un guardia.
 */
class GuardiaController extends Controller
{
    public function create(Acceso $acceso): View
    {
        return view('guardias.form', ['acceso' => $acceso->load('estacion'), 'guardia' => new Guardia(['turno' => 'matutino'])]);
    }

    public function store(Request $request, Acceso $acceso): RedirectResponse
    {
        try {
            $guardia = $acceso->guardias()->create(self::validar($request));
        } catch (QueryException $e) {
            return $this->volverConErrorBd($e);
        }
        Bitacora::registrar('crear', $guardia, "Guardia asignado al {$acceso->nombre} de la estación {$acceso->estacion->nombre}: {$guardia->nombre}");

        return redirect()->route('estaciones.show', $acceso->estacion_id)->with('ok', "Guardia agregado al {$acceso->nombre}: {$guardia->nombre}.");
    }

    public function edit(Guardia $guardia): View
    {
        return view('guardias.form', ['acceso' => $guardia->acceso->load('estacion'), 'guardia' => $guardia]);
    }

    public function update(Request $request, Guardia $guardia): RedirectResponse
    {
        try {
            $guardia->update(self::validar($request, $guardia));
        } catch (QueryException $e) {
            return $this->volverConErrorBd($e);
        }
        Bitacora::registrar('editar', $guardia, "Guardia actualizado: {$guardia->nombre}");

        return redirect()->route('estaciones.show', $guardia->acceso->estacion_id)->with('ok', "Guardia actualizado: {$guardia->nombre}.");
    }

    public function destroy(Guardia $guardia): RedirectResponse
    {
        $acceso = $guardia->acceso;

        // Se bloquean los guardias del acceso para que dos eliminaciones simultáneas no lo dejen vacío.
        $eliminado = DB::transaction(function () use ($guardia, $acceso) {
            if ($acceso->guardias()->lockForUpdate()->count() <= 1) {
                return false;
            }
            $guardia->delete();

            return true;
        });

        if (! $eliminado) {
            return back()->with('error', "No se puede eliminar a {$guardia->nombre}: es el último guardia del acceso {$acceso->nombre} y todo acceso debe tener al menos uno (RN-11).");
        }
        Bitacora::registrar('eliminar', $guardia, "Guardia retirado del {$acceso->nombre}: {$guardia->nombre}");

        return redirect()->route('estaciones.show', $acceso->estacion_id)->with('ok', "Guardia eliminado: {$guardia->nombre}.");
    }

    private static function validar(Request $request, ?Guardia $guardia = null): array
    {
        return $request->validate(...self::reglas($guardia));
    }

    /**
     * Reglas, mensajes y nombres de campo del guardia. Con $prefijo sirven para el primer guardia
     * del formulario de acceso (campos guardia_nombre, guardia_dpi, ...).
     */
    public static function reglas(?Guardia $guardia = null, string $prefijo = ''): array
    {
        return [
            [
                "{$prefijo}nombre" => ['required', 'string', 'max:120'],
                "{$prefijo}dpi" => ['required', 'digits:13', Rule::unique('guardias', 'dpi')->ignore($guardia)],
                "{$prefijo}telefono" => ['nullable', 'digits_between:8,15'],
                "{$prefijo}turno" => ['required', Rule::in(array_keys(Guardia::TURNOS))],
            ],
            [
                "{$prefijo}dpi.digits" => 'El DPI debe tener exactamente 13 dígitos.',
                "{$prefijo}dpi.unique" => 'Ya existe un guardia con ese DPI.',
            ],
            [
                "{$prefijo}nombre" => 'nombre del guardia',
                "{$prefijo}dpi" => 'DPI',
                "{$prefijo}telefono" => 'teléfono',
                "{$prefijo}turno" => 'turno',
            ],
        ];
    }
}
