@php($rapidos = config('transmetro.recarga.montos_rapidos'))
@php($minimo = config('transmetro.recarga.minimo'))
@php($maximo = config('transmetro.recarga.maximo'))

<x-layouts.app :titulo="'Recargar '.$tarjeta->codigo" :subtitulo="$tarjeta->pasajero->nombre.' · saldo actual Q'.number_format((float) $tarjeta->saldo, 2)">
    <x-slot:acciones>
        <a href="{{ route('tarjetas.show', $tarjeta) }}" class="btn-secundario"><x-icono nombre="atras" class="size-4" /> Tarjeta</a>
    </x-slot:acciones>

    @unless ($tarjeta->activa())
        <p class="mb-5 max-w-3xl rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700 ring-1 ring-rose-200">
            La tarjeta está {{ $tarjeta->estado }}: la recarga será rechazada hasta que se desbloquee.
        </p>
    @endunless

    {{-- Pestañas: efectivo (solo el operador en su ventanilla) y en línea --}}
    <nav class="mb-5 flex max-w-3xl gap-2" aria-label="Forma de pago">
        @if ($puedeEfectivo)
            <a href="{{ route('recargas.create', ['tarjeta' => $tarjeta, 'metodo' => 'efectivo']) }}"
               @class(['btn', 'bg-celeste-500 text-white' => $metodo === 'efectivo', 'btn-secundario' => $metodo !== 'efectivo'])
               @if ($metodo === 'efectivo') aria-current="page" @endif>En efectivo</a>
        @endif
        <a href="{{ route('recargas.create', ['tarjeta' => $tarjeta, 'metodo' => 'tarjeta']) }}"
           @class(['btn', 'bg-celeste-500 text-white' => $metodo === 'tarjeta' || ! $puedeEfectivo, 'btn-secundario' => $metodo !== 'tarjeta' && $puedeEfectivo])
           @if ($metodo === 'tarjeta' || ! $puedeEfectivo) aria-current="page" @endif>En línea con tarjeta</a>
    </nav>

    @if ($metodo === 'efectivo' && $puedeEfectivo)
        <form method="POST" action="{{ route('recargas.efectivo', $tarjeta) }}" class="tarjeta max-w-3xl space-y-5 p-6" data-recarga
              data-confirmar-tipo="normal" data-confirmar-titulo="¿Recargar en efectivo?" data-confirmar="Confirme el monto recibido en ventanilla."
              data-confirmar-boton="Sí, recargar" data-tarjeta="{{ $tarjeta->codigo }}">
            @csrf
            <p class="text-sm text-slate-600">Recarga en la ventanilla de <strong>{{ auth()->user()->estacion->nombre }}</strong>, registrada por {{ auth()->user()->name }}.</p>
            @include('recargas._monto')
            <div class="flex justify-end border-t border-celeste-50 pt-5">
                <button class="btn-primario">Registrar recarga</button>
            </div>
        </form>
    @else
        {{-- Pasarela SIMULADA: el número se usa solo para decidir el resultado; se guardan marca, últimos 4 y referencia. --}}
        <div class="grid max-w-5xl gap-6 lg:grid-cols-5">
            <form method="POST" action="{{ route('recargas.en-linea', $tarjeta) }}" class="tarjeta space-y-5 p-6 lg:col-span-3" data-recarga data-pasarela novalidate>
                @csrf
                <input type="hidden" name="llave" value="{{ $llave }}">
                @include('recargas._monto')

                <fieldset class="space-y-4 rounded-2xl bg-gradient-to-br from-slate-800 to-slate-900 p-5 text-white shadow-lg">
                    <legend class="sr-only">Datos de la tarjeta</legend>
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold tracking-wide text-slate-300">Pago con tarjeta</p>
                        <span data-marca class="rounded-md bg-white/10 px-2 py-1 text-xs font-bold tracking-wider" aria-live="polite">TARJETA</span>
                    </div>
                    <div>
                        <label for="campo_numero" class="mb-1 block text-xs text-slate-300">Número de tarjeta</label>
                        <input id="campo_numero" name="numero" inputmode="numeric" autocomplete="cc-number" maxlength="23" required
                               placeholder="4242 4242 4242 4242" data-numero
                               class="block w-full rounded-xl border-0 bg-white/10 px-3 py-2.5 font-mono text-lg tracking-widest text-white placeholder:text-slate-500 focus:ring-2 focus:ring-celeste-300 focus:outline-none">
                        <p data-error-numero class="mt-1 text-xs text-rose-300">@error('numero'){{ $message }}@enderror</p>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="campo_vencimiento" class="mb-1 block text-xs text-slate-300">Vencimiento</label>
                            <input id="campo_vencimiento" name="vencimiento" inputmode="numeric" autocomplete="cc-exp" maxlength="5" required placeholder="MM/AA" data-vencimiento
                                   class="block w-full rounded-xl border-0 bg-white/10 px-3 py-2.5 font-mono text-white placeholder:text-slate-500 focus:ring-2 focus:ring-celeste-300 focus:outline-none">
                            <p class="mt-1 text-xs text-rose-300">@error('vencimiento'){{ $message }}@enderror</p>
                        </div>
                        <div>
                            <label for="campo_cvv" class="mb-1 block text-xs text-slate-300">CVV</label>
                            <input id="campo_cvv" name="cvv" type="password" inputmode="numeric" autocomplete="cc-csc" maxlength="4" required placeholder="•••" data-cvv
                                   class="block w-full rounded-xl border-0 bg-white/10 px-3 py-2.5 font-mono text-white placeholder:text-slate-500 focus:ring-2 focus:ring-celeste-300 focus:outline-none">
                            <p class="mt-1 text-xs text-rose-300">@error('cvv'){{ $message }}@enderror</p>
                        </div>
                    </div>
                    <div>
                        <label for="campo_nombre" class="mb-1 block text-xs text-slate-300">Nombre en la tarjeta</label>
                        <input id="campo_nombre" name="nombre" autocomplete="cc-name" maxlength="80" required value="{{ old('nombre') }}" placeholder="COMO APARECE EN LA TARJETA"
                               class="block w-full rounded-xl border-0 bg-white/10 px-3 py-2.5 text-white uppercase placeholder:text-slate-500 focus:ring-2 focus:ring-celeste-300 focus:outline-none">
                        <p class="mt-1 text-xs text-rose-300">@error('nombre'){{ $message }}@enderror</p>
                    </div>
                </fieldset>

                <div class="flex items-center justify-between gap-3 border-t border-celeste-50 pt-5">
                    <p class="text-xs text-slate-500">No se guarda el número completo ni el CVV.</p>
                    <button class="btn-primario" data-pagar>Pagar y recargar</button>
                </div>
            </form>

            <aside class="h-fit rounded-2xl border-2 border-dashed border-amber-300 bg-amber-50 p-5 text-sm lg:col-span-2">
                <p class="font-semibold text-amber-900">Modo prueba</p>
                <p class="mt-1 text-amber-800">Pasarela simulada: no se cobra nada. Use cualquier vencimiento futuro y CVV.</p>
                <ul class="mt-3 space-y-2 font-mono text-xs">
                    <li><button type="button" data-tarjeta-prueba="4242424242424242" class="rounded px-1 hover:bg-amber-100">4242 4242 4242 4242</button> → aprobada</li>
                    <li><button type="button" data-tarjeta-prueba="4000000000000002" class="rounded px-1 hover:bg-amber-100">4000 0000 0000 0002</button> → Fondos insuficientes</li>
                    <li><button type="button" data-tarjeta-prueba="4000000000000069" class="rounded px-1 hover:bg-amber-100">4000 0000 0000 0069</button> → Tarjeta vencida</li>
                </ul>
                <p class="mt-3 text-xs text-amber-800">Cualquier otro número que pase la validación de Luhn se aprueba.</p>
            </aside>
        </div>

        {{-- "Procesando pago..." mientras la pasarela simulada responde --}}
        <div data-procesando hidden class="fixed inset-0 z-[70] flex items-center justify-center bg-celeste-900/50 backdrop-blur-sm" role="status" aria-live="assertive">
            <div class="rounded-2xl bg-white px-10 py-8 text-center shadow-2xl">
                <span class="mx-auto block size-12 animate-spin rounded-full border-4 border-celeste-200 border-t-celeste-500 motion-reduce:animate-none"></span>
                <p class="mt-4 font-semibold text-slate-800">Procesando pago…</p>
                <p class="text-sm text-slate-500">No cierre ni recargue la página.</p>
            </div>
        </div>
    @endif
</x-layouts.app>
