<x-layouts.app :titulo="'Tarjeta '.$tarjeta->codigo" :subtitulo="$tarjeta->pasajero->nombre">
    <x-slot:acciones>
        <a href="{{ route('tarjetas.index') }}" class="btn-secundario"><x-icono nombre="atras" class="size-4" /> Tarjetas</a>
        <a href="{{ route('torniquete.index', ['codigo' => $tarjeta->codigo]) }}" class="btn-secundario"><x-icono nombre="torniquete" class="size-4" /> Simular pasaje</a>
        <a href="{{ route('recargas.create', $tarjeta) }}" class="btn-primario"><x-icono nombre="mas" class="size-4" /> Recargar</a>
    </x-slot:acciones>

    <div class="grid gap-6 xl:grid-cols-3">
        <section class="tarjeta space-y-5 p-6">
            <div>
                <p class="text-xs text-slate-500">Saldo</p>
                <p @class([
                    'text-4xl font-bold tabular-nums',
                    'text-rose-600' => (float) $tarjeta->saldo < config('transmetro.pasaje'),
                    'text-celeste-900' => (float) $tarjeta->saldo >= config('transmetro.pasaje'),
                ]) data-saldo>Q{{ number_format((float) $tarjeta->saldo, 2) }}</p>
                <p class="text-xs text-slate-500">Alcanza para {{ (int) floor((float) $tarjeta->saldo / config('transmetro.pasaje')) }} pasajes de Q{{ number_format(config('transmetro.pasaje'), 2) }}</p>
            </div>
            <div><p class="text-xs text-slate-500">Estado</p><x-estado :valor="$tarjeta->estado" class="mt-1" /></div>
            <dl class="space-y-2 border-t border-celeste-50 pt-4 text-sm">
                <div><dt class="text-xs text-slate-500">Pasajero</dt><dd class="font-medium">{{ $tarjeta->pasajero->nombre }}</dd></div>
                <div><dt class="text-xs text-slate-500">DPI</dt><dd class="tabular-nums">{{ $tarjeta->pasajero->dpi }}</dd></div>
                <div><dt class="text-xs text-slate-500">Teléfono</dt><dd>{{ $tarjeta->pasajero->telefono ?? '—' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Emitida</dt><dd>{{ $tarjeta->fecha_emision?->format('d/m/Y') }}</dd></div>
            </dl>

            {{-- Cambiar el estado: solo el administrador, siempre con motivo (queda en la bitácora) --}}
            @if (auth()->user()->esAdministrador())
                <details class="rounded-xl bg-celeste-50 p-4" @if ($errors->hasAny(['estado', 'motivo'])) open @endif data-cambiar-estado>
                    <summary class="cursor-pointer text-sm font-semibold text-celeste-800">Cambiar estado</summary>
                    <form method="POST" action="{{ route('tarjetas.estado', $tarjeta) }}" class="mt-3 space-y-3"
                          data-confirmar-titulo="¿Cambiar el estado de la tarjeta {{ $tarjeta->codigo }}?"
                          data-confirmar="Solo con la tarjeta activa se pagan pasajes y se reciben recargas." data-confirmar-boton="Sí, cambiar">
                        @csrf @method('PATCH')
                        <x-campo nombre="estado" etiqueta="Nuevo estado" tipo="select" required vacio="Seleccione…"
                                 :opciones="collect(\App\Models\Tarjeta::ESTADOS)->except($tarjeta->estado)->all()" />
                        <x-campo nombre="motivo" etiqueta="Motivo" maxlength="150" required minlength="5" placeholder="Reporte de robo o extravío" />
                        <button class="btn-primario w-full">Cambiar estado</button>
                    </form>
                </details>
            @else
                <p class="rounded-xl bg-celeste-50 p-3 text-xs text-slate-600">El estado de la tarjeta lo cambia el administrador.</p>
            @endif
        </section>

        <section class="tarjeta overflow-hidden xl:col-span-2">
            <h2 class="px-5 py-4 font-semibold text-celeste-900">Movimientos</h2>
            <div class="overflow-x-auto">
                <table class="tabla">
                    <thead><tr><th>Fecha</th><th>Movimiento</th><th class="text-right">Monto</th><th class="text-right">Saldo</th><th>Estado</th></tr></thead>
                    <tbody>
                        @forelse ($transacciones as $t)
                            <tr class="relative cursor-pointer transition hover:bg-celeste-50">
                                <td class="whitespace-nowrap text-sm text-slate-500">{{ $t->created_at?->format('d/m/Y H:i') }}</td>
                                <td>
                                    <a href="{{ route('transacciones.show', $t) }}" class="enlace-extendido font-medium text-slate-800">{{ $t->descripcion() }}</a>
                                    <span class="block text-xs text-slate-500">
                                        {{ collect([$t->medioDePago(), $t->acceso ? $t->acceso->estacion->nombre.' · '.$t->acceso->nombre : $t->estacion?->nombre])->filter()->join(' · ') ?: '—' }}
                                    </span>
                                    @if (! $t->aprobada())
                                        <span class="block text-xs text-rose-600">{{ $t->motivo_rechazo }}</span>
                                    @endif
                                </td>
                                <td @class(['text-right font-semibold tabular-nums whitespace-nowrap', 'text-emerald-700' => $t->tipo === 'recarga' && $t->aprobada(), 'text-slate-700' => $t->tipo === 'pago' && $t->aprobada(), 'text-slate-400 line-through' => ! $t->aprobada()])>
                                    {{ $t->tipo === 'recarga' ? '+' : '−' }}Q{{ number_format((float) $t->monto, 2) }}
                                </td>
                                <td class="text-right tabular-nums">Q{{ number_format((float) $t->saldo_resultante, 2) }}</td>
                                <td><x-estado :valor="$t->estado" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-slate-500">Sin movimientos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4">{{ $transacciones->links() }}</div>
        </section>
    </div>
</x-layouts.app>
