<x-layouts.app :titulo="$piloto->nombre" :subtitulo="'Licencia '.$piloto->licencia">
    <x-slot:acciones>
        <a href="{{ route('pilotos.index') }}" class="btn-secundario"><x-icono nombre="atras" class="size-4" /> Pilotos</a>
        <a href="{{ route('pilotos.edit', $piloto) }}" class="btn-secundario"><x-icono nombre="editar" class="size-4" /> Editar</a>
        <x-eliminar :accion="route('pilotos.destroy', $piloto)" confirmar="¿Eliminar al piloto {{ $piloto->nombre }}?" />
    </x-slot:acciones>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="tarjeta p-5">
            <dl class="space-y-4">
                <div><dt class="text-xs text-slate-500">Estado</dt><dd class="mt-1"><x-estado :valor="$piloto->estado" /></dd></div>
                <div><dt class="text-xs text-slate-500">DPI</dt><dd class="font-mono">{{ $piloto->dpi }}</dd></div>
                <div><dt class="text-xs text-slate-500">Teléfono</dt><dd>{{ $piloto->telefono }}</dd></div>
                <div><dt class="text-xs text-slate-500">Correo</dt><dd>{{ $piloto->email ?? '—' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Residencia</dt><dd>{{ $piloto->residencia }}</dd></div>
            </dl>
        </section>

        <section class="tarjeta overflow-hidden lg:col-span-2">
            <h2 class="px-5 py-4 font-semibold text-celeste-900">Historial educativo</h2>
            <table class="tabla">
                <thead><tr><th>Nivel</th><th>Institución</th><th>Título</th><th>Año</th><th></th></tr></thead>
                <tbody>
                    @forelse ($piloto->historialEducativo as $estudio)
                        <tr>
                            <td><span class="insignia bg-celeste-100 text-celeste-700">{{ ucfirst($estudio->nivel) }}</span></td>
                            <td>{{ $estudio->institucion }}</td>
                            <td>{{ $estudio->titulo ?? '—' }}</td>
                            <td>{{ $estudio->anio ?? '—' }}</td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('pilotos.historial.destroy', [$piloto, $estudio]) }}" data-confirmar="¿Quitar este estudio del historial?">
                                    @csrf @method('DELETE')
                                    <button class="rounded-lg p-1.5 text-rose-500 hover:bg-rose-50" title="Quitar"><x-icono nombre="eliminar" class="size-4" /></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-slate-500">Sin estudios registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <form method="POST" action="{{ route('pilotos.historial.store', $piloto) }}" class="m-5 grid gap-3 rounded-xl bg-celeste-50 p-4 sm:grid-cols-6 sm:items-end">
                @csrf
                <p class="text-sm font-semibold text-celeste-800 sm:col-span-6">Agregar estudio</p>
                <x-campo nombre="nivel" etiqueta="Nivel" tipo="select" class="sm:col-span-2" required vacio="Seleccione…"
                         :opciones="collect($niveles)->mapWithKeys(fn ($n) => [$n => ucfirst($n)])" />
                <x-campo nombre="institucion" etiqueta="Institución" class="sm:col-span-4" required maxlength="150" />
                <x-campo nombre="titulo" etiqueta="Título" class="sm:col-span-3" maxlength="150" />
                <x-campo nombre="anio" etiqueta="Año" tipo="number" min="1901" :max="now()->year" class="sm:col-span-2" />
                <button class="btn-primario"><x-icono nombre="mas" class="size-4" /> Agregar</button>
            </form>
        </section>
    </div>
</x-layouts.app>
