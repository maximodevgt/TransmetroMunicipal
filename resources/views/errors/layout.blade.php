{{-- Páginas de error: independientes de la sesión y de la base (también sirven para el 500). --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('codigo') · @yield('titulo') · Transmetro Municipal</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-gradient-to-b from-celeste-100 to-white px-4 py-10 font-sans text-slate-800 antialiased">
    <main class="tarjeta w-full max-w-md p-8 text-center sm:p-10" data-error="@yield('codigo')">
        <div class="mx-auto flex size-16 items-center justify-center rounded-2xl bg-gradient-to-br from-celeste-300 to-celeste-500 text-white shadow">
            <x-icono :nombre="trim($__env->yieldContent('icono', 'advertencia'))" class="size-8" />
        </div>
        <p class="mt-6 text-6xl font-bold text-celeste-400 tabular-nums">@yield('codigo')</p>
        <h1 class="mt-3 text-xl font-bold text-celeste-900">@yield('titulo')</h1>
        <p class="mt-2 text-sm text-slate-600">@yield('mensaje')</p>
        <div class="mt-8 flex flex-col justify-center gap-2 sm:flex-row">
            <a href="{{ url('/') }}" class="btn-primario"><x-icono nombre="inicio" class="size-4" /> Volver al panel</a>
            <button type="button" onclick="history.length > 1 ? history.back() : location.assign('{{ url('/') }}')" class="btn-secundario">
                <x-icono nombre="atras" class="size-4" /> Página anterior
            </button>
        </div>
        <p class="mt-8 text-xs text-slate-400">Transmetro · Municipalidad de Guatemala</p>
    </main>
</body>
</html>
