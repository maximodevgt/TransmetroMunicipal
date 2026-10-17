@php($esAdmin = auth()->user()->esAdministrador())

@push('cabecera')
    @vite('resources/js/seguimiento.js')
@endpush

<x-layouts.app titulo="Seguimiento en vivo" subtitulo="Buses en ruta, estaciones y alertas, actualizado cada 3 segundos">
    <x-slot:acciones>
        <span data-estado-simulacion class="insignia bg-slate-100 text-slate-600" aria-live="polite">Simulación detenida</span>
        @if ($esAdmin)
            <label class="flex items-center gap-2 text-sm text-slate-600">
                Buses
                <input type="number" data-buses-simulacion value="6" min="1" max="20" class="campo w-20 py-1.5" aria-label="Cantidad de buses a simular">
            </label>
            <button type="button" data-boton-simulacion data-accion="iniciar" class="btn-primario">Iniciar simulación</button>
        @endif
    </x-slot:acciones>

    <div class="relative grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div data-mapa-seguimiento="completo"
             data-url-datos="{{ route('seguimiento.datos') }}"
             data-url-avanzar="{{ route('seguimiento.avanzar') }}"
             data-url-simulacion="{{ $esAdmin ? route('seguimiento.simulacion') : '' }}"
             class="isolate h-[calc(100dvh-13rem)] min-h-[420px] overflow-hidden rounded-2xl shadow-sm ring-1 ring-celeste-100"
             role="region" aria-label="Mapa de seguimiento en vivo"></div>

        {{-- Panel: a la derecha en escritorio; en teléfono, hoja que se desliza desde abajo --}}
        <aside data-panel-seguimiento
               class="fixed inset-x-0 bottom-0 z-40 max-h-[70dvh] translate-y-[calc(100%-3.25rem)] overflow-hidden rounded-t-3xl bg-white shadow-2xl ring-1 ring-celeste-100 transition-transform duration-300 data-abierto:translate-y-0 motion-reduce:transition-none lg:static lg:z-auto lg:max-h-none lg:translate-y-0 lg:rounded-2xl lg:shadow-sm xl:flex xl:h-[calc(100dvh-13rem)] xl:min-h-[420px] xl:flex-col">
            <button type="button" data-alternar-panel class="flex w-full items-center justify-between px-5 py-3 lg:hidden" aria-label="Mostrar u ocultar la lista de buses">
                <span class="mx-auto h-1.5 w-12 rounded-full bg-slate-300"></span>
            </button>

            <div class="flex max-h-[calc(70dvh-3.25rem)] flex-col overflow-y-auto lg:max-h-[28rem] xl:max-h-none xl:flex-1">
                <div class="flex items-center justify-between gap-2 px-5 pt-1 pb-3 lg:pt-5">
                    <h2 class="font-semibold text-celeste-900">Buses en ruta <span data-contador-buses class="text-sm font-normal text-slate-500">0</span></h2>
                    <label class="sr-only" for="filtro_linea_mapa">Filtrar por línea</label>
                    <select id="filtro_linea_mapa" data-filtro-linea class="campo w-auto py-1.5 text-xs">
                        <option value="">Todas las líneas</option>
                        @foreach (\App\Models\Linea::orderBy('id')->get() as $linea)
                            <option value="{{ $linea->id }}">{{ $linea->codigo }}</option>
                        @endforeach
                    </select>
                </div>
                <ul data-lista-buses class="space-y-1 px-2" aria-live="polite">
                    <li class="px-3 py-4 text-sm text-slate-500">Cargando…</li>
                </ul>

                <h2 class="mt-4 border-t border-celeste-50 px-5 pt-4 font-semibold text-celeste-900">Alertas en vivo</h2>
                <ul data-lista-alertas class="space-y-1 px-4 pb-5"></ul>
            </div>
        </aside>
    </div>
</x-layouts.app>
