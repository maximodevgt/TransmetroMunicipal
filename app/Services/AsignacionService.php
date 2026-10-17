<?php

namespace App\Services;

use App\Models\Alerta;
use App\Models\Bitacora;
use App\Models\Estacion;
use App\Models\Linea;

/**
 * Reglas de cantidad de buses por línea: mínimo = estaciones, máximo = estaciones × 2.
 * El máximo al asignar un bus lo valida el trigger de MySQL; aquí se cubren los casos que el trigger no ve.
 */
class AsignacionService
{
    /**
     * Revisar el mínimo después de que un bus deja una línea (reasignado, sin línea o eliminado).
     * La operación ya se guardó: si la línea quedó corta se crea una alerta 'insuficiencia_unidades'
     * (sin duplicar una activa) y se devuelve el aviso para mostrarlo al usuario.
     */
    public function revisarMinimo(?Linea $linea): ?string
    {
        if ($linea === null) {
            return null;
        }

        $estaciones = $linea->estaciones()->count();
        $buses = $linea->buses()->count();
        if ($buses >= $estaciones) {
            return null;
        }

        $faltan = $estaciones - $buses;
        $mensaje = "La línea {$linea->codigo} quedó con {$buses} ".($buses === 1 ? 'bus' : 'buses')
            ." y necesita al menos {$estaciones} (uno por estación). Faltan {$faltan}.";

        $alerta = Alerta::updateOrCreate(
            ['linea_id' => $linea->id, 'tipo' => 'insuficiencia_unidades', 'estado' => 'activa'],
            ['mensaje' => $mensaje],
        );
        if ($alerta->wasRecentlyCreated) {
            Bitacora::registrar('crear', $alerta, "Alerta de insuficiencia de unidades en la línea {$linea->codigo}");
        }

        return $mensaje;
    }

    /**
     * Motivo por el que no se puede quitar la estación de la línea, o null si se puede.
     * Con una estación menos el máximo baja a (estaciones - 1) × 2 y los buses asignados no deben superarlo.
     */
    public function motivoParaNoQuitarEstacion(Linea $linea, Estacion $estacion): ?string
    {
        $restantes = $linea->estaciones()->count() - 1;
        $maximo = $restantes * 2;
        $buses = $linea->buses()->count();

        if ($buses <= $maximo) {
            return null;
        }

        return "No se puede quitar {$estacion->nombre}: la línea {$linea->codigo} tiene {$buses} buses y con {$restantes} "
            .($restantes === 1 ? 'estación' : 'estaciones')." el máximo sería {$maximo} (estaciones × 2). Reasigne "
            .($buses - $maximo).' '.($buses - $maximo === 1 ? 'bus' : 'buses').' antes de quitarla.';
    }
}
