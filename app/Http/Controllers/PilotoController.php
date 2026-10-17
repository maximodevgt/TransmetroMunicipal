<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\HistorialEducativo;
use App\Models\Piloto;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PilotoController extends Controller
{
    public function index(Request $request): View
    {
        $buscar = trim((string) $request->query('buscar'));

        return view('pilotos.index', [
            'buscar' => $buscar,
            'pilotos' => Piloto::withCount('historialEducativo')
                ->when($buscar !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->where('nombre', 'like', "%{$buscar}%")
                    ->orWhere('dpi', 'like', "%{$buscar}%")
                    ->orWhere('licencia', 'like', "%{$buscar}%")))
                ->orderBy('id')
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('pilotos.form', ['piloto' => new Piloto(['estado' => 'activo'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $piloto = Piloto::create($this->validar($request));
        } catch (QueryException $e) {
            return $this->volverConErrorBd($e);
        }
        Bitacora::registrar('crear', $piloto, "Registro del piloto {$piloto->nombre}");

        return redirect()->route('pilotos.show', $piloto)->with('ok', 'Piloto registrado. Agregue su historial educativo.');
    }

    public function show(Piloto $piloto): View
    {
        $piloto->load('historialEducativo');

        return view('pilotos.show', ['piloto' => $piloto, 'niveles' => HistorialEducativo::NIVELES]);
    }

    public function edit(Piloto $piloto): View
    {
        return view('pilotos.form', ['piloto' => $piloto]);
    }

    public function update(Request $request, Piloto $piloto): RedirectResponse
    {
        try {
            $piloto->update($this->validar($request, $piloto));
        } catch (QueryException $e) {
            return $this->volverConErrorBd($e);
        }
        Bitacora::registrar('editar', $piloto, "Actualización del piloto {$piloto->nombre}");

        return redirect()->route('pilotos.show', $piloto)->with('ok', 'Piloto actualizado.');
    }

    public function destroy(Piloto $piloto): RedirectResponse
    {
        try {
            $piloto->delete();
        } catch (QueryException $e) {
            return back()->with('error', $this->mensajeErrorBd($e).' Puede marcarlo como inactivo.');
        }
        Bitacora::registrar('eliminar', $piloto, "Eliminación del piloto {$piloto->nombre}");

        return redirect()->route('pilotos.index')->with('ok', 'Piloto eliminado.');
    }

    private function validar(Request $request, ?Piloto $piloto = null): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'dpi' => ['required', 'digits:13', Rule::unique('pilotos', 'dpi')->ignore($piloto)],
            'licencia' => ['required', 'string', 'max:20', Rule::unique('pilotos', 'licencia')->ignore($piloto)],
            'residencia' => ['required', 'string', 'max:200'],
            'telefono' => ['required', 'string', 'regex:/^[0-9+\-\s]{8,15}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'estado' => ['required', Rule::in(['activo', 'inactivo'])],
        ]);
    }
}
