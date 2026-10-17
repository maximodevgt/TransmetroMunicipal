{{-- Aviso de la pantalla destino de "Resolver": qué alerta se resuelve y enlace para volver a ella. --}}
@props(['alerta'])

<div {{ $attributes->merge(['class' => 'mb-5 flex flex-wrap items-start justify-between gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3']) }} data-resolviendo="{{ $alerta->id }}">
    <div class="flex items-start gap-3">
        <x-icono nombre="advertencia" class="mt-0.5 text-amber-600" />
        <div class="text-sm">
            <p class="font-semibold text-amber-900">Resolviendo alerta #{{ $alerta->id }} · {{ $alerta->tipoTexto() }}</p>
            <p class="text-amber-800">{{ $slot->isNotEmpty() ? $slot : $alerta->mensaje }}</p>
        </div>
    </div>
    <a href="{{ route('alertas.show', $alerta) }}" class="btn-secundario px-3 py-1.5 text-xs"><x-icono nombre="atras" class="size-4" /> Volver a la alerta</a>
</div>
