<x-layouts.app :titulo="'Reporte: '.$reporte->titulo()" :subtitulo="$reporte->descripcion()">
    <x-slot:acciones>
        <a href="{{ route('reportes.index') }}" class="btn-secundario"><x-icono nombre="atras" class="size-4" /> Reportes</a>
        <a href="{{ route('reportes.imprimir', [$reporte->clave(), ...$parametros]) }}" target="_blank" rel="noopener" class="btn-secundario" data-imprimir-reporte>
            <x-icono nombre="imprimir" class="size-4" /> Imprimir
        </a>
        <a href="{{ route('reportes.csv', [$reporte->clave(), ...$parametros]) }}" class="btn-primario" data-exportar-csv>
            <x-icono nombre="descargar" class="size-4" /> Exportar CSV
        </a>
    </x-slot:acciones>

    {{-- Filtros: quedan en la URL --}}
    <form method="GET" action="{{ route('reportes.show', $reporte->clave()) }}" class="tarjeta mb-4 p-4" data-filtros-reporte>
        @if ($reporte->usaFechas())
            <div class="mb-3 flex flex-wrap items-center gap-2" role="group" aria-label="Atajos de fecha">
                <span class="text-sm font-medium text-slate-700">Fechas:</span>
                @foreach (\App\Reportes\Reporte::PERIODOS as $periodo => $texto)
                    @php($activo = $filtros['periodo'] === $periodo)
                    <a href="{{ route('reportes.show', [$reporte->clave(), ...collect($parametros)->except(['desde', 'hasta', 'periodo'])->all(), 'periodo' => $periodo]) }}"
                       @class(['rounded-full px-3 py-1 text-sm font-medium transition', 'bg-celeste-500 text-white shadow-sm' => $activo, 'bg-celeste-50 text-celeste-700 hover:bg-celeste-100' => ! $activo])
                       @if ($activo) aria-current="true" @endif data-atajo="{{ $periodo }}">{{ $texto }}</a>
                @endforeach
            </div>
        @endif
        <div class="flex flex-wrap items-end gap-3">
            @if ($reporte->usaFechas())
                <div>
                    <label for="f_desde" class="etiqueta">Desde</label>
                    <input id="f_desde" type="date" name="desde" value="{{ $filtros['desde']->format('Y-m-d') }}" class="campo">
                </div>
                <div>
                    <label for="f_hasta" class="etiqueta">Hasta</label>
                    <input id="f_hasta" type="date" name="hasta" value="{{ $filtros['hasta']->format('Y-m-d') }}" class="campo">
                </div>
            @endif
            @foreach ($campos as $nombre => $campo)
                <div>
                    <label for="f_{{ $nombre }}" class="etiqueta">{{ $campo['etiqueta'] }}</label>
                    <select id="f_{{ $nombre }}" name="{{ $nombre }}" class="campo">
                        @isset($campo['vacio'])
                            <option value="">{{ $campo['vacio'] }}</option>
                        @endisset
                        @foreach ($campo['opciones'] as $valor => $texto)
                            <option value="{{ $valor }}" @selected((string) ($filtros[$nombre] ?? '') === (string) $valor)>{{ $texto }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
            <button class="btn-secundario">Filtrar</button>
            <a href="{{ route('reportes.show', $reporte->clave()) }}" class="px-2 py-2 text-sm font-medium text-celeste-600 hover:underline">Quitar filtros</a>
        </div>
    </form>

    {{-- Totales de todos los registros filtrados (no solo de esta página) --}}
    <div @class(['mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3', match (count($totales)) { 4 => 'lg:grid-cols-4', 5 => 'lg:grid-cols-5', default => 'lg:grid-cols-6' }])>
        @foreach ($totales as [$etiqueta, $valor, $tipo])
            <div class="tarjeta p-4" data-total="{{ $etiqueta }}">
                <p class="text-xs font-medium text-slate-500">{{ $etiqueta }}</p>
                <p class="mt-1 text-xl font-bold text-celeste-900 tabular-nums">{{ \App\Reportes\Reporte::formatear($valor, $tipo) }}</p>
            </div>
        @endforeach
    </div>

    <section class="tarjeta overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-2 px-5 py-3 text-sm text-slate-500">
            <span>{{ $descripcionFiltros }}</span>
            <span>{{ number_format($filas->total()) }} {{ $filas->total() === 1 ? 'registro' : 'registros' }}</span>
        </div>
        <div class="overflow-x-auto">
            @include('reportes._tabla', ['filas' => $filas, 'enlaces' => $enlaces])
        </div>
        @if ($filas->hasPages())
            <div class="p-4">{{ $filas->links() }}</div>
        @endif
    </section>

    @if ($resumen)
        <section class="tarjeta mt-6 overflow-hidden" data-resumen>
            <h2 class="px-5 py-4 font-semibold text-celeste-900">{{ $resumen['titulo'] }}</h2>
            <div class="overflow-x-auto">
                @include('reportes._tabla', ['columnas' => $resumen['columnas'], 'filas' => $resumen['filas'], 'enlaces' => []])
            </div>
        </section>
    @endif
</x-layouts.app>
