<x-layouts.app titulo="Usuarios" subtitulo="Administrador municipal y operadores de estación">
    <x-slot:acciones>
        <a href="{{ route('usuarios.create') }}" class="btn-primario"><x-icono nombre="mas" class="size-4" /> Nuevo usuario</a>
    </x-slot:acciones>

    <section class="tarjeta overflow-hidden">
        <div class="flex flex-wrap gap-2 p-4">
            @foreach (['' => 'Todos', 'administrador' => 'Administradores', 'operador' => 'Operadores'] as $valor => $texto)
                <a href="{{ route('usuarios.index', array_filter(['rol' => $valor])) }}"
                   @class(['btn', 'bg-celeste-500 text-white' => (string) $rol === $valor, 'text-celeste-700 ring-1 ring-celeste-200 hover:bg-celeste-50' => (string) $rol !== $valor])>{{ $texto }}</a>
            @endforeach
        </div>

        <div class="overflow-x-auto">
            <table class="tabla">
                <thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Estación</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                    @forelse ($usuarios as $usuario)
                        <tr>
                            <td class="font-medium">{{ $usuario->name }}</td>
                            <td>{{ $usuario->email }}</td>
                            <td>
                                <span @class(['insignia', 'bg-celeste-500 text-white' => $usuario->esAdministrador(), 'bg-celeste-100 text-celeste-700' => ! $usuario->esAdministrador()])>{{ ucfirst($usuario->rol) }}</span>
                            </td>
                            <td>{{ $usuario->estacion?->nombre ?? '—' }}</td>
                            <td><x-estado :valor="$usuario->activo ? 'activo' : 'inactivo'" /></td>
                            <td class="text-right">
                                <a href="{{ route('usuarios.edit', $usuario) }}" class="inline-flex rounded-lg p-1.5 text-celeste-600 hover:bg-celeste-50" title="Editar" aria-label="Editar a {{ $usuario->name }}"><x-icono nombre="editar" class="size-4" /></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-slate-500">No hay usuarios con ese filtro.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $usuarios->links() }}</div>
    </section>
</x-layouts.app>
