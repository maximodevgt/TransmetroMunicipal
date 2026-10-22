{{-- Vista de impresión de un reporte: tamaño carta, sin menú, con todos los registros filtrados. --}}
@php
    $usuario = auth()->user();
    // Texto seguro para los márgenes de la página (content: "..." en CSS).
    $css = fn (string $texto) => '"'.str_replace(['\\', '"', '<', '>', "\n"], ['\\\\', '\\"', '', '', ' '], $texto).'"';
    $pie = 'Emitido el '.$emitido->format('d/m/Y H:i').' por '.$usuario->name;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $reporte->titulo() }} · Reporte · Transmetro Municipal</title>
    @vite('resources/css/app.css')
    <style>
        @page {
            size: letter;
            margin: 14mm 12mm 16mm;

            @top-right {
                content: {!! $css('Transmetro · '.$reporte->titulo()) !!};
                font-size: 8pt;
                color: #64748b;
            }

            @bottom-left {
                content: {!! $css($pie) !!};
                font-size: 8pt;
                color: #64748b;
            }

            @bottom-right {
                content: "Página " counter(page) " de " counter(pages);
                font-size: 8pt;
                color: #64748b;
            }
        }

        @media print {
            .no-imprimir {
                display: none !important;
            }

            body {
                background: #fff;
                font-size: 9pt;
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }

            .hoja {
                box-shadow: none;
                padding: 0;
                max-width: none;
            }

            .tabla thead {
                display: table-header-group;
            }

            .tabla tr {
                break-inside: avoid;
            }

            .tabla thead th,
            .tabla tbody td {
                padding: 4px 6px;
            }
        }
    </style>
</head>
<body class="bg-celeste-50 font-sans text-slate-800 antialiased">
    {{-- Barra de la pantalla (no se imprime) --}}
    <div class="no-imprimir sticky top-0 z-10 flex items-center justify-between gap-3 border-b border-celeste-100 bg-white px-6 py-3 shadow-sm">
        <p class="text-sm text-slate-600">Vista de impresión · tamaño carta</p>
        <div class="flex gap-2">
            <a href="{{ route('reportes.show', [$reporte->clave(), ...$parametros]) }}" class="btn-secundario"><x-icono nombre="atras" class="size-4" /> Volver al reporte</a>
            <button type="button" onclick="window.print()" class="btn-primario"><x-icono nombre="imprimir" class="size-4" /> Imprimir</button>
        </div>
    </div>

    <main class="hoja mx-auto my-6 max-w-5xl bg-white p-8 shadow-sm print:my-0">
        <header class="flex items-start gap-4 border-b-2 border-celeste-500 pb-4" data-encabezado-impresion>
            <div class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-celeste-500 text-white">
                <x-icono nombre="buses" class="size-8" />
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold tracking-wide text-celeste-700 uppercase">Municipalidad de Guatemala – Transmetro</p>
                <h1 class="text-2xl font-bold text-celeste-900">{{ $reporte->titulo() }}</h1>
                <p class="mt-1 text-sm text-slate-600"><span class="font-medium">Filtros:</span> {{ $descripcionFiltros }}</p>
            </div>
            <div class="shrink-0 text-right text-xs text-slate-500">
                <p>Emitido el {{ $emitido->format('d/m/Y H:i') }}</p>
                <p>por {{ $usuario->name }}</p>
                <p>{{ ucfirst($usuario->rol) }}{{ $usuario->estacion ? ' · '.$usuario->estacion->nombre : '' }}</p>
            </div>
        </header>

        <dl class="my-4 grid grid-cols-3 gap-2 sm:grid-cols-6">
            @foreach ($totales as [$etiqueta, $valor, $tipo])
                <div class="rounded-lg border border-celeste-100 px-3 py-2">
                    <dt class="text-[10px] font-medium text-slate-500 uppercase">{{ $etiqueta }}</dt>
                    <dd class="font-bold text-celeste-900 tabular-nums">{{ \App\Reportes\Reporte::formatear($valor, $tipo) }}</dd>
                </div>
            @endforeach
        </dl>

        @include('reportes._tabla', ['filas' => $filas, 'enlaces' => []])

        @if ($resumen)
            <h2 class="mt-6 mb-2 font-semibold text-celeste-900">{{ $resumen['titulo'] }}</h2>
            @include('reportes._tabla', ['columnas' => $resumen['columnas'], 'filas' => $resumen['filas'], 'enlaces' => []])
        @endif
    </main>
</body>
</html>
