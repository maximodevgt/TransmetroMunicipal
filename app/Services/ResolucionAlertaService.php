<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Alerta;
use App\Models\Recorrido;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * "Resolver" una alerta: lleva a la pantalla donde se soluciona (con ?alerta=ID) y, al guardar
 * la acción en esa pantalla, valida que corresponda a la alerta y la marca atendida.
 */
class ResolucionAlertaService
{
    public function __construct(private AlertaService $alertas) {}

    /**
     * Acción que ofrece el botón Resolver para este usuario, o null si la alerta ya está atendida.
     *
     * @return array{url: string, texto: string}|null
     */
    public function accion(Alerta $alerta, User $usuario): ?array
    {
        if (! $alerta->activa() || ! $alerta->puedeVerla($usuario)) {
            return null;
        }

        $detalle = ['url' => route('alertas.show', ['alerta' => $alerta, 'resolver' => 1]), 'texto' => 'Resolver'];

        return match ($alerta->tipo) {
            // Iniciar recorridos es del administrador; el operador pide la unidad.
            'alta_demanda' => $usuario->esAdministrador()
                ? ['url' => route('recorridos.create', ['linea_id' => $alerta->linea_id, 'alerta' => $alerta->id]), 'texto' => 'Resolver']
                : [...$detalle, 'texto' => 'Solicitar unidad'],
            'baja_ocupacion' => ($recorrido = $this->recorridoDe($alerta))
                ? ['url' => route('recorridos.show', ['recorrido' => $recorrido, 'alerta' => $alerta->id]).'#visita-'.$alerta->estacion_id, 'texto' => 'Resolver']
                : $detalle,
            'insuficiencia_unidades' => $usuario->esAdministrador()
                ? ['url' => route('buses.index', ['linea_id' => 'sin', 'alerta' => $alerta->id]), 'texto' => 'Resolver']
                : $detalle,
            default => $detalle,
        };
    }

    /**
     * Alerta que se está resolviendo en una pantalla destino (?alerta=ID), solo si es visible para el usuario,
     * sigue activa y es del tipo que esa pantalla resuelve.
     */
    public function enCurso(Request $request, string ...$tipos): ?Alerta
    {
        $id = $request->input('alerta');
        if (! ctype_digit((string) $id)) {
            return null;
        }
        $alerta = Alerta::with(['estacion', 'bus', 'linea'])->find($id);

        return $alerta && $alerta->activa() && $alerta->puedeVerla($request->user()) && in_array($alerta->tipo, $tipos, true)
            ? $alerta
            : null;
    }

    /**
     * Igual que enCurso(), pero al guardar: si se envió una alerta que no corresponde, la acción se rechaza.
     */
    public function exigir(Request $request, string ...$tipos): ?Alerta
    {
        if (! $request->filled('alerta')) {
            return null;
        }

        return $this->enCurso($request, ...$tipos)
            ?? throw new ReglaNegocioException('La alerta indicada no existe, ya fue atendida o no corresponde a esta acción.');
    }

    /**
     * Recorrido donde ocurrió la baja ocupación: el más reciente del bus que pasó por la estación de la alerta.
     */
    public function recorridoDe(Alerta $alerta): ?Recorrido
    {
        return Recorrido::where('bus_id', $alerta->bus_id)
            ->whereHas('visitas', fn ($q) => $q->where('estacion_id', $alerta->estacion_id))
            ->latest('id')
            ->first();
    }

    /**
     * Cierra la alerta con lo que se hizo. Al resolver una alta demanda también se cierran
     * las solicitudes de unidad activas de esa estación.
     */
    public function resolver(Alerta $alerta, string $queSeHizo): void
    {
        $this->alertas->atender($alerta, $queSeHizo);

        if ($alerta->tipo === 'alta_demanda') {
            Alerta::where('estacion_id', $alerta->estacion_id)->where('tipo', 'asignacion')->where('estado', 'activa')->get()
                ->each(fn (Alerta $solicitud) => $this->alertas->atender($solicitud, "{$queSeHizo} (alerta #{$alerta->id})"));
        }
    }
}
