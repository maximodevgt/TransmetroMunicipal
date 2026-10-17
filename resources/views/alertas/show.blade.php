<x-layouts.app :titulo="$alerta->tipoTexto()" :subtitulo="'Alerta #'.$alerta->id">
    <x-slot:acciones>
        <a href="{{ route('alertas.index') }}" class="btn-secundario"><x-icono nombre="atras" class="size-4" /> Alertas</a>
        @if ($alerta->activa())
            <form method="POST" action="{{ route('alertas.atender', $alerta) }}">
                @csrf @method('PATCH')
                <button class="btn-primario"><x-icono nombre="exito" class="size-4" /> Atender</button>
            </form>
        @endif
    </x-slot:acciones>

    <section @class(['tarjeta max-w-3xl border-l-4 p-6', 'border-amber-400' => $alerta->activa(), 'border-slate-300' => ! $alerta->activa()])>
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
</x-layouts.app>
