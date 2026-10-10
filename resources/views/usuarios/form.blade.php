<x-layouts.app :titulo="$usuario->exists ? 'Editar usuario' : 'Nuevo usuario'" :subtitulo="$usuario->exists ? $usuario->email : 'Los operadores quedan asignados a una estación'">
    <x-slot:acciones>
        <a href="{{ route('usuarios.index') }}" class="btn-secundario"><x-icono nombre="atras" class="size-4" /> Volver</a>
    </x-slot:acciones>

    <form method="POST" action="{{ $usuario->exists ? route('usuarios.update', $usuario) : route('usuarios.store') }}" class="tarjeta max-w-2xl space-y-5 p-6">
        @csrf
        @if ($usuario->exists) @method('PUT') @endif

        <x-campo nombre="name" etiqueta="Nombre completo" :valor="$usuario->name" required maxlength="120" />
        <x-campo nombre="email" etiqueta="Correo" tipo="email" :valor="$usuario->email" required maxlength="150" />
        <div class="grid gap-5 sm:grid-cols-2">
            <x-campo nombre="rol" etiqueta="Rol" tipo="select" :valor="$usuario->rol" required
                     :opciones="['operador' => 'Operador', 'administrador' => 'Administrador']" />
            <x-campo nombre="estacion_id" etiqueta="Estación" tipo="select" :valor="$usuario->estacion_id" vacio="— (solo administrador)"
                     :opciones="$estaciones->pluck('nombre', 'id')" ayuda="Obligatoria para operadores." />
            <x-campo nombre="password" etiqueta="Contraseña" tipo="password" autocomplete="new-password" :required="! $usuario->exists"
                     :ayuda="$usuario->exists ? 'Déjela vacía para mantener la actual.' : 'Mínimo 8 caracteres.'" />
            <x-campo nombre="password_confirmation" etiqueta="Confirmar contraseña" tipo="password" autocomplete="new-password" />
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="hidden" name="activo" value="0">
            <input type="checkbox" name="activo" value="1" @checked(old('activo', $usuario->activo))
                   class="size-4 rounded border-celeste-300 text-celeste-500 focus:ring-celeste-400">
            Usuario activo (puede iniciar sesión)
        </label>

        <div class="flex justify-end border-t border-celeste-50 pt-5">
            <button class="btn-primario">{{ $usuario->exists ? 'Guardar cambios' : 'Crear usuario' }}</button>
        </div>
    </form>
</x-layouts.app>
