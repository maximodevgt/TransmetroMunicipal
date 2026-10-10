<x-layouts.app :titulo="$linea->exists ? 'Editar línea '.$linea->codigo : 'Nueva línea'">
    <x-slot:acciones>
        <a href="{{ $linea->exists ? route('lineas.show', $linea) : route('lineas.index') }}" class="btn-secundario"><x-icono nombre="atras" class="size-4" /> Volver</a>
    </x-slot:acciones>

    <form method="POST" action="{{ $linea->exists ? route('lineas.update', $linea) : route('lineas.store') }}" class="tarjeta max-w-2xl space-y-5 p-6">
        @csrf
        @if ($linea->exists) @method('PUT') @endif

        <div class="grid gap-5 sm:grid-cols-3">
            <x-campo nombre="codigo" etiqueta="Código" :valor="$linea->codigo" required maxlength="10" placeholder="L12" />
            <x-campo nombre="nombre" etiqueta="Nombre" :valor="$linea->nombre" required maxlength="100" class="sm:col-span-2" placeholder="Línea 12 - Eje Sur" />
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <x-campo nombre="municipalidad_id" etiqueta="Municipalidad" tipo="select" :valor="$linea->municipalidad_id"
                     :opciones="$municipalidades->pluck('nombre', 'id')" vacio="Seleccione…" required />
            <x-campo nombre="estado" etiqueta="Estado" tipo="select" :valor="$linea->estado"
                     :opciones="['activa' => 'Activa', 'inactiva' => 'Inactiva']" required />
        </div>

        <div class="flex justify-end gap-2 border-t border-celeste-50 pt-5">
            <button class="btn-primario">{{ $linea->exists ? 'Guardar cambios' : 'Crear línea' }}</button>
        </div>
    </form>
</x-layouts.app>
