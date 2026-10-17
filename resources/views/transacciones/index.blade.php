<x-layouts.app titulo="Movimientos" :subtitulo="auth()->user()->esAdministrador() ? 'Recargas y pasajes de todas las tarjetas' : 'Recargas y pasajes de su estación'">
    <section class="tarjeta overflow-hidden">
        <form method="GET" class="flex flex-wrap items-end gap-3 p-4">
            <div>
                <label for="f_tipo" class="etiqueta">Tipo</label>
                <select id="f_tipo" name="tipo" class="campo">
                    <option value="">Todos</option>
                    <option value="recarga" @selected(($filtros['tipo'] ?? '') === 'recarga')>Recargas</option>
                    <option value="pago" @selected(($filtros['tipo'] ?? '') === 'pago')>Pasajes</option>
                </select>
            </div>
            <div>
                <label for="f_estado" class="etiqueta">Estado</label>
                <select id="f_estado" name="estado" class="campo">
                    <option value="">Todos</option>
                    <option value="aprobada" @selected(($filtros['estado'] ?? '') === 'aprobada')>Aprobadas</option>
                    <option value="rechazada" @selected(($filtros['estado'] ?? '') === 'rechazada')>Rechazadas</option>
                </select>
            </div>
            <div>
                <label for="f_metodo" class="etiqueta">Medio</label>
                <select id="f_metodo" name="metodo" class="campo">
                    <option value="">Todos</option>
                    <option value="efectivo" @selected(($filtros['metodo'] ?? '') === 'efectivo')>Efectivo</option>
                    <option value="tarjeta" @selected(($filtros['metodo'] ?? '') === 'tarjeta')>En línea</option>
                </select>
            </div>
            <div>
                <label for="f_periodo" class="etiqueta">Fecha</label>
                <select id="f_periodo" name="periodo" class="campo">
                    @foreach (['hoy' => 'Hoy', '7dias' => 'Últimos 7 días', 'todas' => 'Todas'] as $valor => $texto)
                        <option value="{{ $valor }}" @selected($filtros['periodo'] === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn-secundario">Filtrar</button>
        </form>

        <div class="overflow-x-auto">
            <table class="tabla">
                <thead><tr><th>Fecha</th><th>Movimiento</th><th>Tarjeta</th><th class="text-right">Monto</th><th>Estado</th></tr></thead>
                <tbody>
                    @forelse ($transacciones as $t)
                        <tr class="relative cursor-pointer transition hover:bg-celeste-50">
                            <td class="whitespace-nowrap text-sm text-slate-500">{{ $t->created_at?->format('d/m/Y H:i') }}</td>
                            <td>
                                <a href="{{ route('transacciones.show', $t) }}" class="enlace-extendido font-medium text-slate-800">{{ $t->descripcion() }}</a>
                                <span class="block text-xs text-slate-500">{{ collect([$t->medioDePago(), $t->acceso?->nombre, $t->estacion?->nombre, $t->user?->name])->filter()->join(' · ') }}</span>
                                @unless ($t->aprobada()) <span class="block text-xs text-rose-600">{{ $t->motivo_rechazo }}</span> @endunless
                            </td>
                            <td class="whitespace-nowrap tabular-nums">{{ $t->tarjeta->codigo }}</td>
                            <td class="text-right font-semibold tabular-nums whitespace-nowrap">Q{{ number_format((float) $t->monto, 2) }}</td>
                            <td><x-estado :valor="$t->estado" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-slate-500">No hay movimientos con esos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $transacciones->links() }}</div>
    </section>
</x-layouts.app>
