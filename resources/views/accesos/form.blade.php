<x-layouts.app :titulo="$acceso->exists ? 'Editar acceso' : 'Nuevo acceso'" :subtitulo="'Estación '.$estacion->nombre">
    <x-slot:acciones>
        <a href="{{ route('estaciones.show', $estacion) }}" class="btn-secundario"><x-icono nombre="atras" class="size-4" /> Volver</a>
    </x-slot:acciones>

    <form method="POST" action="{{ $acceso->exists ? route('accesos.update', $acceso) : route('estaciones.accesos.store', $estacion) }}" class="tarjeta max-w-2xl space-y-5 p-6">
        @csrf
        @if ($acceso->exists) @method('PUT') @endif

        <x-campo nombre="nombre" etiqueta="Nombre del acceso" :valor="$acceso->nombre" required maxlength="100" placeholder="Acceso Norte" />

        @if ($guardia)
            {{-- RN-11: todo acceso nace con al menos un guardia --}}
            <fieldset class="space-y-5 rounded-xl bg-celeste-50 p-4">
                <legend class="px-1 text-sm font-semibold text-celeste-800">Primer guardia del acceso</legend>
                <p class="-mt-2 text-xs text-slate-500">Todo acceso debe tener al menos un guardia de seguridad.</p>
                <x-campo nombre="guardia_nombre" etiqueta="Nombre completo" :valor="$guardia->nombre" required maxlength="120" />
                <div class="grid gap-5 sm:grid-cols-3">
                    <x-campo nombre="guardia_dpi" etiqueta="DPI" :valor="$guardia->dpi" required inputmode="numeric" pattern="\d{13}" maxlength="13"
                             placeholder="13 dígitos" />
                    <x-campo nombre="guardia_telefono" etiqueta="Teléfono" :valor="$guardia->telefono" inputmode="tel" pattern="\d{8,15}" maxlength="15"
                             placeholder="55001234" />
                    <x-campo nombre="guardia_turno" etiqueta="Turno" tipo="select" :valor="$guardia->turno" :opciones="\App\Models\Guardia::TURNOS" required />
                </div>
            </fieldset>
        @endif

        <div class="flex justify-end border-t border-celeste-50 pt-5">
            <button class="btn-primario">{{ $acceso->exists ? 'Guardar cambios' : 'Crear acceso' }}</button>
        </div>
    </form>
</x-layouts.app>
