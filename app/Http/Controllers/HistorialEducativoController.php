<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\HistorialEducativo;
use App\Models\Piloto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HistorialEducativoController extends Controller
{
    public function store(Request $request, Piloto $piloto): RedirectResponse
    {
        $datos = $request->validate([
            'nivel' => ['required', Rule::in(HistorialEducativo::NIVELES)],
            'institucion' => ['required', 'string', 'max:150'],
            'titulo' => ['nullable', 'string', 'max:150'],
            'anio' => ['nullable', 'integer', 'min:1901', 'max:'.now()->year],
        ]);

        $piloto->historialEducativo()->create($datos);
        Bitacora::registrar('agregar_historial', $piloto, "Historial {$datos['nivel']} agregado a {$piloto->nombre}");

        return back()->with('ok', 'Estudio agregado al historial.');
    }

    public function destroy(Piloto $piloto, HistorialEducativo $historial): RedirectResponse
    {
        abort_unless((int) $historial->piloto_id === (int) $piloto->id, 404);

        $historial->delete();
        Bitacora::registrar('quitar_historial', $piloto, "Historial {$historial->nivel} retirado de {$piloto->nombre}");

        return back()->with('ok', 'Estudio retirado del historial.');
    }
}
