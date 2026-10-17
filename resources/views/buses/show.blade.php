<x-layouts.app :titulo="'Bus '.$bus->codigo" :subtitulo="'Placa '.$bus->placa">
    <x-slot:acciones>
        <a href="{{ route('buses.index') }}" class="btn-secundario"><x-icono nombre="atras" class="size-4" /> Buses</a>
        @if (auth()->user()->esAdministrador())
            <a href="{{ route('buses.edit', $bus) }}" class="btn-secundario"><x-icono nombre="editar" class="size-4" /> Editar</a>
            <x-eliminar :accion="route('buses.destroy', $bus)" titulo="¿Eliminar el bus {{ $bus->codigo }}?" />
        @endif
    </x-slot:acciones>

    <section class="tarjeta max-w-2xl p-6">
        <dl class="grid gap-5 sm:grid-cols-2">
            <div><dt class="text-xs text-slate-500">Estado</dt><dd class="mt-1"><x-estado :valor="$bus->estado" /></dd></div>
            <div>
                <dt class="text-xs text-slate-500">Línea</dt>
                <dd class="mt-1 font-medium">
                    @if ($bus->linea)
                        <a href="{{ route('lineas.show', $bus->linea) }}" class="text-celeste-700 hover:underline">{{ $bus->linea->codigo }} · {{ $bus->linea->nombre }}</a>
                    @else
                        <span class="text-slate-400">Sin línea asignada</span>
                    @endif
                </dd>
            </div>
            <div><dt class="text-xs text-slate-500">Parqueo</dt><dd class="mt-1 font-medium">{{ $bus->parqueo->nombre }}</dd></div>
            <div><dt class="text-xs text-slate-500">Capacidad máxima</dt><dd class="mt-1 font-medium">{{ $bus->capacidad_maxima }} pasajeros</dd></div>
            <div><dt class="text-xs text-slate-500">Registrado</dt><dd class="mt-1">{{ $bus->created_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
            <div><dt class="text-xs text-slate-500">Última actualización</dt><dd class="mt-1">{{ $bus->updated_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
        </dl>
    </section>
</x-layouts.app>
