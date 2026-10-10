<x-layouts.app :titulo="$estacion->exists ? 'Editar estación' : 'Nueva estación'" :subtitulo="$estacion->exists ? $estacion->nombre : 'Las líneas se asignan desde el recorrido de cada línea'">
    <x-slot:acciones>
        <a href="{{ $estacion->exists ? route('estaciones.show', $estacion) : route('estaciones.index') }}" class="btn-secundario"><x-icono nombre="atras" class="size-4" /> Volver</a>
    </x-slot:acciones>

    <form method="POST" action="{{ $estacion->exists ? route('estaciones.update', $estacion) : route('estaciones.store') }}" class="tarjeta max-w-2xl space-y-5 p-6">
        @csrf
        @if ($estacion->exists) @method('PUT') @endif

        <x-campo nombre="nombre" etiqueta="Nombre" :valor="$estacion->nombre" required maxlength="100" />
        <x-campo nombre="direccion" etiqueta="Dirección" :valor="$estacion->direccion" maxlength="200" placeholder="Zona 1, Ciudad de Guatemala" />
        <div class="grid gap-5 sm:grid-cols-2">
            <x-campo nombre="municipalidad_id" etiqueta="Municipalidad" tipo="select" :valor="$estacion->municipalidad_id"
                     :opciones="$municipalidades->pluck('nombre', 'id')" vacio="Seleccione…" required />
            <x-campo nombre="estado" etiqueta="Estado" tipo="select" :valor="$estacion->estado"
                     :opciones="['activa' => 'Activa', 'inactiva' => 'Inactiva']" required />
        </div>

        <div class="flex justify-end border-t border-celeste-50 pt-5">
            <button class="btn-primario">{{ $estacion->exists ? 'Guardar cambios' : 'Crear estación' }}</button>
        </div>
    </form>
</x-layouts.app>
