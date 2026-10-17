{{-- Consulta pública de saldo: sin iniciar sesión y sin datos personales del titular. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Consulta de saldo · Transmetro Municipal</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gradient-to-br from-celeste-50 via-white to-celeste-100 font-sans text-slate-800 antialiased">
    <main class="mx-auto flex min-h-screen max-w-lg flex-col justify-center px-4 py-10">
        <div class="mb-6 flex items-center gap-3">
            <div class="flex size-11 items-center justify-center rounded-2xl bg-celeste-500 text-white shadow"><x-icono nombre="tarjeta" class="size-6" /></div>
            <div>
                <h1 class="text-xl font-bold text-celeste-900">Consulta de saldo</h1>
                <p class="text-sm text-slate-500">Tarjeta Transmetro · Municipalidad de Guatemala</p>
            </div>
        </div>

        @if ($bloqueado ?? false)
            <p class="mb-4 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700 ring-1 ring-rose-200" role="alert">
                Demasiados intentos. Espere un minuto e intente de nuevo.
            </p>
        @endif

        <form method="POST" action="{{ route('consulta-saldo.consultar') }}" class="tarjeta space-y-4 p-6">
            @csrf
            <x-campo nombre="codigo" etiqueta="Código de la tarjeta" required maxlength="20" placeholder="TM-000001" autocomplete="off" style="text-transform: uppercase" />
            <x-campo nombre="dpi4" etiqueta="Últimos 4 dígitos del DPI del titular" tipo="password" inputmode="numeric" pattern="\d{4}" maxlength="4" required
                     autocomplete="off" ayuda="Es la verificación de que la tarjeta es suya." />
            <button class="btn-primario w-full py-2.5">Consultar saldo</button>
        </form>

        @if ($tarjeta)
            <section class="tarjeta mt-6 p-6" data-resultado-consulta aria-live="polite">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-slate-500">Tarjeta {{ $tarjeta->codigo }}</p>
                        <p class="text-4xl font-bold text-celeste-900 tabular-nums">Q{{ number_format((float) $tarjeta->saldo, 2) }}</p>
                    </div>
                    <x-estado :valor="$tarjeta->estado" />
                </div>
                <h2 class="mt-5 text-sm font-semibold text-slate-700">Últimos movimientos</h2>
                <ul class="mt-2 divide-y divide-celeste-50 text-sm">
                    @forelse ($movimientos as $m)
                        <li class="flex items-center justify-between py-2">
                            <span>
                                {{ $m->descripcion() }}
                                <span class="block text-xs text-slate-500">{{ $m->created_at?->format('d/m/Y H:i') }}{{ $m->estacion ? ' · '.$m->estacion->nombre : '' }}</span>
                                @unless ($m->aprobada()) <span class="block text-xs text-rose-600">Rechazado: {{ $m->motivo_rechazo }}</span> @endunless
                            </span>
                            <span @class(['font-semibold tabular-nums', 'text-emerald-700' => $m->tipo === 'recarga' && $m->aprobada(), 'text-slate-400 line-through' => ! $m->aprobada()])>
                                {{ $m->tipo === 'recarga' ? '+' : '−' }}Q{{ number_format((float) $m->monto, 2) }}
                            </span>
                        </li>
                    @empty
                        <li class="py-2 text-slate-500">Sin movimientos.</li>
                    @endforelse
                </ul>
            </section>
        @endif

        <p class="mt-6 text-center text-sm"><a href="{{ route('login') }}" class="text-celeste-600 hover:underline">Ir al inicio de sesión del personal</a></p>
    </main>
    <x-notificaciones />
</body>
</html>
