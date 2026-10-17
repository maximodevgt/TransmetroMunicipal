<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión · Transmetro Municipal</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white font-sans text-slate-800 antialiased">
    <div class="grid min-h-screen lg:grid-cols-2">
        {{-- Panel celeste --}}
        <section class="relative hidden overflow-hidden bg-gradient-to-br from-celeste-300 via-celeste-400 to-celeste-600 p-12 text-white lg:flex lg:flex-col lg:justify-between">
            <div class="absolute -top-24 -right-24 size-96 rounded-full bg-white/10"></div>
            <div class="absolute -bottom-32 -left-16 size-80 rounded-full bg-white/10"></div>

            <div class="relative flex items-center gap-3">
                <div class="flex size-12 items-center justify-center rounded-2xl bg-white text-celeste-500 shadow-lg">
                    <x-icono nombre="buses" class="size-7" />
                </div>
                <span class="text-xl font-bold">Transmetro Municipal</span>
            </div>

            <div class="relative max-w-md">
                <h1 class="text-4xl leading-tight font-bold">Gestión de líneas, estaciones y buses</h1>
                <p class="mt-4 text-celeste-50">
                    Sistema de administración del Transmetro de la Municipalidad de Guatemala.
                </p>
            </div>

            <p class="relative text-sm text-celeste-50/80">© {{ date('Y') }} Municipalidad de Guatemala</p>
        </section>

        {{-- Formulario --}}
        <section class="flex items-center justify-center bg-gradient-to-b from-celeste-50 to-white px-6 py-12 lg:bg-none">
            <div class="w-full max-w-sm">
                <div class="mb-8 flex items-center gap-3 lg:hidden">
                    <div class="flex size-11 items-center justify-center rounded-2xl bg-celeste-500 text-white shadow">
                        <x-icono nombre="buses" class="size-6" />
                    </div>
                    <span class="text-lg font-bold text-celeste-800">Transmetro Municipal</span>
                </div>

                <h2 class="text-2xl font-bold text-celeste-900">Bienvenido</h2>
                <p class="mt-1 text-sm text-slate-500">Ingrese con su correo institucional.</p>

                <form method="POST" action="{{ url('/login') }}" class="mt-8 space-y-5">
                    @csrf
                    <x-campo nombre="email" etiqueta="Correo" tipo="email" required autofocus autocomplete="username" placeholder="usuario@transmetro.gt" />
                    <x-campo nombre="password" etiqueta="Contraseña" tipo="password" required autocomplete="current-password" />

                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="remember" class="size-4 rounded border-celeste-300 text-celeste-500 focus:ring-celeste-400">
                        Mantener la sesión iniciada
                    </label>

                    <button class="btn-primario w-full py-2.5">Iniciar sesión</button>
                </form>
                <p class="mt-6 text-center text-sm text-slate-500">
                    ¿Tiene una Tarjeta Transmetro?
                    <a href="{{ route('consulta-saldo') }}" class="font-semibold text-celeste-600 hover:underline">Consulte su saldo</a>
                </p>
            </div>
        </section>
    </div>
</body>
</html>
