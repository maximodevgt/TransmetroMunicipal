@php($esAdmin = auth()->user()->esAdministrador())

<x-layouts.app :titulo="$linea->codigo.' · '.$linea->nombre" :subtitulo="$linea->municipalidad->nombre">
    <x-slot:acciones>
        <a href="{{ route('lineas.index') }}" class="btn-secundario"><x-icono nombre="atras" class="size-4" /> Líneas</a>
        @if ($esAdmin)
            <a href="{{ route('lineas.edit', $linea) }}" class="btn-secundario"><x-icono nombre="editar" class="size-4" /> Editar</a>
            <x-eliminar :accion="route('lineas.destroy', $linea)" titulo="¿Eliminar la línea {{ $linea->codigo }}?" confirmar="Sus buses quedarán sin línea. Esta acción no se puede deshacer." />
        @endif
    </x-slot:acciones>

    {{-- Indicadores de la linea --}}
    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
        <div class="tarjeta p-4"><p class="text-xs text-slate-500">Estado</p><x-estado :valor="$linea->estado" class="mt-1" /></div>
        <div class="tarjeta p-4"><p class="text-xs text-slate-500">Estaciones</p><p class="text-xl font-bold text-celeste-900">{{ $linea->estaciones->count() }}</p></div>
        <div class="tarjeta p-4"><p class="text-xs text-slate-500">Distancia total</p><p class="text-xl font-bold text-celeste-900">{{ number_format($distanciaTotal, 2) }} km</p></div>
        <div class="tarjeta p-4">
            <p class="text-xs text-slate-500">Buses (mín. {{ $minBuses }} · máx. {{ $maxBuses }})</p>
            <p @class(['text-xl font-bold', 'text-celeste-900' => $linea->buses->count() >= $minBuses && $linea->buses->count() <= $maxBuses, 'text-amber-600' => $linea->buses->count() < $minBuses || $linea->buses->count() > $maxBuses])>
                {{ $linea->buses->count() }}
            </p>
        </div>
    </div>

    @if ($linea->buses->count() < $minBuses)
        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
            La línea necesita al menos {{ $minBuses }} buses (uno por estación). Faltan {{ $minBuses - $linea->buses->count() }}.
        </div>
    @elseif ($linea->buses->count() > $maxBuses)
        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
            La línea supera el máximo de {{ $maxBuses }} buses (estaciones × 2). Reasigne {{ $linea->buses->count() - $maxBuses }}.
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-5">
        {{-- Recorrido --}}
        <section class="tarjeta p-5 lg:col-span-3">
            <h2 class="font-semibold text-celeste-900">Recorrido</h2>

            @if ($linea->estaciones->isEmpty())
                <p class="mt-4 text-sm text-slate-500">La línea aún no tiene estaciones.</p>
            @else
                <ol class="mt-4">
                    @foreach ($linea->estaciones as $estacion)
                        <li class="relative flex gap-4 pb-5 last:pb-0">
                            @unless ($loop->last)
                                <span class="absolute top-8 left-4 h-full w-0.5 -translate-x-1/2 bg-celeste-200"></span>
                            @endunless
                            <span class="relative z-10 flex size-8 shrink-0 items-center justify-center rounded-full bg-celeste-500 text-xs font-bold text-white ring-4 ring-celeste-100">
                                {{ $estacion->pivot->orden }}
                            </span>
                            <div class="flex min-w-0 flex-1 flex-wrap items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <a href="{{ route('estaciones.show', $estacion) }}" class="font-medium text-slate-800 hover:text-celeste-600">{{ $estacion->nombre }}</a>
                                    <p class="text-xs text-slate-500">
                                        {{ $loop->first ? 'Inicio del recorrido' : '+'.number_format($estacion->pivot->distancia_anterior_km, 2).' km desde la anterior' }}
                                    </p>
                                </div>
                                @if ($esAdmin)
                                    <div class="flex items-center gap-2">
                                        @unless ($loop->first)
                                            <form method="POST" action="{{ route('lineas.estaciones.update', [$linea, $estacion]) }}" class="flex items-center gap-1">
                                                @csrf @method('PUT')
                                                <input type="number" name="distancia_anterior_km" step="0.01" min="0" value="{{ $estacion->pivot->distancia_anterior_km }}"
                                                       class="campo w-20 py-1 text-xs" aria-label="Distancia en km">
                                                <button class="rounded-lg px-2 py-1 text-xs font-semibold text-celeste-600 hover:bg-celeste-50">Guardar</button>
                                            </form>
                                        @endunless
                                        <form method="POST" action="{{ route('lineas.estaciones.destroy', [$linea, $estacion]) }}" data-confirmar-titulo="¿Quitar {{ $estacion->nombre }} de la línea?" data-confirmar="Dejará de formar parte del recorrido de la línea {{ $linea->codigo }}." data-confirmar-boton="Sí, quitar">
                                            @csrf @method('DELETE')
                                            <button class="rounded-lg p-1.5 text-rose-500 hover:bg-rose-50" title="Quitar de la línea"><x-icono nombre="eliminar" class="size-4" /></button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif

            @if ($esAdmin)
                <form method="POST" action="{{ route('lineas.estaciones.store', $linea) }}" class="mt-6 grid gap-3 rounded-xl bg-celeste-50 p-4 sm:grid-cols-6 sm:items-end">
                    @csrf
                    <p class="text-sm font-semibold text-celeste-800 sm:col-span-6">Agregar estación al recorrido</p>
                    <x-campo nombre="estacion_id" etiqueta="Estación" tipo="select" class="sm:col-span-3"
                             :opciones="$estacionesDisponibles->pluck('nombre', 'id')" vacio="Seleccione…" required />
                    <x-campo nombre="orden" etiqueta="Posición" tipo="number" min="1" :max="$linea->estaciones->count() + 1"
                             :placeholder="'Al final ('.($linea->estaciones->count() + 1).')'" />
                    <x-campo nombre="distancia_anterior_km" etiqueta="Km desde anterior" tipo="number" step="0.01" min="0" valor="0" required />
                    <button class="btn-primario"><x-icono nombre="mas" class="size-4" /> Agregar</button>
                </form>
            @endif
        </section>

        {{-- Buses asignados --}}
        <section class="tarjeta overflow-hidden lg:col-span-2">
            <div class="flex items-center justify-between px-5 py-4">
                <h2 class="font-semibold text-celeste-900">Buses asignados</h2>
                <a href="{{ route('buses.index', ['linea_id' => $linea->id]) }}" class="text-sm font-medium text-celeste-600 hover:underline">Ver todos</a>
            </div>
            <table class="tabla">
                <thead><tr><th>Bus</th><th>Placa</th><th>Estado</th></tr></thead>
                <tbody>
                    @forelse ($linea->buses as $bus)
                        <tr>
                            <td><a href="{{ route('buses.show', $bus) }}" class="font-medium text-celeste-700 hover:underline">{{ $bus->codigo }}</a></td>
                            <td>{{ $bus->placa }}</td>
                            <td><x-estado :valor="$bus->estado" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-slate-500">Sin buses asignados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>
</x-layouts.app>
