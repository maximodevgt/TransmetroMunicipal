<x-layouts.app :titulo="$guardia->exists ? 'Editar guardia' : 'Nuevo guardia'" :subtitulo="$acceso->nombre.' · Estación '.$acceso->estacion->nombre">
    <x-slot:acciones>
        <a href="{{ route('estaciones.show', $acceso->estacion) }}" class="btn-secundario"><x-icono nombre="atras" class="size-4" /> Volver</a>
    </x-slot:acciones>

    <form method="POST" action="{{ $guardia->exists ? route('guardias.update', $guardia) : route('accesos.guardias.store', $acceso) }}" class="tarjeta max-w-2xl space-y-5 p-6">
        @csrf
        @if ($guardia->exists) @method('PUT') @endif

        <x-campo nombre="nombre" etiqueta="Nombre completo" :valor="$guardia->nombre" required maxlength="120" />
        <div class="grid gap-5 sm:grid-cols-3">
            <x-campo nombre="dpi" etiqueta="DPI" :valor="$guardia->dpi" required inputmode="numeric" pattern="\d{13}" maxlength="13" placeholder="13 dígitos" />
            <x-campo nombre="telefono" etiqueta="Teléfono" :valor="$guardia->telefono" inputmode="tel" pattern="\d{8,15}" maxlength="15" placeholder="55001234" />
            <x-campo nombre="turno" etiqueta="Turno" tipo="select" :valor="$guardia->turno" :opciones="\App\Models\Guardia::TURNOS" required />
        </div>

        <div class="flex justify-end border-t border-celeste-50 pt-5">
            <button class="btn-primario">{{ $guardia->exists ? 'Guardar cambios' : 'Agregar guardia' }}</button>
        </div>
    </form>
</x-layouts.app>
