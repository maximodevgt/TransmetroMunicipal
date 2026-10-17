@props([
    'accion',
    'confirmar' => 'Esta acción no se puede deshacer.',
    'titulo' => '¿Eliminar registro?',
    'boton' => 'Sí, eliminar',
    'texto' => 'Eliminar',
])

<form method="POST" action="{{ $accion }}" data-confirmar="{{ $confirmar }}" data-confirmar-titulo="{{ $titulo }}" data-confirmar-boton="{{ $boton }}" {{ $attributes->only('class') }}>
    @csrf
    @method('DELETE')
    <button class="btn-peligro">
        <x-icono nombre="eliminar" class="size-4" /> {{ $texto }}
    </button>
</form>
