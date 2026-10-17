@php($esAdmin = auth()->user()->esAdministrador())

<x-layouts.app titulo="Alertas" :subtitulo="$esAdmin ? 'Alertas de todo el Transmetro' : 'Alertas de su estación'">
    <section class="tarjeta overflow-hidden">
        <form method="GET" class="flex flex-wrap items-end gap-3 p-4">
            <div>
                <label for="filtro_tipo" class="etiqueta">Tipo</label>
                <select id="filtro_tipo" name="tipo" class="campo">
                    <option value="">Todos</option>
                    @foreach (\App\Models\Alerta::TIPOS as $valor => $texto)
                        <option value="{{ $valor }}" @selected(($filtros['tipo'] ?? '') === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filtro_estado" class="etiqueta">Estado</label>
                <select id="filtro_estado" name="estado" class="campo">
                    <option value="">Todos</option>
                    <option value="activa" @selected(($filtros['estado'] ?? '') === 'activa')>Activa</option>
                    <option value="atendida" @selected(($filtros['estado'] ?? '') === 'atendida')>Atendida</option>
                </select>
            </div>
            <div>
                <label for="filtro_linea" class="etiqueta">Línea</label>
                <select id="filtro_linea" name="linea_id" class="campo">
                    <option value="">Todas</option>
                    @foreach ($lineas as $linea)
                        <option value="{{ $linea->id }}" @selected(($filtros['linea_id'] ?? '') == $linea->id)>{{ $linea->codigo }}</option>
                    @endforeach
                </select>
            </div>
            @if ($esAdmin)
                <div>
                    <label for="filtro_estacion" class="etiqueta">Estación</label>
                    <select id="filtro_estacion" name="estacion_id" class="campo">
                        <option value="">Todas</option>
                        @foreach ($estaciones as $estacion)
                            <option value="{{ $estacion->id }}" @selected(($filtros['estacion_id'] ?? '') == $estacion->id)>{{ $estacion->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <button class="btn-secundario">Filtrar</button>
            @if (array_filter($filtros))
                <a href="{{ route('alertas.index') }}" class="btn text-slate-500 hover:text-celeste-600">Limpiar</a>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="tabla">
                <thead><tr><th>Alerta</th><th>Lugar</th><th>Fecha</th><th>Estado</th><th class="text-right"><span class="sr-only">Acciones</span></th></tr></thead>
                <tbody>
                    @forelse ($alertas as $alerta)
                        <tr>
                            <td>
                                <a href="{{ route('alertas.show', $alerta) }}" class="font-medium text-celeste-700 hover:underline">{{ $alerta->tipoTexto() }}</a>
                                <span class="block max-w-md truncate text-xs text-slate-500">{{ $alerta->mensaje }}</span>
                            </td>
                            <td class="text-sm">{{ $alerta->lugar() ?: '—' }}</td>
                            <td class="whitespace-nowrap">{{ $alerta->created_at?->format('d/m/Y H:i') }}</td>
                            <td><x-estado :valor="$alerta->estado" /></td>
                            <td class="text-right">
                                @if ($alerta->activa())
                                    <form method="POST" action="{{ route('alertas.atender', $alerta) }}">
                                        @csrf @method('PATCH')
                                        <button class="btn-secundario px-3 py-1.5 text-xs" aria-label="Atender la alerta {{ $alerta->id }}">Atender</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-slate-500">No hay alertas con esos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $alertas->links() }}</div>
    </section>
</x-layouts.app>
