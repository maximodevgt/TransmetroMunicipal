{{-- Saldo con color: rojo sin saldo para un pasaje, ámbar si alcanza para menos de 5 pasajes, verde si no. --}}
@props(['valor'])

@php
    $pasaje = (float) config('transmetro.pasaje');
    $color = match (true) {
        (float) $valor < $pasaje => 'bg-rose-100 text-rose-700',
        (float) $valor < $pasaje * 5 => 'bg-amber-100 text-amber-800',
        default => 'bg-emerald-100 text-emerald-700',
    };
@endphp

<span {{ $attributes->merge(['class' => "insignia tabular-nums {$color}"]) }}>Q{{ number_format((float) $valor, 2) }}</span>
