@php
    // Si "Resolver" lleva a esta misma página, la solución se hace aquí: solicitar unidad o atender con una nota.
    $resuelveAqui = $accion && str_starts_with($accion['url'], route('alertas.show', $alerta));
    $solicitaUnidad = $resuelveAqui && $accion['texto'] === 'Solicitar unidad';
@endphp

<x-layouts.app :titulo="$alerta->tipoTexto()" :subtitulo="'Alerta #'.$alerta->id">
    <x-slot:acciones>
        <a href="{{ route('alertas.index') }}" class="btn-secundario"><x-icono nombre="atras" class="size-4" /> Alertas</a>
        @if ($accion && ! $resuelveAqui)
            <a href="{{ $accion['url'] }}" class="btn-primario" data-resolver><x-icono nombre="exito" class="size-4" /> {{ $accion['texto'] }}</a>
        @elseif ($solicitaUnidad)
            <form method="POST" action="{{ route('alertas.solicitar-unidad', $alerta) }}">
                @csrf
                <button class="btn-primario" data-resolver><x-icono nombre="buses" class="size-4" /> Solicitar unidad</button>
            </form>
        @endif
    </x-slot:acciones>

    <div class="grid max-w-5xl gap-6 lg:grid-cols-5">
        <div class="space-y-6 lg:col-span-3">
            <section @class(['tarjeta border-l-4 p-6', 'border-amber-400' => $alerta->activa(), 'border-slate-300' => ! $alerta->activa()])>
                <p class="text-base text-slate-800">{{ $alerta->mensaje }}</p>

                <dl class="mt-6 grid gap-5 sm:grid-cols-3">
                    <div><dt class="text-xs text-slate-500">Estado</dt><dd class="mt-1"><x-estado :valor="$alerta->estado" /></dd></div>
                    <div><dt class="text-xs text-slate-500">Generada</dt><dd class="mt-1">{{ $alerta->created_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Atendida</dt><dd class="mt-1">{{ $alerta->atendida_en?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                    <div>
                        <dt class="text-xs text-slate-500">Estación</dt>
                        <dd class="mt-1">
                            @if ($alerta->estacion)
                                <a href="{{ route('estaciones.show', $alerta->estacion) }}" class="font-medium text-celeste-700 hover:underline">{{ $alerta->estacion->nombre }}</a>
                            @else — @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Bus</dt>
                        <dd class="mt-1">
                            @if ($alerta->bus)
                                <a href="{{ route('buses.show', $alerta->bus) }}" class="font-medium text-celeste-700 hover:underline">{{ $alerta->bus->codigo }}</a>
                            @else — @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Línea</dt>
                        <dd class="mt-1">
                            @if ($alerta->linea)
                                <a href="{{ route('lineas.show', $alerta->linea) }}" class="font-medium text-celeste-700 hover:underline">{{ $alerta->linea->codigo }}</a>
                            @else — @endif
                        </dd>
                    </div>
                </dl>
            </section>

            @if ($alerta->activa())
                {{-- Atender a mano, dejando constancia de qué se hizo (queda en la bitácora) --}}
                <form method="POST" action="{{ route('alertas.atender', $alerta) }}" @class(['tarjeta space-y-3 p-6', 'ring-2 ring-celeste-300' => $resolviendo]) id="atender">
                    @csrf @method('PATCH')
                    <h2 class="font-semibold text-celeste-900">Atender alerta</h2>
                    <div>
                        <label for="campo_nota" class="etiqueta">¿Qué se hizo?</label>
                        <textarea id="campo_nota" name="nota" rows="3" maxlength="200" class="campo" @if ($resolviendo) autofocus @endif
                                  placeholder="Por ejemplo: se revisó el torniquete y quedó funcionando.">{{ old('nota') }}</textarea>
                        @error('nota') <p class="campo-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex justify-end">
                        <button class="btn-secundario"><x-icono nombre="exito" class="size-4" /> Atender</button>
                    </div>
                </form>
            @endif
        </div>

        {{-- Historial: creación, solicitudes y atención, tomado de la bitácora --}}
        <section class="tarjeta p-6 lg:col-span-2">
            <h2 class="font-semibold text-celeste-900">Historial</h2>
            <ol class="mt-4 space-y-4 border-l-2 border-celeste-100 pl-4" data-historial>
                @forelse ($historial as $registro)
                    <li class="relative text-sm">
                        <span class="absolute top-1.5 -left-[1.4rem] size-2.5 rounded-full {{ $registro->accion === 'atender' ? 'bg-emerald-500' : 'bg-celeste-400' }}"></span>
                        <p class="text-slate-700">{{ $registro->detalle }}</p>
                        <p class="text-xs text-slate-400">{{ $registro->user?->name ?? 'Sistema' }} · {{ $registro->created_at?->format('d/m/Y H:i') }}</p>
                    </li>
                @empty
                    <li class="text-sm text-slate-500">Sin movimientos registrados.</li>
                @endforelse
            </ol>
        </section>
    </div>
</x-layouts.app>
