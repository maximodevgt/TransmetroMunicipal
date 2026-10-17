<x-layouts.app titulo="Cobro de pasaje" :subtitulo="$acceso->estacion->nombre.' · '.$acceso->nombre.' · pasaje Q'.number_format($pasaje, 2)">
    <x-slot:acciones>
        <a href="{{ route('torniquete.index') }}" class="btn-secundario"><x-icono nombre="atras" class="size-4" /> Accesos</a>
    </x-slot:acciones>

    <div class="mx-auto max-w-2xl space-y-6" data-torniquete>
        <form method="POST" action="{{ route('torniquete.validar', $acceso) }}" class="tarjeta flex flex-col gap-3 p-6 sm:flex-row sm:items-end">
            @csrf
            <div class="flex-1">
                <label for="codigo" class="etiqueta">Código de la tarjeta</label>
                <input id="codigo" name="codigo" value="{{ $codigo }}" autofocus autocomplete="off" required maxlength="20" placeholder="TM-000001"
                       class="campo py-3 font-mono text-xl tracking-wider uppercase">
            </div>
            <button class="btn-primario py-3 text-base"><x-icono nombre="torniquete" class="size-5" /> Pasar tarjeta</button>
        </form>

        {{-- Respuesta del torniquete: verde "Bienvenido" o rojo con el motivo --}}
        <div data-resultado hidden role="status" aria-live="assertive">
            <p data-resultado-icono class="text-7xl leading-none font-black" aria-hidden="true"></p>
            <p data-resultado-titulo class="mt-3 text-4xl font-bold"></p>
            <p data-resultado-mensaje class="mt-2 text-xl opacity-95"></p>
        </div>

        <div class="tarjeta p-5">
            <p class="text-sm font-semibold text-slate-700">Pasar una tarjeta de ejemplo</p>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach ($ejemplos as $ejemplo)
                    <button type="button" data-pasar="{{ $ejemplo->codigo }}" class="btn-secundario font-mono text-xs">
                        {{ $ejemplo->codigo }} @unless ($ejemplo->estado === 'activa') <span class="text-rose-600">({{ $ejemplo->estado }})</span> @endunless
                    </button>
                @endforeach
                <button type="button" data-pasar="TM-999999" class="btn-secundario font-mono text-xs">TM-999999 <span class="text-slate-400">(no existe)</span></button>
            </div>
        </div>
    </div>
</x-layouts.app>
