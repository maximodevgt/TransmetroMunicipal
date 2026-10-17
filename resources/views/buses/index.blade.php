<x-layouts.app titulo="Buses" subtitulo="Flota del Transmetro y su asignación a líneas">
    @if (auth()->user()->esAdministrador())
        <x-slot:acciones>
            <a href="{{ route('buses.create') }}" class="btn-primario"><x-icono nombre="mas" class="size-4" /> Nuevo bus</a>
        </x-slot:acciones>
    @endif

    @if ($alerta)
        <x-resolviendo :alerta="$alerta">
            La línea {{ $alerta->linea->codigo }} necesita {{ $faltan === 1 ? '1 bus más' : $faltan.' buses más' }}.
            Asigne uno de los buses sin línea con el botón «Asignar a {{ $alerta->linea->codigo }}».
        </x-resolviendo>
    @endif

    <section class="tarjeta overflow-hidden">
        <form method="GET" class="flex flex-wrap items-end gap-3 p-4">
            @if ($alerta)
                <input type="hidden" name="alerta" value="{{ $alerta->id }}">
            @endif
            <div>
                <label class="etiqueta" for="f_linea">Línea</label>
                <select id="f_linea" name="linea_id" class="campo">
                    <option value="">Todas</option>
                    @foreach ($lineas as $linea)
                        <option value="{{ $linea->id }}" @selected(($filtros['linea_id'] ?? '') == $linea->id)>{{ $linea->codigo }}</option>
                    @endforeach
                    <option value="sin" @selected(($filtros['linea_id'] ?? '') === 'sin')>Sin línea</option>
                </select>
            </div>
            <div>
                <label class="etiqueta" for="f_estado">Estado</label>
                <select id="f_estado" name="estado" class="campo">
                    <option value="">Todos</option>
                    @foreach (\App\Models\Bus::ESTADOS as $estado)
                        <option value="{{ $estado }}" @selected(($filtros['estado'] ?? '') === $estado)>{{ ucfirst(str_replace('_', ' ', $estado)) }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn-secundario">Filtrar</button>
            @if (array_filter($filtros))
                <a href="{{ route('buses.index') }}" class="btn text-slate-500 hover:text-celeste-600">Limpiar</a>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Código</th><th>Placa</th><th>Línea</th><th>Parqueo</th><th class="text-right">Capacidad</th><th>Estado</th>
                        @if ($alerta)<th><span class="sr-only">Asignar</span></th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($buses as $bus)
                        <tr>
                            <td class="whitespace-nowrap"><a href="{{ route('buses.show', $bus) }}" class="font-medium text-celeste-700 hover:underline">{{ $bus->codigo }}</a></td>
                            <td class="whitespace-nowrap">{{ $bus->placa }}</td>
                            <td>
                                @if ($bus->linea)
                                    <span class="insignia bg-celeste-100 text-celeste-700">{{ $bus->linea->codigo }}</span>
                                @else
                                    <span class="text-xs text-slate-400">Sin línea</span>
                                @endif
                            </td>
                            <td>{{ $bus->parqueo->nombre }}</td>
                            <td class="text-right">{{ $bus->capacidad_maxima }}</td>
                            <td><x-estado :valor="$bus->estado" /></td>
                            @if ($alerta)
                                <td class="text-right">
                                    @if ($bus->linea_id === null)
                                        <form method="POST" action="{{ route('buses.asignar-linea', $bus) }}">
                                            @csrf
                                            <input type="hidden" name="alerta" value="{{ $alerta->id }}">
                                            <button class="btn-primario px-3 py-1.5 text-xs whitespace-nowrap">Asignar a {{ $alerta->linea->codigo }}</button>
                                        </form>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-slate-500">No se encontraron buses.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $buses->links() }}</div>
    </section>
</x-layouts.app>
