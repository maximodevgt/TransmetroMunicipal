{{-- Tabla de un reporte (pantalla e impresión). $enlaces: id de la fila => url; la primera columna lleva el enlace.
     Cada columna es [etiqueta, tipo] y, opcional, una clase de ancho para los textos largos. --}}
@php($numericas = ['numero', 'dinero', 'km', 'porcentaje', 'minutos'])
<table class="tabla" data-tabla-reporte>
    <thead>
        <tr>
            @foreach ($columnas as $columna)
                <th @class(['text-right' => in_array($columna[1], $numericas, true), $columna[2] ?? null])>{{ $columna[0] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $fila)
            @php($fila = (object) $fila)
            @php($url = isset($fila->id) ? ($enlaces[$fila->id] ?? null) : null)
            <tr data-fila @class(['relative cursor-pointer transition hover:bg-celeste-50' => $url])>
                @foreach ($columnas as $clave => [, $tipo])
                    @php($valor = $fila->{$clave} ?? null)
                    <td @class(['text-right tabular-nums whitespace-nowrap' => in_array($tipo, $numericas, true), 'whitespace-nowrap' => in_array($tipo, ['fecha', 'dia'], true)])>
                        @if ($tipo === 'estado' && $valor !== null)
                            <x-estado :valor="$valor" />
                        @elseif ($loop->first && $url)
                            <a href="{{ $url }}" class="enlace-extendido font-medium text-celeste-700">{{ \App\Reportes\Reporte::formatear($valor, $tipo) }}</a>
                        @else
                            {{ \App\Reportes\Reporte::formatear($valor, $tipo) }}
                        @endif
                    </td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($columnas) }}" class="text-slate-500">No hay registros con esos filtros.</td></tr>
        @endforelse
    </tbody>
</table>
