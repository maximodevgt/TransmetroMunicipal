<x-layouts.app titulo="Tarjetas Transmetro" subtitulo="Emisión, saldo, recargas y bloqueos (pagos simulados)">
    <x-slot:acciones>
        <a href="{{ route('torniquete.index') }}" class="btn-secundario"><x-icono nombre="torniquete" class="size-4" /> Cobro de pasaje</a>
        @if (auth()->user()->esAdministrador())
            <a href="{{ route('tarjetas.create') }}" class="btn-primario"><x-icono nombre="mas" class="size-4" /> Emitir tarjeta</a>
        @endif
    </x-slot:acciones>

    <section class="tarjeta overflow-hidden">
        <form method="GET" class="flex flex-wrap items-end gap-3 p-4">
            <div class="min-w-56 flex-1 sm:max-w-xs">
                <label for="buscar" class="etiqueta">Buscar</label>
                <input id="buscar" type="search" name="buscar" value="{{ $filtros['buscar'] ?? '' }}" placeholder="Código o pasajero" class="campo">
            </div>
            <div>
                <label for="filtro_estado" class="etiqueta">Estado</label>
                <select id="filtro_estado" name="estado" class="campo">
                    <option value="">Todos</option>
                    @foreach (\App\Models\Tarjeta::ESTADOS as $valor => $texto)
                        <option value="{{ $valor }}" @selected(($filtros['estado'] ?? '') === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn-secundario">Filtrar</button>
            @if (array_filter($filtros))
                <a href="{{ route('tarjetas.index') }}" class="btn text-slate-500 hover:text-celeste-600">Limpiar</a>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="tabla">
                <thead><tr><th>Código</th><th>Pasajero</th><th class="text-right">Saldo</th><th>Estado</th><th>Emisión</th></tr></thead>
                <tbody>
                    @forelse ($tarjetas as $tarjeta)
                        <tr class="relative cursor-pointer transition hover:bg-celeste-50">
                            <td class="whitespace-nowrap"><a href="{{ route('tarjetas.show', $tarjeta) }}" class="enlace-extendido font-semibold text-celeste-700 tabular-nums">{{ $tarjeta->codigo }}</a></td>
                            <td>{{ $tarjeta->pasajero->nombre }}</td>
                            <td class="text-right"><x-saldo :valor="$tarjeta->saldo" /></td>
                            <td><x-estado :valor="$tarjeta->estado" /></td>
                            <td class="whitespace-nowrap text-slate-500">{{ $tarjeta->fecha_emision?->format('d/m/Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-slate-500">No se encontraron tarjetas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $tarjetas->links() }}</div>
    </section>
</x-layouts.app>
