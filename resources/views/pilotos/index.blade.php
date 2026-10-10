<x-layouts.app titulo="Pilotos" subtitulo="Conductores del Transmetro y su historial educativo">
    <x-slot:acciones>
        <a href="{{ route('pilotos.create') }}" class="btn-primario"><x-icono nombre="mas" class="size-4" /> Nuevo piloto</a>
    </x-slot:acciones>

    <section class="tarjeta overflow-hidden">
        <form method="GET" class="flex flex-wrap gap-2 p-4">
            <input type="search" name="buscar" value="{{ $buscar }}" placeholder="Nombre, DPI o licencia…" class="campo max-w-xs">
            <button class="btn-secundario">Buscar</button>
            @if ($buscar !== '')
                <a href="{{ route('pilotos.index') }}" class="btn text-slate-500 hover:text-celeste-600">Limpiar</a>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="tabla">
                <thead><tr><th>Piloto</th><th>DPI</th><th>Licencia</th><th>Teléfono</th><th class="text-right">Estudios</th><th>Estado</th></tr></thead>
                <tbody>
                    @forelse ($pilotos as $piloto)
                        <tr>
                            <td>
                                <a href="{{ route('pilotos.show', $piloto) }}" class="font-medium text-celeste-700 hover:underline">{{ $piloto->nombre }}</a>
                                <span class="block text-xs text-slate-500">{{ $piloto->residencia }}</span>
                            </td>
                            <td class="font-mono text-xs">{{ $piloto->dpi }}</td>
                            <td>{{ $piloto->licencia }}</td>
                            <td>{{ $piloto->telefono }}</td>
                            <td class="text-right">{{ $piloto->historial_educativo_count }}</td>
                            <td><x-estado :valor="$piloto->estado" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-slate-500">No se encontraron pilotos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $pilotos->links() }}</div>
    </section>
</x-layouts.app>
