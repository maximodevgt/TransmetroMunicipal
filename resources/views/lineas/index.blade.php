<x-layouts.app titulo="Líneas" subtitulo="Rutas del Transmetro y su cobertura de buses">
    @if (auth()->user()->esAdministrador())
        <x-slot:acciones>
            <a href="{{ route('lineas.create') }}" class="btn-primario"><x-icono nombre="mas" class="size-4" /> Nueva línea</a>
        </x-slot:acciones>
    @endif

    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($lineas as $linea)
            @php
                $min = $linea->estaciones_count;
                $max = $linea->estaciones_count * 2;
            @endphp
            <a href="{{ route('lineas.show', $linea) }}" class="tarjeta group block p-5 transition hover:-translate-y-0.5 hover:shadow-md hover:ring-celeste-300">
                <div class="flex items-start justify-between">
                    <span class="rounded-xl bg-gradient-to-br from-celeste-400 to-celeste-600 px-3 py-1.5 text-lg font-bold text-white shadow-sm">{{ $linea->codigo }}</span>
                    <x-estado :valor="$linea->estado" />
                </div>
                <h3 class="mt-4 font-semibold text-celeste-900 group-hover:text-celeste-600">{{ $linea->nombre }}</h3>
                <p class="text-sm text-slate-500">{{ $linea->municipalidad->nombre }}</p>

                <dl class="mt-4 grid grid-cols-3 gap-2 rounded-xl bg-celeste-50 p-3 text-center">
                    <div>
                        <dt class="text-xs text-slate-500">Estaciones</dt>
                        <dd class="font-bold text-celeste-800">{{ $linea->estaciones_count }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Buses</dt>
                        <dd @class(['font-bold', 'text-celeste-800' => $linea->buses_count >= $min, 'text-amber-600' => $linea->buses_count < $min])>{{ $linea->buses_count }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Rango</dt>
                        <dd class="font-bold text-celeste-800">{{ $min }}–{{ $max }}</dd>
                    </div>
                </dl>
            </a>
        @empty
            <p class="text-slate-500">No hay líneas registradas.</p>
        @endforelse
    </div>
</x-layouts.app>
