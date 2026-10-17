<x-layouts.app titulo="Parqueos" subtitulo="Predios donde se guardan los buses y su ocupación">
    @if (auth()->user()->esAdministrador())
        <x-slot:acciones>
            <a href="{{ route('parqueos.create') }}" class="btn-primario"><x-icono nombre="mas" class="size-4" /> Nuevo parqueo</a>
        </x-slot:acciones>
    @endif

    <section class="tarjeta overflow-hidden">
        <div class="overflow-x-auto">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Parqueo</th>
                        <th>Dirección</th>
                        <th class="text-right">Capacidad</th>
                        <th>Ocupación</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($parqueos as $parqueo)
                        <tr>
                            <td><a href="{{ route('parqueos.show', $parqueo) }}" class="font-medium text-celeste-700 hover:underline">{{ $parqueo->nombre }}</a></td>
                            <td class="text-slate-500">{{ $parqueo->direccion ?? '—' }}</td>
                            <td class="text-right">{{ $parqueo->capacidad }}</td>
                            <td><x-ocupacion :usados="$parqueo->buses_count" :capacidad="$parqueo->capacidad" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-slate-500">No hay parqueos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>
