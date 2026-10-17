<x-layouts.app titulo="Bitácora" subtitulo="Cada cambio registrado en el sistema">
    <section class="tarjeta overflow-hidden">
        <form method="GET" class="flex flex-wrap items-end gap-3 p-4">
            <div>
                <label for="filtro_usuario" class="etiqueta">Usuario</label>
                <select id="filtro_usuario" name="user_id" class="campo">
                    <option value="">Todos</option>
                    @foreach ($usuarios as $usuario)
                        <option value="{{ $usuario->id }}" @selected(($filtros['user_id'] ?? '') == $usuario->id)>{{ $usuario->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filtro_tabla" class="etiqueta">Tabla</label>
                <select id="filtro_tabla" name="tabla" class="campo">
                    <option value="">Todas</option>
                    @foreach ($tablas as $tabla)
                        <option value="{{ $tabla }}" @selected(($filtros['tabla'] ?? '') === $tabla)>{{ str_replace('_', ' ', $tabla) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filtro_accion" class="etiqueta">Acción</label>
                <select id="filtro_accion" name="accion" class="campo">
                    <option value="">Todas</option>
                    @foreach ($acciones as $accion)
                        <option value="{{ $accion }}" @selected(($filtros['accion'] ?? '') === $accion)>{{ str_replace('_', ' ', $accion) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filtro_fecha" class="etiqueta">Fecha</label>
                <input id="filtro_fecha" type="date" name="fecha" value="{{ $filtros['fecha'] ?? '' }}" class="campo">
            </div>
            <button class="btn-secundario">Filtrar</button>
            @if (array_filter($filtros))
                <a href="{{ route('bitacora.index') }}" class="btn text-slate-500 hover:text-celeste-600">Limpiar</a>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="tabla">
                <thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Detalle</th></tr></thead>
                <tbody>
                    @forelse ($registros as $b)
                        @php($url = $enlaces[$b->id] ?? null)
                        <tr @class(['relative', 'cursor-pointer transition hover:bg-celeste-50' => $url])>
                            <td class="whitespace-nowrap text-slate-500">{{ $b->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="whitespace-nowrap">{{ $b->user?->name }}</td>
                            <td><span class="insignia bg-celeste-50 text-celeste-700">{{ str_replace('_', ' ', $b->accion) }}</span>
                                <span class="block text-xs text-slate-400">{{ str_replace('_', ' ', $b->tabla) }} #{{ $b->registro_id }}</span></td>
                            <td>
                                @if ($url)
                                    <a href="{{ $url }}" class="enlace-extendido text-celeste-700">{{ $b->detalle }}</a>
                                @else
                                    {{ $b->detalle }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-slate-500">No hay registros con esos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $registros->links() }}</div>
    </section>
</x-layouts.app>
