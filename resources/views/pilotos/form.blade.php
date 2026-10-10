<x-layouts.app :titulo="$piloto->exists ? 'Editar piloto' : 'Nuevo piloto'" :subtitulo="$piloto->exists ? $piloto->nombre : null">
    <x-slot:acciones>
        <a href="{{ $piloto->exists ? route('pilotos.show', $piloto) : route('pilotos.index') }}" class="btn-secundario"><x-icono nombre="atras" class="size-4" /> Volver</a>
    </x-slot:acciones>

    <form method="POST" action="{{ $piloto->exists ? route('pilotos.update', $piloto) : route('pilotos.store') }}" class="tarjeta max-w-3xl space-y-5 p-6">
        @csrf
        @if ($piloto->exists) @method('PUT') @endif

        <x-campo nombre="nombre" etiqueta="Nombre completo" :valor="$piloto->nombre" required maxlength="120" />
        <div class="grid gap-5 sm:grid-cols-2">
            <x-campo nombre="dpi" etiqueta="DPI" :valor="$piloto->dpi" required inputmode="numeric" maxlength="13" ayuda="13 dígitos, sin espacios." />
            <x-campo nombre="licencia" etiqueta="Licencia" :valor="$piloto->licencia" required maxlength="20" placeholder="A-250000" />
            <x-campo nombre="telefono" etiqueta="Teléfono" :valor="$piloto->telefono" required maxlength="15" />
            <x-campo nombre="email" etiqueta="Correo (opcional)" tipo="email" :valor="$piloto->email" maxlength="150" />
        </div>
        <x-campo nombre="residencia" etiqueta="Residencia" :valor="$piloto->residencia" required maxlength="200" placeholder="Zona 7, Guatemala" />
        <x-campo nombre="estado" etiqueta="Estado" tipo="select" :valor="$piloto->estado" class="max-w-xs"
                 :opciones="['activo' => 'Activo', 'inactivo' => 'Inactivo']" required />

        <div class="flex justify-end border-t border-celeste-50 pt-5">
            <button class="btn-primario">{{ $piloto->exists ? 'Guardar cambios' : 'Registrar piloto' }}</button>
        </div>
    </form>
</x-layouts.app>
