@php
    $esAdmin = auth()->user()->esAdministrador();
    // Contadores que funcionan como filtro rápido: [texto, valor, filtros, estilo].
    $rapidos = [
        ['Activas', $contadores['activas'], ['estado' => 'activa'], 'text-amber-700'],
        ['Atendidas hoy', $contadores['atendidas_hoy'], ['estado' => 'atendida', 'periodo' => 'hoy'], 'text-emerald-700'],
        ['Alta demanda', $contadores['alta_demanda'], ['estado' => 'activa', 'tipo' => 'alta_demanda'], 'text-rose-700'],
        ['Baja ocupación', $contadores['baja_ocupacion'], ['estado' => 'activa', 'tipo' => 'baja_ocupacion'], 'text-amber-700'],
        ['Insuficiencia', $contadores['insuficiencia_unidades'], ['estado' => 'activa', 'tipo' => 'insuficiencia_unidades'], 'text-rose-700'],
    ];
    $filtroActual = array_filter($filtros, fn ($v, $k) => filled($v) && ! ($k === 'periodo' && $v === 'todas'), ARRAY_FILTER_USE_BOTH);
@endphp

<x-layouts.app titulo="Alertas" :subtitulo="$esAdmin ? 'Alertas de todo el Transmetro' : 'Alertas de su estación'">
    {{-- Contadores: filtro rápido --}}
    <nav aria-label="Filtros rápidos" class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        @foreach ($rapidos as [$texto, $valor, $filtro, $color])
            @php($seleccionado = $filtroActual == $filtro)
            <a href="{{ route('alertas.index', $filtro) }}" data-contador
               @class(['tarjeta clicable block p-4', 'ring-2 ring-celeste-400' => $seleccionado])
               @if ($seleccionado) aria-current="true" @endif>
                <p class="text-2xl font-bold {{ $color }}">{{ $valor }}</p>
                <p class="text-sm text-slate-600">{{ $texto }}</p>
            </a>
        @endforeach
    </nav>

    <section class="tarjeta overflow-hidden">
        <form method="GET" class="flex flex-wrap items-end gap-3 p-4">
            <div>
                <label for="filtro_tipo" class="etiqueta">Tipo</label>
                <select id="filtro_tipo" name="tipo" class="campo">
                    <option value="">Todos</option>
                    @foreach (\App\Models\Alerta::TIPOS as $valor => $texto)
                        <option value="{{ $valor }}" @selected(($filtros['tipo'] ?? '') === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filtro_estado" class="etiqueta">Estado</label>
                <select id="filtro_estado" name="estado" class="campo">
                    <option value="">Todos</option>
                    <option value="activa" @selected(($filtros['estado'] ?? '') === 'activa')>Activa</option>
                    <option value="atendida" @selected(($filtros['estado'] ?? '') === 'atendida')>Atendida</option>
                </select>
            </div>
            <div>
                <label for="filtro_periodo" class="etiqueta">Fecha</label>
                <select id="filtro_periodo" name="periodo" class="campo">
                    @foreach (\App\Http\Controllers\AlertaController::PERIODOS as $valor => $texto)
                        <option value="{{ $valor }}" @selected($filtros['periodo'] === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filtro_linea" class="etiqueta">Línea</label>
                <select id="filtro_linea" name="linea_id" class="campo">
                    <option value="">Todas</option>
                    @foreach ($lineas as $linea)
                        <option value="{{ $linea->id }}" @selected(($filtros['linea_id'] ?? '') == $linea->id)>{{ $linea->codigo }}</option>
                    @endforeach
                </select>
            </div>
            @if ($esAdmin)
                <div>
                    <label for="filtro_estacion" class="etiqueta">Estación</label>
                    <select id="filtro_estacion" name="estacion_id" class="campo">
                        <option value="">Todas</option>
                        @foreach ($estaciones as $estacion)
                            <option value="{{ $estacion->id }}" @selected(($filtros['estacion_id'] ?? '') == $estacion->id)>{{ $estacion->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <button class="btn-secundario">Filtrar</button>
            @if ($filtroActual)
                <a href="{{ route('alertas.index') }}" class="btn text-slate-500 hover:text-celeste-600">Limpiar</a>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="tabla">
                <thead>
                    <tr><th>Alerta</th><th class="whitespace-nowrap">Fecha</th><th>Estado</th><th class="text-right"><span class="sr-only">Acciones</span></th></tr>
                </thead>
                <tbody>
                    @forelse ($alertas as $alerta)
                        @php($accion = $acciones[$alerta->id] ?? null)
                        {{-- La fila completa lleva al detalle; los botones quedan por encima del enlace. --}}
                        <tr class="relative cursor-pointer transition hover:bg-celeste-50" data-fila-alerta="{{ $alerta->id }}">
                            <td class="min-w-56">
                                <a href="{{ route('alertas.show', $alerta) }}" class="enlace-extendido font-medium text-celeste-700">
                                    {{ $alerta->tipoTexto() }} <span class="text-xs font-normal text-slate-400">#{{ $alerta->id }}</span>
                                </a>
                                <span class="block max-w-xl text-sm text-slate-600">{{ $alerta->mensaje }}</span>
                                @if ($alerta->lugar())
                                    <span class="block text-xs text-slate-400">{{ $alerta->lugar() }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-sm">{{ $alerta->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="whitespace-nowrap">
                                <x-estado :valor="$alerta->estado" />
                                @if (! $alerta->activa())
                                    @php($atencion = $atenciones[$alerta->id] ?? null)
                                    <span class="mt-1 block text-xs text-slate-500" data-atendida-por>
                                        @if ($atencion)
                                            por {{ $atencion->user?->name ?? 'Sistema' }}<br>a las {{ $atencion->created_at?->format('H:i') }}
                                        @else
                                            a las {{ $alerta->atendida_en?->format('H:i') ?? '—' }}
                                        @endif
                                    </span>
                                @endif
                            </td>
                            <td class="text-right">
                                <div class="relative z-10 inline-flex w-24 flex-col items-stretch gap-1.5">
                                    @if ($alerta->activa())
                                        @if ($accion && $accion['texto'] === 'Solicitar unidad')
                                            <form method="POST" action="{{ route('alertas.solicitar-unidad', $alerta) }}">
                                                @csrf
                                                <button class="btn-primario w-full px-3 py-1.5 text-xs whitespace-nowrap">Solicitar unidad</button>
                                            </form>
                                        @elseif ($accion)
                                            <a href="{{ $accion['url'] }}" class="btn-primario px-3 py-1.5 text-xs whitespace-nowrap">{{ $accion['texto'] }}</a>
                                        @endif
                                        <form method="POST" action="{{ route('alertas.atender', $alerta) }}">
                                            @csrf @method('PATCH')
                                            <button class="btn-secundario w-full px-3 py-1.5 text-xs" data-atender="Alerta #{{ $alerta->id }} · {{ $alerta->tipoTexto() }}"
                                                    aria-label="Atender la alerta {{ $alerta->id }}">Atender</button>
                                        </form>
                                    @else
                                        <a href="{{ route('alertas.show', $alerta) }}" class="btn-secundario px-3 py-1.5 text-xs">Ver</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-slate-500">No hay alertas con esos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $alertas->links() }}</div>
    </section>
</x-layouts.app>
