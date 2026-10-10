<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('codigo') · Transmetro Municipal</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-gradient-to-b from-celeste-100 to-white px-6 font-sans text-slate-800 antialiased">
    <div class="tarjeta max-w-md p-10 text-center">
        <p class="text-6xl font-bold text-celeste-400">@yield('codigo')</p>
        <h1 class="mt-4 text-xl font-bold text-celeste-900">@yield('titulo')</h1>
        <p class="mt-2 text-sm text-slate-500">@yield('mensaje')</p>
        <a href="{{ url('/') }}" class="btn-primario mt-8">Volver al panel</a>
    </div>
</body>
</html>
