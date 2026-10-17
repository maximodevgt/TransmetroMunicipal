<x-layouts.app :titulo="'Mi estación: '.$estacion->nombre" :subtitulo="$estacion->direccion">
    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Pasajeros en espera --}}
        <section class="tarjeta p-5">
            <h2 class="font-semibold text-celeste-900">Pasajeros en espera</h2>
            <p class="mt-1 text-xs text-slate-500">
                @if ($referencia)
                    Capacidad de referencia: {{ $referencia[0] }} pasajeros ({{ $referencia[1]->codigo }}).
                    Desde {{ (int) ceil($referencia[0] * \App\Services\AlertaService::ALTA_DEMANDA) }} en espera se genera una alerta de alta demanda.
                @else
                    Las líneas de esta estación no tienen buses asignados.
                @endif
            </p>
            <form method="POST" action="{{ route('mi-estacion.pasajeros') }}" class="mt-4 flex items-end gap-2">
                @csrf
                <x-campo nombre="cantidad" etiqueta="Personas esperando" tipo="number" min="0" max="5000" required class="flex-1" />
                <button class="btn-primario">Registrar</button>
            </form>

            <h3 class="mt-6 text-sm font-semibold text-slate-700">Últimos registros</h3>
            <ul class="mt-2 divide-y divide-celeste-50 text-sm">
                @forelse ($registros as $registro)
                    <li class="flex justify-between py-2">
                        <span class="font-medium">{{ $registro->cantidad }} personas</span>
                        <span class="text-slate-500">{{ $registro->registrado_en->format('d/m H:i') }}</span>
                    </li>
                @empty
                    <li class="py-2 text-slate-500">Aún no hay registros.</li>
                @endforelse
            </ul>
        </section>

        {{-- Buses en curso por las líneas de la estación --}}
        <section class="tarjeta overflow-hidden lg:col-span-2">
            <div class="px-5 py-4">
                <h2 class="font-semibold text-celeste-900">Buses en camino</h2>
                <p class="text-xs text-slate-500">Recorridos en curso de las líneas {{ $estacion->lineas->pluck('codigo')->join(', ', ' y ') ?: '—' }}.</p>
            </div>
            @forelse ($recorridos as $recorrido)
                @php($esMiTurno = $recorrido->siguiente?->id === $estacion->id)
                <div @class(['border-t border-celeste-100 px-5 py-4', 'bg-celeste-50/60' => $esMiTurno])>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="font-medium">
                            <a href="{{ route('recorridos.show', $recorrido) }}" class="text-celeste-700 hover:underline">{{ $recorrido->bus->codigo }}</a>
                            <span class="text-sm font-normal text-slate-500">· {{ $recorrido->linea->codigo }} · {{ $recorrido->piloto->nombre }} · {{ $recorrido->bus->capacidad_maxima }} pasajeros</span>
                        </p>
                        @if ($esMiTurno)
                            <span class="insignia bg-celeste-500 text-white">Llega a esta estación</span>
                        @elseif ($recorrido->yaPaso)
                            <span class="insignia bg-slate-100 text-slate-600">Ya pasó por aquí</span>
                        @else
                            <span class="insignia bg-celeste-50 text-celeste-700">Próxima parada: {{ $recorrido->siguiente?->nombre ?? 'fin de línea' }}</span>
                        @endif
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        Última parada: {{ $recorrido->visitas->last()?->estacion->nombre ?? 'aún no llega a la primera estación' }}
                    </p>

                    @if ($esMiTurno)
                        <form method="POST" action="{{ route('recorridos.llegadas.store', $recorrido) }}" class="mt-3 flex flex-wrap items-end gap-2">
                            @csrf
                            <input type="hidden" name="estacion_id" value="{{ $estacion->id }}">
                            <div>
                                <label for="pasajeros_{{ $recorrido->id }}" class="etiqueta text-xs">Pasajeros a bordo</label>
                                <input id="pasajeros_{{ $recorrido->id }}" type="number" name="pasajeros_a_bordo" min="0" max="{{ $recorrido->bus->capacidad_maxima }}" required class="campo w-32">
                            </div>
                            <button class="btn-primario">Registrar llegada</button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="border-t border-celeste-100 px-5 py-4 text-sm text-slate-500">No hay buses en curso por las líneas de esta estación.</p>
            @endforelse
        </section>
    </div>

    <section class="tarjeta mt-6 p-5">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-celeste-900">Alertas activas de la estación</h2>
            <a href="{{ route('alertas.index') }}" class="text-sm font-medium text-celeste-600 hover:underline">Ver todas</a>
        </div>
        <ul class="mt-3 space-y-2">
            @forelse ($alertas as $alerta)
                <li>
                    <a href="{{ route('alertas.show', $alerta) }}" class="block rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm hover:border-amber-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                        <span class="font-medium text-amber-800">{{ $alerta->tipoTexto() }}</span>
                        <span class="block text-amber-700">{{ $alerta->mensaje }}</span>
                    </a>
                </li>
            @empty
                <li class="text-sm text-slate-500">No hay alertas activas.</li>
            @endforelse
        </ul>
    </section>
</x-layouts.app>
