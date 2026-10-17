@php($esAdmin = auth()->user()->esAdministrador())

<x-layouts.app :titulo="$estacion->nombre" :subtitulo="$estacion->direccion">
    <x-slot:acciones>
        <a href="{{ route('estaciones.index') }}" class="btn-secundario"><x-icono nombre="atras" class="size-4" /> Estaciones</a>
        @if ($esAdmin)
            <a href="{{ route('estaciones.edit', $estacion) }}" class="btn-secundario"><x-icono nombre="editar" class="size-4" /> Editar</a>
            <x-eliminar :accion="route('estaciones.destroy', $estacion)" titulo="¿Eliminar la estación {{ $estacion->nombre }}?" />
        @endif
    </x-slot:acciones>

    <div class="grid gap-6 xl:grid-cols-3">
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

        <section class="tarjeta overflow-hidden xl:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-2 px-5 py-4">
                <h2 class="font-semibold text-celeste-900">Accesos y guardias</h2>
                @if ($esAdmin)
                    <a href="{{ route('estaciones.accesos.create', $estacion) }}" class="btn-primario"><x-icono nombre="mas" class="size-4" /> Nuevo acceso</a>
                @endif
            </div>

            @forelse ($estacion->accesos as $acceso)
                <div class="border-t border-celeste-100 px-5 py-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="font-medium text-slate-800">
                            {{ $acceso->nombre }}
                            <span class="text-xs font-normal text-slate-500">· {{ $acceso->guardias->count() }} {{ $acceso->guardias->count() === 1 ? 'guardia' : 'guardias' }}</span>
                        </h3>
                        @if ($esAdmin)
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('accesos.guardias.create', $acceso) }}" class="btn-secundario"><x-icono nombre="mas" class="size-4" /> Guardia</a>
                                <a href="{{ route('accesos.edit', $acceso) }}" class="btn-secundario"><x-icono nombre="editar" class="size-4" /> Renombrar</a>
                                <x-eliminar :accion="route('accesos.destroy', $acceso)" titulo="¿Eliminar el acceso {{ $acceso->nombre }}?" etiqueta="Eliminar el acceso {{ $acceso->nombre }}"
                                            confirmar="{{ $acceso->guardias->count() === 1 ? 'Se retirará también su guardia.' : 'Se retirarán también sus '.$acceso->guardias->count().' guardias.' }} Esta acción no se puede deshacer." />
                            </div>
                        @endif
                    </div>

                    <div class="mt-3 overflow-x-auto rounded-xl ring-1 ring-celeste-50">
                        <table class="tabla">
                            <thead>
                                <tr>
                                    <th>Guardia</th><th>DPI</th><th>Turno</th><th>Teléfono</th>
                                    @if ($esAdmin)<th class="text-right"><span class="sr-only">Acciones</span></th>@endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($acceso->guardias as $guardia)
                                    <tr>
                                        <td class="font-medium whitespace-nowrap">{{ $guardia->nombre }}</td>
                                        <td class="tabular-nums">{{ $guardia->dpi }}</td>
                                        <td><span class="insignia bg-celeste-50 text-celeste-700">{{ \App\Models\Guardia::TURNOS[$guardia->turno] ?? $guardia->turno }}</span></td>
                                        <td>{{ $guardia->telefono ?? '—' }}</td>
                                        @if ($esAdmin)
                                            <td class="text-right whitespace-nowrap">
                                                <a href="{{ route('guardias.edit', $guardia) }}" class="inline-flex rounded-lg p-1.5 text-celeste-600 hover:bg-celeste-50" aria-label="Editar a {{ $guardia->nombre }}">
                                                    <x-icono nombre="editar" class="size-4" />
                                                </a>
                                                @if ($acceso->guardias->count() > 1)
                                                    <form method="POST" action="{{ route('guardias.destroy', $guardia) }}" class="inline"
                                                          data-confirmar-titulo="¿Eliminar a {{ $guardia->nombre }}?" data-confirmar="Dejará de cubrir el acceso {{ $acceso->nombre }}.">
                                                        @csrf @method('DELETE')
                                                        <button class="rounded-lg p-1.5 text-rose-500 hover:bg-rose-50" aria-label="Eliminar a {{ $guardia->nombre }}"><x-icono nombre="eliminar" class="size-4" /></button>
                                                    </form>
                                                @else
                                                    <span class="inline-flex cursor-not-allowed rounded-lg p-1.5 text-slate-300" title="Último guardia del acceso: no se puede eliminar (RN-11)">
                                                        <x-icono nombre="eliminar" class="size-4" />
                                                        <span class="sr-only">Último guardia del acceso, no se puede eliminar</span>
                                                    </span>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-amber-600">Sin guardia asignado (RN-11).</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <p class="border-t border-celeste-100 px-5 py-4 text-sm text-slate-500">La estación no tiene accesos registrados.</p>
            @endforelse
        </section>
    </div>
</x-layouts.app>
