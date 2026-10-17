<x-layouts.app titulo="Reportes"
    :subtitulo="auth()->user()->esAdministrador() ? 'Consultas de todo el sistema, para ver, imprimir o exportar' : 'Consultas de la estación '.(auth()->user()->estacion?->nombre ?? 'asignada').', para ver, imprimir o exportar'">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($reportes as $reporte)
            <a href="{{ route('reportes.show', $reporte->clave()) }}" class="tarjeta clicable flex flex-col gap-3 p-5" data-reporte="{{ $reporte->clave() }}">
                <div class="flex size-11 items-center justify-center rounded-2xl bg-gradient-to-br from-celeste-300 to-celeste-500 text-white shadow-sm">
                    <x-icono :nombre="$reporte->icono()" class="size-6" />
                </div>
                <div>
                    <h2 class="font-semibold text-celeste-900">{{ $reporte->titulo() }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $reporte->descripcion() }}</p>
                </div>
            </a>
        @endforeach
    </div>
</x-layouts.app>
