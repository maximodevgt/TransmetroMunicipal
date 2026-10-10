@props([
    'nombre',
    'etiqueta',
    'tipo' => 'text',
    'valor' => null,
    'opciones' => null, // [valor => texto] para tipo "select"
    'vacio' => null,    // texto de la opcion vacia en un select
    'ayuda' => null,
])

@php
    $actual = old($nombre, $valor);
    $id = 'campo_'.$nombre;
@endphp

<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="etiqueta">{{ $etiqueta }}</label>

    @if ($tipo === 'select')
        <select id="{{ $id }}" name="{{ $nombre }}" {{ $attributes->except('class')->merge(['class' => 'campo']) }}>
            @if ($vacio !== null)
                <option value="">{{ $vacio }}</option>
            @endif
            @foreach ($opciones as $clave => $texto)
                <option value="{{ $clave }}" @selected((string) $actual === (string) $clave)>{{ $texto }}</option>
            @endforeach
        </select>
    @else
        <input id="{{ $id }}" type="{{ $tipo }}" name="{{ $nombre }}"
               @if ($tipo !== 'password') value="{{ $actual }}" @endif
               {{ $attributes->except('class')->merge(['class' => 'campo']) }}>
    @endif

    @if ($ayuda)
        <p class="mt-1 text-xs text-slate-500">{{ $ayuda }}</p>
    @endif
    @error($nombre)
        <p class="campo-error">{{ $message }}</p>
    @enderror
</div>
