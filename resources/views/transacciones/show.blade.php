@php
    $t = $transaccion;
    $lugar = $t->acceso ? $t->acceso->estacion->nombre.' · '.$t->acceso->nombre : ($t->estacion?->nombre ?? 'En línea');
@endphp

<x-layouts.app :titulo="$t->tipo === 'recarga' ? 'Comprobante de recarga' : 'Constancia de pasaje'" :subtitulo="$t->numeroComprobante()">
    <x-slot:acciones>
        <a href="{{ route('tarjetas.show', $t->tarjeta) }}" class="btn-secundario print:hidden"><x-icono nombre="atras" class="size-4" /> Tarjeta {{ $t->tarjeta->codigo }}</a>
        <button type="button" onclick="window.print()" class="btn-primario print:hidden"><x-icono nombre="imprimir" class="size-4" /> Imprimir</button>
    </x-slot:acciones>

    {{-- Comprobante imprimible: al imprimir se ocultan el menú y las barras --}}
    <article class="tarjeta mx-auto max-w-md overflow-hidden print:shadow-none print:ring-0" data-comprobante>
        <header @class(['px-6 py-5 text-white', 'bg-gradient-to-r from-celeste-500 to-celeste-600' => $t->aprobada(), 'bg-gradient-to-r from-rose-500 to-rose-600' => ! $t->aprobada()])>
            <p class="text-xs tracking-widest uppercase opacity-90">Transmetro · Municipalidad de Guatemala</p>
            <p class="mt-1 text-lg font-bold">{{ $t->descripcion() }} {{ $t->aprobada() ? 'aprobada' : 'rechazada' }}</p>
            <p class="text-sm opacity-90">Comprobante {{ $t->numeroComprobante() }}</p>
        </header>

        <dl class="divide-y divide-dashed divide-slate-200 px-6 text-sm">
            <div class="flex justify-between py-2.5"><dt class="text-slate-500">Fecha</dt><dd class="font-medium">{{ $t->created_at?->format('d/m/Y H:i:s') }}</dd></div>
            <div class="flex justify-between py-2.5"><dt class="text-slate-500">Tarjeta</dt><dd class="font-medium tabular-nums">{{ $t->tarjeta->codigo }}</dd></div>
            <div class="flex justify-between py-2.5"><dt class="text-slate-500">Estación</dt><dd class="text-right font-medium">{{ $lugar }}</dd></div>
            <div class="flex justify-between py-2.5"><dt class="text-slate-500">Operador</dt><dd class="text-right font-medium">{{ $t->user?->name ?? '—' }}</dd></div>
            @if ($t->medioDePago())
                <div class="flex justify-between py-2.5"><dt class="text-slate-500">Medio de pago</dt><dd class="text-right font-medium">{{ $t->medioDePago() }}</dd></div>
                <div class="flex justify-between py-2.5"><dt class="text-slate-500">Referencia</dt><dd class="font-mono text-xs">{{ $t->referencia }}</dd></div>
            @elseif ($t->metodo === 'efectivo')
                <div class="flex justify-between py-2.5"><dt class="text-slate-500">Medio de pago</dt><dd class="font-medium">Efectivo en ventanilla</dd></div>
            @endif
            <div class="flex justify-between py-3"><dt class="text-slate-500">Monto</dt><dd class="text-xl font-bold tabular-nums">Q{{ number_format((float) $t->monto, 2) }}</dd></div>
            <div class="flex justify-between py-3">
                <dt class="text-slate-500">Saldo resultante</dt>
                <dd class="text-xl font-bold text-celeste-800 tabular-nums" data-saldo-resultante>Q{{ number_format((float) $t->saldo_resultante, 2) }}</dd>
            </div>
            @unless ($t->aprobada())
                <div class="py-3 text-rose-700"><dt class="font-semibold">Motivo del rechazo</dt><dd>{{ $t->motivo_rechazo }}</dd></div>
            @endunless
        </dl>

        <footer class="bg-slate-50 px-6 py-4 text-center text-xs text-slate-500">
            Pago simulado con fines de demostración. {{ $t->aprobada() ? 'Conserve este comprobante.' : 'No se realizó ningún cargo.' }}
        </footer>
    </article>
</x-layouts.app>
