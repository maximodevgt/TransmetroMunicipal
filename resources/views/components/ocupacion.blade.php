{{-- Barra de ocupación: celeste hasta 79 %, ámbar desde 80 % y roja al llenarse. --}}
@props(['usados', 'capacidad'])

@php
    $porcentaje = $capacidad > 0 ? (int) round($usados / $capacidad * 100) : 0;
    $color = match (true) {
        $porcentaje >= 100 => 'bg-rose-500',
        $porcentaje >= 80 => 'bg-amber-400',
        default => 'bg-celeste-500',
    };
@endphp

<div {{ $attributes->merge(['class' => 'min-w-40']) }}>
    <div class="flex items-center justify-between text-xs">
        <span class="font-semibold text-slate-700">{{ $usados }} / {{ $capacidad }}</span>
        <span class="text-slate-500">{{ $porcentaje }} %</span>
    </div>
    <div class="mt-1 h-2 rounded-full bg-celeste-100" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $capacidad }}" aria-valuenow="{{ $usados }}"
         aria-label="Ocupación: {{ $usados }} de {{ $capacidad }}">
        <div class="h-2 rounded-full {{ $color }}" style="width: {{ min(100, $porcentaje) }}%"></div>
    </div>
</div>
