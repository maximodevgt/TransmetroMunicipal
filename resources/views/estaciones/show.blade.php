<x-layouts.app :titulo="$estacion->nombre" :subtitulo="$estacion->direccion">
    <x-slot:acciones>
        <a href="{{ route('estaciones.index') }}" class="btn-secundario"><x-icono nombre="atras" class="size-4" /> Estaciones</a>
        @if (auth()->user()->esAdministrador())
            <a href="{{ route('estaciones.edit', $estacion) }}" class="btn-secundario"><x-icono nombre="editar" class="size-4" /> Editar</a>
            <x-eliminar :accion="route('estaciones.destroy', $estacion)" confirmar="¿Eliminar la estación {{ $estacion->nombre }}?" />
        @endif
    </x-slot:acciones>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="tarjeta space-y-4 p-5">
            <div>
                <p class="text-xs text-slate-500">Estado</p>
                <x-estado :valor="$estacion->estado" class="mt-1" />
            </div>
            <div>
                <p class="text-xs text-slate-500">Municipalidad</p>
                <p class="font-medium">{{ $estacion->municipalidad->nombre }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-500">Líneas</p>
                <div class="mt-1 flex flex-wrap gap-2">
                    @forelse ($estacion->lineas as $linea)
                        <a href="{{ route('lineas.show', $linea) }}" class="insignia bg-celeste-100 text-celeste-700 hover:bg-celeste-200">
                            {{ $linea->codigo }} · parada {{ $linea->pivot->orden }}
                        </a>
                    @empty
                        <span class="text-sm text-slate-400">No pertenece a ninguna línea.</span>
                    @endforelse
                </div>
            </div>
            <div>
                <p class="text-xs text-slate-500">Operador asignado</p>
                @forelse ($estacion->operadores as $operador)
                    <p class="font-medium">{{ $operador->name }} <span class="text-xs text-slate-400">{{ $operador->email }}</span></p>
                @empty
                    <p class="text-sm text-amber-600">Sin operador asignado.</p>
                @endforelse
            </div>
        </section>

        <section class="tarjeta overflow-hidden lg:col-span-2">
            <h2 class="px-5 py-4 font-semibold text-celeste-900">Accesos y guardias</h2>
            <table class="tabla">
                <thead><tr><th>Acceso</th><th>Guardia</th><th>Turno</th><th>Teléfono</th></tr></thead>
                <tbody>
                    @forelse ($estacion->accesos as $acceso)
                        @forelse ($acceso->guardias as $guardia)
                            <tr>
                                <td class="font-medium">{{ $acceso->nombre }}</td>
                                <td>{{ $guardia->nombre }}</td>
                                <td><span class="insignia bg-celeste-50 text-celeste-700">{{ ucfirst($guardia->turno) }}</span></td>
                                <td>{{ $guardia->telefono }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="font-medium">{{ $acceso->nombre }}</td>
                                <td colspan="3" class="text-amber-600">Sin guardia asignado</td>
                            </tr>
                        @endforelse
                    @empty
                        <tr><td colspan="4" class="text-slate-500">La estación no tiene accesos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>
</x-layouts.app>
