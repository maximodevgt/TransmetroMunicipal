@php($esAdmin = auth()->user()->esAdministrador())

@push('cabecera')
    @vite(['resources/js/seguimiento.js', 'resources/js/graficas.js'])
@endpush

<x-layouts.app titulo="Panel" :subtitulo="'Hola, '.auth()->user()->name">
    {{-- Indicadores: la tarjeta completa lleva al módulo (Pilotos solo para el administrador) --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ([
            ['Líneas', $totales['lineas'], 'lineas', 'lineas.index'],
            ['Estaciones', $totales['estaciones'], 'estaciones', 'estaciones.index'],
            ['Buses', $totales['buses'], 'buses', 'buses.index'],
            ['Pilotos activos', $totales['pilotos'], 'pilotos', $esAdmin ? 'pilotos.index' : null],
        ] as [$texto, $valor, $icono, $ruta])
            @php($etiqueta = $ruta ? 'a' : 'div')
            <{{ $etiqueta }} @if ($ruta) href="{{ route($ruta) }}" @endif
                @class(['tarjeta flex items-center gap-4 p-5', 'clicable' => $ruta])
                data-indicador="{{ $icono }}">
                <div class="flex size-12 items-center justify-center rounded-2xl bg-gradient-to-br from-celeste-300 to-celeste-500 text-white shadow-sm">
                    <x-icono :nombre="$icono" class="size-6" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-celeste-900">{{ $valor }}</p>
                    <p class="text-sm text-slate-500">{{ $texto }}</p>
                </div>
            </{{ $etiqueta }}>
        @endforeach
    </div>

    {{-- Caja de hoy: recaudación y pagos rechazados, hacia la lista de movimientos filtrada --}}
    <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <a href="{{ route('transacciones.index', ['periodo' => 'hoy', 'estado' => 'aprobada']) }}" class="tarjeta clicable flex items-center gap-4 p-5" data-caja="recaudacion">
            <div class="flex size-12 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-300 to-emerald-500 text-white shadow-sm">
                <x-icono nombre="movimientos" class="size-6" />
            </div>
            <div>
                <p class="text-sm text-slate-500">Recaudación de hoy</p>
                <p class="text-2xl font-bold text-celeste-900 tabular-nums">Q{{ number_format($caja['recargas'], 2) }} <span class="text-sm font-normal text-slate-500">en recargas</span></p>
                <p class="text-sm text-slate-600">{{ $caja['cantidad_pasajes'] }} {{ $caja['cantidad_pasajes'] == 1 ? 'pasaje' : 'pasajes' }} · Q{{ number_format($caja['pasajes'], 2) }}</p>
            </div>
        </a>
        <a href="{{ route('transacciones.index', ['periodo' => 'hoy', 'estado' => 'rechazada']) }}" class="tarjeta clicable flex items-center gap-4 p-5" data-caja="rechazados">
            <div @class(['flex size-12 items-center justify-center rounded-2xl text-white shadow-sm', 'bg-gradient-to-br from-rose-300 to-rose-500' => $caja['rechazados'] > 0, 'bg-gradient-to-br from-slate-300 to-slate-400' => $caja['rechazados'] === 0])>
                <x-icono nombre="error" class="size-6" />
            </div>
            <div>
                <p class="text-sm text-slate-500">Pagos rechazados hoy</p>
                <p class="text-2xl font-bold text-celeste-900 tabular-nums">{{ $caja['rechazados'] }}</p>
                <p class="text-sm text-slate-600">Recargas y pasajes no aprobados</p>
            </div>
        </a>
    </div>

    {{-- Gráficas: cada barra, punto o porción lleva al reporte filtrado (el operador ve su estación) --}}
    <section class="mt-6" data-graficas data-url-graficas="{{ route('panel.graficas') }}" aria-labelledby="titulo-graficas">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 id="titulo-graficas" class="flex items-center gap-2 font-semibold text-celeste-900"><x-icono nombre="reportes" class="text-celeste-500" /> Indicadores</h2>
            <a href="{{ route('reportes.index') }}" class="text-sm font-medium text-celeste-600 hover:underline">Ver todos los reportes</a>
        </div>
        <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
            @foreach ([
                ['espera', 'Pasajeros en espera por estación', $esAdmin ? 'Las 10 estaciones con más pasajeros (último registro)' : 'Último registro de su estación', route('reportes.show', 'estaciones')],
                ['recorridos_hoy', 'Recorridos por hora', 'Iniciados hoy', route('reportes.show', ['recorridos', 'periodo' => 'hoy'])],
                ['alertas_semana', 'Alertas por tipo', 'Últimos 7 días', route('reportes.show', ['alertas', 'periodo' => '7dias'])],
                ['recaudacion_semana', 'Recaudación', 'Recargas y pasajes de los últimos 7 días', route('reportes.show', ['pagos', 'periodo' => '7dias'])],
                ['ocupacion_lineas', 'Ocupación de buses por línea', 'Promedio de hoy (pasajeros a bordo contra capacidad)', route('reportes.show', ['recorridos', 'periodo' => 'hoy'])],
            ] as [$clave, $titulo, $detalle, $url])
                <article class="tarjeta flex flex-col p-5" data-grafica="{{ $clave }}">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h3 class="font-semibold text-celeste-900">{{ $titulo }}</h3>
                            <p class="text-xs text-slate-500">{{ $detalle }}</p>
                        </div>
                        <a href="{{ $url }}" class="shrink-0 text-xs font-medium text-celeste-600 hover:underline">Ver reporte</a>
                    </div>
                    <div class="relative mt-3 h-56">
                        <canvas role="img" aria-label="{{ $titulo }}"></canvas>
                        <p data-grafica-cargando class="absolute inset-0 flex items-center justify-center text-sm text-slate-400">Cargando…</p>
                        <p data-grafica-vacia hidden class="absolute inset-0 flex flex-col items-center justify-center gap-2 rounded-xl bg-celeste-50 px-6 text-center text-sm text-celeste-800"></p>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    {{-- Mini mapa con los buses en ruta --}}
    <section class="tarjeta mt-6 overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-2 px-5 py-4">
            <h2 class="flex items-center gap-2 font-semibold text-celeste-900"><x-icono nombre="en_vivo" class="text-celeste-500" /> En vivo</h2>
            <a href="{{ route('seguimiento.index') }}" class="text-sm font-medium text-celeste-600 hover:underline">Abrir seguimiento en vivo</a>
        </div>
        <div data-mapa-seguimiento="mini"
             data-url-datos="{{ route('seguimiento.datos') }}"
             data-url-avanzar="{{ route('seguimiento.avanzar') }}"
             class="isolate h-64" role="region" aria-label="Mini mapa de buses en ruta"></div>
    </section>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        {{-- Resumen por línea (vista vw_resumen_lineas): la fila lleva a la línea y el número de buses a sus buses --}}
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
                            <tr class="relative cursor-pointer transition hover:bg-celeste-50">
                                <td>
                                    <a href="{{ route('lineas.show', $r->id) }}" class="enlace-extendido font-semibold text-celeste-700">{{ $r->codigo }}</a>
                                    <span class="block text-xs text-slate-500">{{ $r->nombre }}</span>
                                </td>
                                <td class="text-right">{{ $r->total_estaciones }}</td>
                                <td class="text-right">{{ number_format($r->distancia_total_km, 2) }} km</td>
                                <td class="min-w-48">
                                    <div class="flex items-center justify-between text-xs">
                                        <a href="{{ route('buses.index', ['linea_id' => $r->id]) }}"
                                           class="relative z-10 rounded font-semibold text-celeste-700 underline-offset-2 hover:underline focus-visible:ring-2 focus-visible:ring-celeste-500 focus-visible:outline-none"
                                           aria-label="Ver los {{ $r->buses_asignados }} buses de la línea {{ $r->codigo }}">{{ $r->buses_asignados }} buses</a>
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

        {{-- Estado de la flota: cada fila lleva a Buses filtrado por ese estado --}}
        <section class="tarjeta p-5">
            <h2 class="font-semibold text-celeste-900">Estado de la flota</h2>
            <ul class="mt-3 space-y-1">
                @foreach (\App\Models\Bus::ESTADOS as $estado)
                    <li>
                        <a href="{{ route('buses.index', ['estado' => $estado]) }}"
                           class="clicable flex items-center justify-between rounded-xl px-2 py-2 hover:bg-celeste-50 hover:shadow-none">
                            <x-estado :valor="$estado" />
                            <span class="font-semibold text-celeste-800">{{ $busesPorEstado[$estado] ?? 0 }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>

            @if ($estacion)
                <a href="{{ route('estaciones.show', $estacion) }}" class="clicable mt-6 block rounded-xl bg-celeste-50 p-4">
                    <p class="text-xs font-semibold tracking-wide text-celeste-700 uppercase">Mi estación</p>
                    <p class="mt-1 font-semibold text-celeste-900">{{ $estacion->nombre }}</p>
                    <p class="text-sm text-slate-500">{{ $estacion->lineas->pluck('codigo')->join(', ') ?: 'Sin línea' }} · {{ $estacion->accesos->count() }} accesos</p>
                </a>
            @endif
        </section>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        {{-- Alertas activas: la tarjeta lleva al detalle; "Atender" actúa sin salir del panel --}}
        <section class="tarjeta p-5">
            <div class="flex items-center justify-between">
                <h2 class="flex items-center gap-2 font-semibold text-celeste-900">
                    <x-icono nombre="alerta" class="text-celeste-500" /> Alertas activas
                </h2>
                <a href="{{ route('alertas.index') }}" class="text-sm font-medium text-celeste-600 hover:underline">Ver todas</a>
            </div>
            <ul class="mt-4 space-y-3">
                @forelse ($alertas as $alerta)
                    <li class="clicable relative rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('alertas.show', $alerta) }}" class="enlace-extendido font-medium text-amber-800">{{ $alerta->tipoTexto() }}</a>
                                <p class="text-amber-700">{{ $alerta->mensaje }}</p>
                                <p class="mt-1 text-xs text-amber-600">{{ $alerta->created_at?->format('d/m/Y H:i') }}</p>
                            </div>
                            <div class="relative z-10 flex shrink-0 flex-col gap-1.5">
                                @if ($accion = $resolver[$alerta->id] ?? null)
                                    <a href="{{ $accion['url'] }}" class="btn-primario px-3 py-1.5 text-xs whitespace-nowrap"
                                       aria-label="{{ $accion['texto'] }}: alerta {{ $alerta->id }}">{{ $accion['texto'] }}</a>
                                @endif
                                <form method="POST" action="{{ route('alertas.atender', $alerta) }}">
                                    @csrf @method('PATCH')
                                    <button class="btn-secundario w-full px-3 py-1.5 text-xs" data-atender="Alerta #{{ $alerta->id }} · {{ $alerta->tipoTexto() }}"
                                            aria-label="Atender la alerta {{ $alerta->id }}">Atender</button>
                                </form>
                            </div>
                        </div>
                    </li>
                @empty
                    <li class="text-sm text-slate-500">No hay alertas activas.</li>
                @endforelse
            </ul>
        </section>

        {{-- Bitácora (solo administrador): cada registro lleva al elemento afectado si todavía existe --}}
        @if ($esAdmin)
            <section class="tarjeta p-5">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold text-celeste-900">Actividad reciente</h2>
                    <a href="{{ route('bitacora.index') }}" class="text-sm font-medium text-celeste-600 hover:underline">Ver toda la bitácora</a>
                </div>
                <ul class="mt-3 space-y-1">
                    @forelse ($bitacora as $b)
                        @php($url = $enlacesBitacora[$b->id] ?? null)
                        <li @class(['relative rounded-xl px-2 py-2 text-sm', 'clicable hover:bg-celeste-50 hover:shadow-none' => $url])>
                            @if ($url)
                                <a href="{{ $url }}" class="enlace-extendido text-slate-700">{{ $b->detalle }}</a>
                            @else
                                <p class="text-slate-700">{{ $b->detalle }}</p>
                            @endif
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
