<x-layouts.app :titulo="$bus->exists ? 'Editar bus '.$bus->codigo : 'Nuevo bus'">
    <x-slot:acciones>
        <a href="{{ $bus->exists ? route('buses.show', $bus) : route('buses.index') }}" class="btn-secundario"><x-icono nombre="atras" class="size-4" /> Volver</a>
    </x-slot:acciones>

    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ $bus->exists ? route('buses.update', $bus) : route('buses.store') }}" class="tarjeta space-y-5 p-6 lg:col-span-2">
            @csrf
            @if ($bus->exists) @method('PUT') @endif

            @if ($bus->recorridoEnCurso)
                <p class="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800 ring-1 ring-emerald-200">
                    Este bus está en ruta (<a href="{{ route('recorridos.show', $bus->recorridoEnCurso) }}" class="font-semibold underline">recorrido #{{ $bus->recorridoEnCurso->id }}</a>):
                    no se puede cambiar su línea ni su estado hasta finalizar el recorrido.
                </p>
            @endif

            <div class="grid gap-5 sm:grid-cols-2">
                <x-campo nombre="codigo" etiqueta="Código" :valor="$bus->codigo" required maxlength="20" placeholder="BUS-136" />
                <x-campo nombre="placa" etiqueta="Placa" :valor="$bus->placa" required maxlength="10" placeholder="TM-136" />
                <x-campo nombre="linea_id" etiqueta="Línea" tipo="select" :valor="$bus->linea_id" vacio="Sin línea (en parqueo)"
                         :opciones="$lineas->mapWithKeys(fn ($l) => [$l->id => $l->codigo.' · '.$l->nombre])" />
                <x-campo nombre="parqueo_id" etiqueta="Parqueo" tipo="select" :valor="$bus->parqueo_id" vacio="Seleccione…" required
                         :opciones="$parqueos->pluck('nombre', 'id')" />
                <x-campo nombre="capacidad_maxima" etiqueta="Capacidad máxima (pasajeros)" tipo="number" min="1" max="500" :valor="$bus->capacidad_maxima" required />
                <x-campo nombre="estado" etiqueta="Estado" tipo="select" :valor="$bus->estado" required
                         :opciones="collect($estados)->mapWithKeys(fn ($e) => [$e => ucfirst(str_replace('_', ' ', $e))])" />
            </div>

            <div class="flex justify-end border-t border-celeste-50 pt-5">
                <button class="btn-primario">{{ $bus->exists ? 'Guardar cambios' : 'Registrar bus' }}</button>
            </div>
        </form>

        {{-- Cupo por linea: maximo = estaciones x 2 (lo valida el trigger) --}}
        <aside class="tarjeta p-5">
            <h2 class="font-semibold text-celeste-900">Cupo por línea</h2>
            <p class="mt-1 text-xs text-slate-500">Máximo permitido: estaciones × 2.</p>
            <ul class="mt-4 space-y-3">
                @foreach ($lineas as $linea)
                    @php($max = $linea->estaciones_count * 2)
                    <li>
                        <div class="flex justify-between text-sm">
                            <span class="font-medium">{{ $linea->codigo }}</span>
                            <span @class(['text-slate-500', 'font-semibold text-rose-600' => $linea->buses_count >= $max])>{{ $linea->buses_count }} / {{ $max }}</span>
                        </div>
                        <div class="mt-1 h-1.5 rounded-full bg-celeste-100">
                            <div class="h-1.5 rounded-full bg-celeste-500" style="width: {{ $max ? min(100, round($linea->buses_count / $max * 100)) : 0 }}%"></div>
                        </div>
                    </li>
                @endforeach
            </ul>
            <h2 class="mt-6 font-semibold text-celeste-900">Parqueos</h2>
            <ul class="mt-2 space-y-1 text-sm">
                @foreach ($parqueos as $parqueo)
                    <li class="flex justify-between"><span>{{ $parqueo->nombre }}</span><span class="text-slate-500">{{ $parqueo->buses_count }} / {{ $parqueo->capacidad }}</span></li>
                @endforeach
            </ul>
        </aside>
    </div>
</x-layouts.app>
