{{-- Enlace solo si el usuario puede abrir el destino; si no, el mismo texto sin enlace. --}}
@props(['url' => null])

@if ($url)
    <a href="{{ $url }}" {{ $attributes }}>{{ $slot }}</a>
@else
    <span {{ $attributes->only('class')->merge(['class' => '']) }}>{{ $slot }}</span>
@endif
