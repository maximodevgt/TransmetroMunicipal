@props(['accion', 'confirmar' => '¿Seguro que desea eliminar este registro?', 'texto' => 'Eliminar'])

<form method="POST" action="{{ $accion }}" data-confirmar="{{ $confirmar }}" {{ $attributes->only('class') }}>
    @csrf
    @method('DELETE')
    <button class="btn-peligro">
        <x-icono nombre="eliminar" class="size-4" /> {{ $texto }}
    </button>
</form>
