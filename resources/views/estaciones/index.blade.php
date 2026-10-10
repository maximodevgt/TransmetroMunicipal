<x-layouts.app titulo="Estaciones" subtitulo="Paradas del Transmetro, sus líneas y accesos">
    @if (auth()->user()->esAdministrador())
        <x-slot:acciones>
            <a href="{{ route('estaciones.create') }}" class="btn-primario"><x-icono nombre="mas" class="size-4" /> Nueva estación</a>
        </x-slot:acciones>
    @endif

    <section class="tarjeta overflow-hidden">
        <form method="GET" class="flex flex-wrap gap-2 p-4">
            <input type="search" name="buscar" value="{{ $buscar }}" placeholder="Buscar estación…" class="campo max-w-xs">
            <button class="btn-secundario">Buscar</button>
            @if ($buscar !== '')
                <a href="{{ route('estaciones.index') }}" class="btn text-slate-500 hover:text-celeste-600">Limpiar</a>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Estación</th>
                        <th>Líneas</th>
                        <th>Municipalidad</th>
                        <th class="text-right">Accesos</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($estaciones as $estacion)
                        <tr>
                            <td class="text-slate-400">{{ $estacion->id }}</td>
                            <td>
                                <a href="{{ route('estaciones.show', $estacion) }}" class="font-medium text-celeste-700 hover:underline">{{ $estacion->nombre }}</a>
                                <span class="block text-xs text-slate-500">{{ $estacion->direccion }}</span>
                            </td>
                            <td>
                                @forelse ($estacion->lineas as $linea)
                                    <span class="insignia bg-celeste-100 text-celeste-700">{{ $linea->codigo }}</span>
                                @empty
                                    <span class="text-xs text-slate-400">Sin línea</span>
                                @endforelse
                            </td>
                            <td>{{ $estacion->municipalidad->nombre }}</td>
                            <td class="text-right">{{ $estacion->accesos_count }}</td>
                            <td><x-estado :valor="$estacion->estado" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-slate-500">No se encontraron estaciones.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $estaciones->links() }}</div>
    </section>
</x-layouts.app>
