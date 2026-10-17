<x-layouts.app titulo="Panel" :subtitulo="'Hola, '.auth()->user()->name">
    {{-- Indicadores --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ([
            ['Líneas', $totales['lineas'], 'lineas', 'lineas.index'],
            ['Estaciones', $totales['estaciones'], 'estaciones', 'estaciones.index'],
            ['Buses', $totales['buses'], 'buses', 'buses.index'],
            ['Pilotos activos', $totales['pilotos'], 'pilotos', auth()->user()->esAdministrador() ? 'pilotos.index' : null],
        ] as [$texto, $valor, $icono, $ruta])
            <div class="tarjeta flex items-center gap-4 p-5">
                <div class="flex size-12 items-center justify-center rounded-2xl bg-gradient-to-br from-celeste-300 to-celeste-500 text-white shadow-sm">
                    <x-icono :nombre="$icono" class="size-6" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-celeste-900">{{ $valor }}</p>
                    <p class="text-sm text-slate-500">
                        @if ($ruta)
                            <a href="{{ route($ruta) }}" class="hover:text-celeste-600">{{ $texto }}</a>
                        @else
                            {{ $texto }}
                        @endif
                    </p>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        {{-- Resumen por linea (vista vw_resumen_lineas) --}}
        <section class="tarjeta overflow-hidden lg:col-span-2">
            <div class="flex items-center justify-between px-5 py-4">
                <h2 class="font-semibold text-celeste-900">Resumen por línea</h2>
                <a href="{{ route('lineas.index') }}" class="text-sm font-medium text-celeste-600 hover:underline">Ver líneas</a>
            </div>
            <div class="overflow-x-auto">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Línea</th>
                            <th class="text-right">Estaciones</th>
                            <th class="text-right">Distancia</th>
                            <th>Buses asignados</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($resumenLineas as $r)
                            @php($pct = $r->buses_maximos > 0 ? min(100, round($r->buses_asignados / $r->buses_maximos * 100)) : 0)
                            <tr>
                                <td>
                                    <a href="{{ route('lineas.show', $r->id) }}" class="font-semibold text-celeste-700 hover:underline">{{ $r->codigo }}</a>
                                    <span class="block text-xs text-slate-500">{{ $r->nombre }}</span>
                                </td>
                                <td class="text-right">{{ $r->total_estaciones }}</td>
                                <td class="text-right">{{ number_format($r->distancia_total_km, 2) }} km</td>
                                <td class="min-w-48">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="font-semibold">{{ $r->buses_asignados }}</span>
                                        <span class="text-slate-500">mín. {{ $r->buses_minimos }} · máx. {{ $r->buses_maximos }}</span>
                                    </div>
                                    <div class="mt-1 h-2 rounded-full bg-celeste-100">
                                        <div @class(['h-2 rounded-full', 'bg-celeste-500' => $r->buses_asignados >= $r->buses_minimos, 'bg-amber-400' => $r->buses_asignados < $r->buses_minimos]) style="width: {{ $pct }}%"></div>
                                    </div>
                                    @if ($r->buses_asignados < $r->buses_minimos)
                                        <span class="text-xs text-amber-600">Faltan {{ $r->buses_minimos - $r->buses_asignados }} buses para el mínimo</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Estado de la flota --}}
        <section class="tarjeta p-5">
            <h2 class="font-semibold text-celeste-900">Estado de la flota</h2>
            <ul class="mt-4 space-y-3">
                @foreach (\App\Models\Bus::ESTADOS as $estado)
                    <li class="flex items-center justify-between">
                        <x-estado :valor="$estado" />
                        <a href="{{ route('buses.index', ['estado' => $estado]) }}" class="font-semibold text-celeste-800 hover:underline">{{ $busesPorEstado[$estado] ?? 0 }}</a>
                    </li>
                @endforeach
            </ul>

            @if ($estacion)
                <div class="mt-6 rounded-xl bg-celeste-50 p-4">
                    <p class="text-xs font-semibold tracking-wide text-celeste-700 uppercase">Mi estación</p>
                    <a href="{{ route('estaciones.show', $estacion) }}" class="mt-1 block font-semibold text-celeste-900 hover:underline">{{ $estacion->nombre }}</a>
                    <p class="text-sm text-slate-500">{{ $estacion->lineas->pluck('codigo')->join(', ') ?: 'Sin línea' }} · {{ $estacion->accesos->count() }} accesos</p>
                </div>
            @endif
        </section>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        {{-- Alertas activas --}}
        <section class="tarjeta p-5">
            <h2 class="flex items-center gap-2 font-semibold text-celeste-900">
                <x-icono nombre="alerta" class="text-celeste-500" /> Alertas activas
            </h2>
            <ul class="mt-4 space-y-3">
                @forelse ($alertas as $alerta)
                    <li id="alerta-{{ $alerta->id }}" class="scroll-mt-24 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm target:ring-2 target:ring-amber-400">
                        <p class="font-medium text-amber-800">{{ $alerta->tipoTexto() }}</p>
                        <p class="text-amber-700">{{ $alerta->mensaje }}</p>
                        <p class="mt-1 text-xs text-amber-600">{{ $alerta->created_at?->format('d/m/Y H:i') }}</p>
                    </li>
                @empty
                    <li class="text-sm text-slate-500">No hay alertas activas.</li>
                @endforelse
            </ul>
        </section>

        {{-- Bitacora (solo administrador) --}}
        @if (auth()->user()->esAdministrador())
            <section class="tarjeta p-5">
                <h2 class="font-semibold text-celeste-900">Actividad reciente</h2>
                <ul class="mt-4 divide-y divide-celeste-50">
                    @forelse ($bitacora as $b)
                        <li class="py-2.5 text-sm">
                            <p class="text-slate-700">{{ $b->detalle }}</p>
                            <p class="text-xs text-slate-400">{{ $b->user?->name }} · {{ $b->created_at?->format('d/m/Y H:i') }}</p>
                        </li>
                    @empty
                        <li class="text-sm text-slate-500">Sin actividad registrada.</li>
                    @endforelse
                </ul>
            </section>
        @endif
    </div>
</x-layouts.app>
