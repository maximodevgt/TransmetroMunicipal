<x-layouts.app :titulo="$parqueo->nombre" :subtitulo="$parqueo->direccion">
    <x-slot:acciones>
        <a href="{{ route('parqueos.index') }}" class="btn-secundario"><x-icono nombre="atras" class="size-4" /> Parqueos</a>
        @if (auth()->user()->esAdministrador())
            <a href="{{ route('parqueos.edit', $parqueo) }}" class="btn-secundario"><x-icono nombre="editar" class="size-4" /> Editar</a>
            <x-eliminar :accion="route('parqueos.destroy', $parqueo)" titulo="¿Eliminar el parqueo {{ $parqueo->nombre }}?"
                        confirmar="Solo se puede eliminar si no tiene buses. Esta acción no se puede deshacer." />
        @endif
    </x-slot:acciones>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="tarjeta space-y-4 p-5">
            <div>
                <p class="text-xs text-slate-500">Capacidad</p>
                <p class="text-xl font-bold text-celeste-900">{{ $parqueo->capacidad }} buses</p>
            </div>
            <div>
                <p class="text-xs text-slate-500">Ocupación</p>
                <x-ocupacion :usados="$parqueo->buses_count" :capacidad="$parqueo->capacidad" class="mt-1" />
            </div>
            <div>
                <p class="text-xs text-slate-500">Espacios libres</p>
                <p class="font-medium">{{ max(0, $parqueo->capacidad - $parqueo->buses_count) }}</p>
            </div>
        </section>

        <section class="tarjeta overflow-hidden lg:col-span-2">
            <h2 class="px-5 py-4 font-semibold text-celeste-900">Buses en este parqueo</h2>
            <div class="overflow-x-auto">
                <table class="tabla">
                    <thead><tr><th>Bus</th><th>Placa</th><th>Línea</th><th>Estado</th></tr></thead>
                    <tbody>
                        @forelse ($parqueo->buses as $bus)
                            <tr>
                                <td><a href="{{ route('buses.show', $bus) }}" class="font-medium text-celeste-700 hover:underline">{{ $bus->codigo }}</a></td>
                                <td>{{ $bus->placa }}</td>
                                <td>{{ $bus->linea?->codigo ?? 'Sin línea' }}</td>
                                <td><x-estado :valor="$bus->estado" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-slate-500">El parqueo no tiene buses.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.app>
