<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\User;
use App\Reportes\Catalogo;
use App\Reportes\Reporte;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reportes para ambos roles (el operador ve lo de su estación): pantalla paginada,
 * vista de impresión y CSV con todos los registros filtrados. Generarlos queda en la bitácora.
 */
class ReporteController extends Controller
{
    /**
     * Separador del CSV: punto y coma, el separador de listas de Windows en español (es-GT).
     * Con coma, Excel en español deja cada fila en una sola columna. Los decimales van con punto, como en es-GT.
     */
    public const SEPARADOR_CSV = ';';

    public function index(): View
    {
        return view('reportes.index', ['reportes' => Catalogo::todos()]);
    }

    public function show(Request $request, string $clave): View
    {
        [$reporte, $usuario, $filtros] = $this->abrir($request, $clave);

        $filas = $reporte->consulta($filtros, $usuario)->paginate(Reporte::POR_PAGINA)->withQueryString();
        $enlaces = $reporte->enlaces($filas->getCollection(), $usuario);
        $filas->getCollection()->transform(fn ($fila) => $reporte->preparar($fila));
        $datos = $this->datos($reporte, $filtros, $usuario);

        // Se anota después de calcular, y al paginar no se vuelve a anotar: es el mismo reporte.
        if ($filas->currentPage() === 1) {
            $this->anotar('generar_reporte', 'en pantalla', $reporte, $filtros, $usuario);
        }

        return view('reportes.show', [...$datos, 'filas' => $filas, 'enlaces' => $enlaces]);
    }

    /**
     * Vista de impresión (carta): todos los registros filtrados, sin menú.
     */
    public function imprimir(Request $request, string $clave): Response
    {
        [$reporte, $usuario, $filtros] = $this->abrir($request, $clave);

        $html = view('reportes.imprimir', [
            ...$this->datos($reporte, $filtros, $usuario),
            // cursor(): las filas se leen una a una mientras se dibuja la tabla.
            'filas' => $reporte->consulta($filtros, $usuario)->cursor()->map(fn ($fila) => $reporte->preparar($fila)),
            'generado' => now(),
        ])->render();
        $this->anotar('imprimir_reporte', 'para imprimir', $reporte, $filtros, $usuario);

        return response($html);
    }

    /**
     * CSV en UTF-8 con BOM (Excel respeta las tildes) y separado por punto y coma, escrito por partes de 500 filas.
     */
    public function csv(Request $request, string $clave): StreamedResponse
    {
        [$reporte, $usuario, $filtros] = $this->abrir($request, $clave);
        $columnas = $reporte->columnas($filtros, $usuario);

        return response()->streamDownload(function () use ($reporte, $filtros, $usuario, $columnas) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, array_map(fn ($c) => Reporte::encabezadoCsv($c[0], $c[1]), array_values($columnas)), self::SEPARADOR_CSV, '"', '');

            foreach ($reporte->consulta($filtros, $usuario)->lazy(500) as $fila) {
                $fila = $reporte->preparar($fila);
                fputcsv($salida, array_map(fn ($clave, $c) => Reporte::valorCsv($fila->{$clave} ?? null, $c[1]), array_keys($columnas), $columnas), self::SEPARADOR_CSV, '"', '');
            }
            fclose($salida);
            $this->anotar('exportar_reporte', 'en CSV', $reporte, $filtros, $usuario);
        }, "reporte-{$reporte->clave()}-".now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0: Reporte, 1: User, 2: array} */
    private function abrir(Request $request, string $clave): array
    {
        $reporte = Catalogo::buscar($clave);
        $usuario = $request->user();

        return [$reporte, $usuario, $reporte->filtros($request, $usuario)];
    }

    private function datos(Reporte $reporte, array $filtros, User $usuario): array
    {
        return [
            'reporte' => $reporte,
            'filtros' => $filtros,
            'parametros' => $reporte->parametros($filtros),
            'campos' => $reporte->campos($usuario),
            'columnas' => $reporte->columnas($filtros, $usuario),
            'totales' => $reporte->totales($filtros, $usuario),
            'resumen' => $reporte->resumen($filtros, $usuario),
            'descripcionFiltros' => $reporte->describirFiltros($filtros, $usuario),
        ];
    }

    private function anotar(string $accion, string $formato, Reporte $reporte, array $filtros, User $usuario): void
    {
        Bitacora::anotar($accion, 'reportes', "Reporte {$reporte->titulo()} {$formato} · {$reporte->describirFiltros($filtros, $usuario)}");
    }
}
