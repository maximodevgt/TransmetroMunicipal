{{-- Campana de alertas activas con panel desplegable. Ver resources/js/alertas.js. --}}
@props(['id', 'clara' => false])

<div data-campana data-url="{{ route('alertas.nuevas') }}" class="relative">
    <button type="button" data-campana-boton aria-haspopup="true" aria-expanded="false" aria-controls="panel-alertas-{{ $id }}" aria-label="Alertas activas"
            @class([
                'relative rounded-xl p-2 transition focus:outline-none focus-visible:ring-2',
                'text-white hover:bg-white/20 focus-visible:ring-white' => $clara,
                'text-celeste-600 hover:bg-celeste-50 focus-visible:ring-celeste-400' => ! $clara,
            ])>
        <x-icono nombre="alerta" class="size-6" />
        <span data-campana-contador hidden
              class="absolute -top-0.5 -right-0.5 min-w-5 rounded-full bg-rose-500 px-1.5 text-center text-[11px] leading-5 font-bold text-white ring-2 ring-white"></span>
    </button>

    <div id="panel-alertas-{{ $id }}" data-campana-panel hidden
         class="absolute right-0 z-50 mt-2 w-80 max-w-[calc(100vw-1.5rem)] overflow-hidden rounded-2xl bg-white text-slate-800 shadow-xl ring-1 ring-celeste-100">
        <div class="flex items-center justify-between border-b border-celeste-50 px-4 py-3">
            <p class="text-sm font-semibold text-celeste-900">Alertas activas</p>
            <a href="{{ route('alertas.index', ['estado' => 'activa']) }}" class="text-xs font-medium text-celeste-600 hover:underline">Ver todas</a>
        </div>
        <ul data-campana-lista class="max-h-80 divide-y divide-celeste-50 overflow-y-auto">
            <li class="px-4 py-6 text-center text-sm text-slate-500">Cargando…</li>
        </ul>
        <label class="flex cursor-pointer items-center justify-between gap-3 border-t border-celeste-50 bg-celeste-50/50 px-4 py-3 text-sm">
            <span>Sonido al llegar una alerta</span>
            <input type="checkbox" role="switch" data-sonido-alertas class="peer sr-only">
            <span aria-hidden="true"
                  class="relative h-6 w-11 shrink-0 rounded-full bg-slate-300 transition peer-checked:bg-celeste-500 peer-focus-visible:ring-2 peer-focus-visible:ring-celeste-400 peer-focus-visible:ring-offset-2 after:absolute after:top-0.5 after:left-0.5 after:size-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-5 motion-reduce:transition-none motion-reduce:after:transition-none"></span>
        </label>
    </div>

    <template data-plantilla-alerta>
        <li>
            <a class="block px-4 py-3 hover:bg-amber-50 focus:bg-amber-50 focus:outline-none">
                <p data-alerta-tipo class="text-sm font-medium text-amber-800"></p>
                <p data-alerta-lugar class="text-xs text-slate-500"></p>
                <p data-alerta-mensaje class="mt-0.5 line-clamp-2 text-sm text-slate-600"></p>
            </a>
        </li>
    </template>
</div>
