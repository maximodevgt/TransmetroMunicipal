<x-layouts.app titulo="Emitir tarjeta" subtitulo="El código TM-000NNN se genera automáticamente y la tarjeta nace con saldo Q0.00">
    <x-slot:acciones>
        <a href="{{ route('tarjetas.index') }}" class="btn-secundario"><x-icono nombre="atras" class="size-4" /> Volver</a>
    </x-slot:acciones>

    <form method="POST" action="{{ route('tarjetas.store') }}" class="tarjeta max-w-2xl space-y-5 p-6">
        @csrf
        <x-campo nombre="pasajero_id" etiqueta="Pasajero registrado" tipo="select" vacio="— Nuevo pasajero —"
                 :opciones="$pasajeros->pluck('nombre', 'id')" ayuda="Elija un pasajero o deje «Nuevo pasajero» y llene sus datos." />

        <fieldset class="space-y-4 rounded-xl bg-celeste-50 p-4">
            <legend class="px-1 text-sm font-semibold text-celeste-800">Nuevo pasajero</legend>
            <x-campo nombre="nombre" etiqueta="Nombre completo" maxlength="120" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-campo nombre="dpi" etiqueta="DPI" inputmode="numeric" pattern="\d{13}" maxlength="13" placeholder="13 dígitos" />
                <x-campo nombre="telefono" etiqueta="Teléfono" inputmode="tel" pattern="\d{8,15}" maxlength="15" placeholder="55001234" />
            </div>
        </fieldset>

        <div class="flex justify-end border-t border-celeste-50 pt-5">
            <button class="btn-primario"><x-icono nombre="tarjeta" class="size-4" /> Emitir tarjeta</button>
        </div>
    </form>
</x-layouts.app>
