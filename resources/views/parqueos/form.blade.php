<x-layouts.app :titulo="$parqueo->exists ? 'Editar parqueo' : 'Nuevo parqueo'" :subtitulo="$parqueo->exists ? $parqueo->nombre : 'Predio donde se guardan los buses'">
    <x-slot:acciones>
        <a href="{{ $parqueo->exists ? route('parqueos.show', $parqueo) : route('parqueos.index') }}" class="btn-secundario"><x-icono nombre="atras" class="size-4" /> Volver</a>
    </x-slot:acciones>

    <form method="POST" action="{{ $parqueo->exists ? route('parqueos.update', $parqueo) : route('parqueos.store') }}" class="tarjeta max-w-2xl space-y-5 p-6">
        @csrf
        @if ($parqueo->exists) @method('PUT') @endif

        <x-campo nombre="nombre" etiqueta="Nombre" :valor="$parqueo->nombre" required maxlength="100" placeholder="Predio Centra Sur" />
        <x-campo nombre="direccion" etiqueta="Dirección" :valor="$parqueo->direccion" maxlength="200" placeholder="Zona 12, Villa Nueva" />
        <x-campo nombre="capacidad" etiqueta="Capacidad (buses)" tipo="number" :valor="$parqueo->capacidad" required
                 :min="max(1, $parqueo->buses_count ?? 0)" max="1000"
                 :ayuda="$parqueo->exists ? 'Actualmente tiene '.$parqueo->buses_count.' buses; la capacidad no puede ser menor.' : null" />

        <div class="flex justify-end border-t border-celeste-50 pt-5">
            <button class="btn-primario">{{ $parqueo->exists ? 'Guardar cambios' : 'Registrar parqueo' }}</button>
        </div>
    </form>
</x-layouts.app>
