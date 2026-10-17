<x-layouts.app titulo="Cobro de pasaje" subtitulo="Simula el paso del pasajero por el torniquete de la estación">
    <div class="space-y-6">
        @forelse ($accesos as $estacion => $grupo)
            <section>
                <h2 class="mb-2 font-semibold text-celeste-900">{{ $estacion }}</h2>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($grupo as $acceso)
                        <a href="{{ route('torniquete.show', ['acceso' => $acceso, 'codigo' => $codigo]) }}" class="tarjeta clicable flex items-center gap-3 p-4">
                            <x-icono nombre="torniquete" class="size-6 text-celeste-500" />
                            <span class="font-medium">{{ $acceso->nombre }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @empty
            <p class="text-sm text-slate-500">No hay accesos disponibles para su usuario.</p>
        @endforelse
    </div>
</x-layouts.app>
