<x-layouts.app titulo="Recorridos" subtitulo="Viajes de los buses por su línea, estación por estación">
    @if (auth()->user()->esAdministrador())
        <x-slot:acciones>
            <a href="{{ route('recorridos.create') }}" class="btn-primario"><x-icono nombre="mas" class="size-4" /> Iniciar recorrido</a>
        </x-slot:acciones>
    @endif

    <section class="tarjeta overflow-hidden">
        <form method="GET" class="flex flex-wrap items-end gap-3 p-4">
            <div>
                <label for="filtro_linea" class="etiqueta">Línea</label>
                <select id="filtro_linea" name="linea_id" class="campo">
                    <option value="">Todas</option>
                    @foreach ($lineas as $linea)
                        <option value="{{ $linea->id }}" @selected(($filtros['linea_id'] ?? '') == $linea->id)>{{ $linea->codigo }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filtro_estado" class="etiqueta">Estado</label>
                <select id="filtro_estado" name="estado" class="campo">
                    <option value="">Todos</option>
                    @foreach (\App\Models\Recorrido::ESTADOS as $valor => $texto)
                        <option value="{{ $valor }}" @selected(($filtros['estado'] ?? '') === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filtro_fecha" class="etiqueta">Fecha de inicio</label>
                <input id="filtro_fecha" type="date" name="fecha" value="{{ $filtros['fecha'] ?? '' }}" class="campo">
            </div>
            <button class="btn-secundario">Filtrar</button>
            @if (array_filter($filtros))
                <a href="{{ route('recorridos.index') }}" class="btn text-slate-500 hover:text-celeste-600">Limpiar</a>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="tabla">
                <thead>
                    <tr><th>#</th><th>Línea</th><th>Bus</th><th>Piloto</th><th>Inicio</th><th class="hidden xl:table-cell">Fin</th><th class="hidden text-right xl:table-cell">Paradas</th><th>Estado</th></tr>
                </thead>
                <tbody>
                    @forelse ($recorridos as $recorrido)
                        <tr>
                            <td><a href="{{ route('recorridos.show', $recorrido) }}" class="font-medium text-celeste-700 hover:underline">#{{ $recorrido->id }}</a></td>
                            <td>{{ $recorrido->linea->codigo }}</td>
                            <td class="whitespace-nowrap">{{ $recorrido->bus->codigo }}</td>
                            <td>{{ $recorrido->piloto->nombre }}</td>
                            <td class="whitespace-nowrap">{{ $recorrido->inicio->format('d/m/Y H:i') }}</td>
                            <td class="hidden whitespace-nowrap xl:table-cell">{{ $recorrido->fin?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="hidden text-right xl:table-cell">{{ $recorrido->visitas_count }}</td>
                            <td class="whitespace-nowrap"><x-estado :valor="$recorrido->estado" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-slate-500">No hay recorridos con esos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $recorridos->links() }}</div>
    </section>
</x-layouts.app>
