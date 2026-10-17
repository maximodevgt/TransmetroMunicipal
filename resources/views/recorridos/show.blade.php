@php
    $usuario = auth()->user();
    $capacidad = $recorrido->bus->capacidad_maxima;
    $puedeRegistrar = $siguiente && ($usuario->esAdministrador() || $usuario->estacion_id === $siguiente->id);
@endphp

<x-layouts.app :titulo="'Recorrido #'.$recorrido->id" :subtitulo="$recorrido->linea->codigo.' · '.$recorrido->linea->nombre">
    <x-slot:acciones>
        <a href="{{ route('recorridos.index') }}" class="btn-secundario"><x-icono nombre="atras" class="size-4" /> Recorridos</a>
        @if ($usuario->esAdministrador() && $recorrido->enCurso())
            <form method="POST" action="{{ route('recorridos.finalizar', $recorrido) }}"
                  data-confirmar-titulo="¿Finalizar el recorrido #{{ $recorrido->id }}?"
                  data-confirmar="{{ $recorrido->bus->codigo }} volverá a estar disponible{{ $siguiente ? ' aunque no haya pasado por todas las estaciones' : '' }}."
                  data-confirmar-boton="Sí, finalizar">
                @csrf @method('PATCH')
                <button class="btn-primario">Finalizar recorrido</button>
            </form>
        @endif
    </x-slot:acciones>

    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
        <div class="tarjeta p-4"><p class="text-xs text-slate-500">Estado</p><x-estado :valor="$recorrido->estado" class="mt-1" /></div>
        <div class="tarjeta p-4">
            <p class="text-xs text-slate-500">Bus</p>
            <a href="{{ route('buses.show', $recorrido->bus) }}" class="font-semibold text-celeste-700 hover:underline">{{ $recorrido->bus->codigo }}</a>
            <p class="text-xs text-slate-500">{{ $capacidad }} pasajeros</p>
        </div>
        <div class="tarjeta p-4">
            <p class="text-xs text-slate-500">Piloto</p>
            @if ($usuario->esAdministrador())
                <a href="{{ route('pilotos.show', $recorrido->piloto) }}" class="font-semibold text-celeste-700 hover:underline">{{ $recorrido->piloto->nombre }}</a>
            @else
                <p class="font-semibold">{{ $recorrido->piloto->nombre }}</p>
            @endif
        </div>
        <div class="tarjeta p-4">
            <p class="text-xs text-slate-500">Distancia recorrida</p>
            <p class="text-xl font-bold text-celeste-900">{{ number_format($distancia, 2) }} km</p>
            <p class="text-xs text-slate-500">de {{ number_format($distanciaTotal, 2) }} km de la línea</p>
        </div>
    </div>

    <p class="mt-4 text-sm text-slate-500">
        Inicio: {{ $recorrido->inicio->format('d/m/Y H:i') }}
        @if ($recorrido->fin) · Fin: {{ $recorrido->fin->format('d/m/Y H:i') }} @endif
    </p>

    <section class="tarjeta mt-4 p-5">
        <h2 class="font-semibold text-celeste-900">Estaciones del recorrido</h2>
        <ol class="mt-4">
            @foreach ($recorrido->linea->estaciones as $estacion)
                @php
                    $visita = $visitas[$estacion->id] ?? null;
                    $esSiguiente = $siguiente?->id === $estacion->id;
                    $porcentaje = $visita ? (int) round($visita->pasajeros_a_bordo / $capacidad * 100) : null;
                    [$colorOcupacion, $textoOcupacion] = match (true) {
                        $porcentaje === null => ['', ''],
                        $porcentaje < 25 => ['bg-amber-100 text-amber-800', 'Baja ocupación'],
                        $porcentaje >= 90 => ['bg-rose-100 text-rose-700', 'Casi lleno'],
                        default => ['bg-emerald-100 text-emerald-700', 'Normal'],
                    };
                @endphp
                <li class="relative flex gap-4 pb-6 last:pb-0">
                    @unless ($loop->last)
                        <span @class(['absolute top-8 left-4 h-full w-0.5 -translate-x-1/2', 'bg-celeste-400' => $visita, 'bg-celeste-100' => ! $visita])></span>
                    @endunless
                    <span @class([
                        'relative z-10 flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-bold ring-4',
                        'bg-celeste-500 text-white ring-celeste-100' => $visita,
                        'bg-white text-celeste-600 ring-celeste-300 motion-safe:animate-pulse' => $esSiguiente,
                        'bg-slate-100 text-slate-400 ring-white' => ! $visita && ! $esSiguiente,
                    ])>{{ $estacion->pivot->orden }}</span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <p @class(['font-medium', 'text-slate-800' => $visita || $esSiguiente, 'text-slate-400' => ! $visita && ! $esSiguiente])>
                                {{ $estacion->nombre }}
                                @if ($esSiguiente) <span class="insignia ml-1 bg-celeste-100 text-celeste-700">Siguiente</span> @endif
                            </p>
                            @if ($visita)
                                <p class="flex items-center gap-1 text-xs text-slate-500">
                                    <x-icono nombre="reloj" class="size-4" />
                                    {{ $visita->hora_llegada->format('H:i') }} → {{ $visita->hora_salida?->format('H:i') ?? 'en la estación' }}
                                </p>
                            @endif
                        </div>

                        @if ($visita)
                            <div class="mt-1 flex flex-wrap items-center gap-2 text-sm">
                                <span>{{ $visita->pasajeros_a_bordo }} / {{ $capacidad }} pasajeros</span>
                                <span class="insignia {{ $colorOcupacion }}">{{ $porcentaje }} % · {{ $textoOcupacion }}</span>
                                @if ($visita->espera_extra_min > 0)
                                    <span class="insignia bg-amber-50 text-amber-700 ring-1 ring-amber-200">+{{ $visita->espera_extra_min }} min de espera</span>
                                @endif
                            </div>
                        @elseif ($esSiguiente && $puedeRegistrar)
                            <form method="POST" action="{{ route('recorridos.llegadas.store', $recorrido) }}" class="mt-2 flex flex-wrap items-end gap-2 rounded-xl bg-celeste-50 p-3">
                                @csrf
                                <input type="hidden" name="estacion_id" value="{{ $estacion->id }}">
                                <div>
                                    <label for="pasajeros_{{ $estacion->id }}" class="etiqueta text-xs">Pasajeros a bordo</label>
                                    <input id="pasajeros_{{ $estacion->id }}" type="number" name="pasajeros_a_bordo" min="0" max="{{ $capacidad }}" required
                                           value="{{ old('pasajeros_a_bordo') }}" class="campo w-32">
                                </div>
                                <div>
                                    <label for="hora_{{ $estacion->id }}" class="etiqueta text-xs">Hora de llegada</label>
                                    <input id="hora_{{ $estacion->id }}" type="datetime-local" name="hora_llegada" value="{{ old('hora_llegada', now()->format('Y-m-d\TH:i')) }}"
                                           max="{{ now()->format('Y-m-d\TH:i') }}" class="campo">
                                </div>
                                <button class="btn-primario">Registrar llegada</button>
                                @error('pasajeros_a_bordo') <p class="campo-error w-full">{{ $message }}</p> @enderror
                            </form>
                        @elseif ($esSiguiente)
                            <p class="mt-1 text-xs text-slate-500">La llegada la registra el operador de esta estación.</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>

        @if ($recorrido->enCurso() && ! $siguiente)
            <p class="mt-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">El bus ya pasó por todas las estaciones: falta finalizar el recorrido.</p>
        @endif
    </section>
</x-layouts.app>
