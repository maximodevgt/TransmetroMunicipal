<?php

namespace App\Http\Controllers;

use App\Models\Alerta;
use App\Models\Bitacora;
use App\Models\Bus;
use App\Models\Estacion;
use App\Models\Linea;
use App\Models\Piloto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $usuario = $request->user();

        $alertas = Alerta::with(['estacion', 'linea', 'bus'])
            ->activasPara($usuario)
            ->latest()
            ->get();

        return view('dashboard', [
            'totales' => [
                'lineas' => Linea::count(),
                'estaciones' => Estacion::count(),
                'buses' => Bus::count(),
                'pilotos' => Piloto::where('estado', 'activo')->count(),
            ],
            'busesPorEstado' => Bus::select('estado', DB::raw('count(*) as total'))->groupBy('estado')->pluck('total', 'estado'),
            'resumenLineas' => DB::table('vw_resumen_lineas')->orderBy('id')->get(),
            'alertas' => $alertas,
            'estacion' => $usuario->estacion?->load('lineas', 'accesos.guardias'),
            'bitacora' => $usuario->esAdministrador()
                ? Bitacora::with('user')->orderByDesc('id')->limit(6)->get()
                : collect(),
        ]);
    }
}
