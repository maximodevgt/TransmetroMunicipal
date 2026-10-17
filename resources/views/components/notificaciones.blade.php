{{-- Contenedor de notificaciones flotantes (toasts). resources/js/notificaciones.js las crea a partir de estas plantillas. --}}
@php
    $tipos = [
        'exito' => ['icono' => 'exito', 'rol' => 'status', 'borde' => 'border-emerald-500', 'fondo' => 'bg-emerald-100 text-emerald-600', 'barra' => 'bg-emerald-400'],
        'error' => ['icono' => 'error', 'rol' => 'alert', 'borde' => 'border-rose-500', 'fondo' => 'bg-rose-100 text-rose-600', 'barra' => 'bg-rose-400'],
        'advertencia' => ['icono' => 'advertencia', 'rol' => 'status', 'borde' => 'border-amber-500', 'fondo' => 'bg-amber-100 text-amber-600', 'barra' => 'bg-amber-400'],
        'info' => ['icono' => 'info', 'rol' => 'status', 'borde' => 'border-celeste-500', 'fondo' => 'bg-celeste-100 text-celeste-600', 'barra' => 'bg-celeste-400'],
    ];

    // Mensajes que dejan los controladores con ->with('ok' | 'error' | 'advertencia' | 'info', ...).
    $iniciales = collect(['ok' => 'exito', 'error' => 'error', 'advertencia' => 'advertencia', 'info' => 'info'])
        ->filter(fn ($tipo, $clave) => filled(session($clave)))
        ->map(fn ($tipo, $clave) => ['tipo' => $tipo, 'mensaje' => session($clave)])
        ->values();

    if ($errors->any() && ! session()->has('error')) {
        $iniciales->push([
            'tipo' => 'advertencia',
            'titulo' => 'Revise el formulario',
            'mensaje' => $errors->count() === 1 ? 'Hay un campo con error.' : "Hay {$errors->count()} campos con errores.",
        ]);
    }
@endphp

<div data-notificaciones role="region" aria-label="Notificaciones"
     class="pointer-events-none fixed inset-x-0 top-0 z-[60] flex flex-col gap-3 p-3 sm:inset-x-auto sm:top-4 sm:right-4 sm:w-96 sm:p-0 lg:top-16"></div>

@foreach ($tipos as $tipo => $estilo)
    <template data-plantilla-toast="{{ $tipo }}">
        <div data-toast role="{{ $estilo['rol'] }}" aria-atomic="true"
             class="toast pointer-events-auto relative w-full overflow-hidden rounded-2xl border-l-4 bg-white shadow-lg shadow-celeste-900/10 ring-1 ring-celeste-100 {{ $estilo['borde'] }}">
            <div class="flex items-start gap-3 p-4">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-full {{ $estilo['fondo'] }}">
                    <x-icono :nombre="$estilo['icono']" />
                </span>
                <div class="min-w-0 flex-1 pt-0.5">
                    <p data-toast-titulo class="text-sm font-semibold text-slate-900"></p>
                    <p data-toast-mensaje class="mt-0.5 text-sm break-words text-slate-600"></p>
                    <a data-toast-enlace hidden class="mt-2 inline-flex items-center gap-1 rounded-lg bg-celeste-50 px-2.5 py-1 text-xs font-semibold text-celeste-700 ring-1 ring-celeste-200 hover:bg-celeste-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-celeste-400"></a>
                </div>
                <button type="button" data-toast-cerrar aria-label="Cerrar notificación"
                        class="-m-1 rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-celeste-400">
                    <x-icono nombre="cerrar" class="size-4" />
                </button>
            </div>
            <div data-toast-progreso class="toast-progreso absolute bottom-0 left-0 h-1 w-full {{ $estilo['barra'] }}"></div>
        </div>
    </template>
@endforeach

<script type="application/json" data-notificaciones-iniciales>@json($iniciales)</script>

{{-- Sin JavaScript los mensajes se muestran como texto normal. --}}
@if ($iniciales->isNotEmpty())
    <noscript>
        <div class="mx-auto max-w-7xl space-y-2 px-4 pt-4 lg:pl-72">
            @foreach ($iniciales as $aviso)
                <p class="rounded-xl bg-white px-4 py-3 text-sm shadow-sm ring-1 ring-celeste-100">{{ $aviso['mensaje'] }}</p>
            @endforeach
        </div>
    </noscript>
@endif
