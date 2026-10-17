<x-layouts.app titulo="Iniciar recorrido" subtitulo="El bus pasa a «en ruta» hasta que se finalice el recorrido">
    <x-slot:acciones>
        <a href="{{ route('recorridos.index') }}" class="btn-secundario"><x-icono nombre="atras" class="size-4" /> Volver</a>
    </x-slot:acciones>

    <form method="POST" action="{{ route('recorridos.store') }}" class="tarjeta max-w-2xl space-y-5 p-6">
        @csrf

        <x-campo nombre="linea_id" etiqueta="Línea" tipo="select" :valor="$lineaElegida" vacio="Seleccione…" required data-filtra="bus_id"
                 :opciones="$lineas->mapWithKeys(fn ($l) => [$l->id => $l->codigo.' · '.$l->nombre])" />

        {{-- Los buses se agrupan por línea; el script de la página deja ver solo los de la línea elegida. --}}
        <div>
            <label for="campo_bus_id" class="etiqueta">Bus disponible</label>
            <select id="campo_bus_id" name="bus_id" class="campo" required>
                <option value="">Seleccione…</option>
                @foreach ($buses->groupBy('linea_id') as $lineaId => $grupo)
                    <optgroup label="{{ $grupo->first()->linea->codigo }}" data-grupo="{{ $lineaId }}">
                        @foreach ($grupo as $bus)
                            <option value="{{ $bus->id }}" @selected(old('bus_id') == $bus->id)>{{ $bus->codigo }} · {{ $bus->placa }} · {{ $bus->capacidad_maxima }} pasajeros</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-500">Solo aparecen buses disponibles, asignados a una línea y sin recorrido en curso.</p>
            @error('bus_id') <p class="campo-error">{{ $message }}</p> @enderror
        </div>

        <x-campo nombre="piloto_id" etiqueta="Piloto" tipo="select" vacio="Seleccione…" required
                 :opciones="$pilotos->mapWithKeys(fn ($p) => [$p->id => $p->nombre.' · licencia '.$p->licencia])"
                 ayuda="Solo pilotos activos que no estén conduciendo otro recorrido." />

        <div class="flex justify-end border-t border-celeste-50 pt-5">
            <button class="btn-primario"><x-icono nombre="recorridos" class="size-4" /> Iniciar recorrido</button>
        </div>
    </form>
</x-layouts.app>
