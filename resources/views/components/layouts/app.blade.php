@props(['titulo', 'subtitulo' => null])

@php
    $usuario = auth()->user();
    // 'rol' => null lo ven todos; 'administrador' u 'operador' solo ese rol.
    $menu = [
        ['ruta' => 'dashboard', 'activo' => 'dashboard', 'texto' => 'Panel', 'icono' => 'inicio', 'rol' => null],
        ['ruta' => 'mi-estacion', 'activo' => 'mi-estacion*', 'texto' => 'Mi estación', 'icono' => 'mi_estacion', 'rol' => 'operador'],
        ['ruta' => 'seguimiento.index', 'activo' => 'seguimiento.*', 'texto' => 'Seguimiento en vivo', 'icono' => 'en_vivo', 'rol' => null],
        ['ruta' => 'recorridos.index', 'activo' => 'recorridos.*', 'texto' => 'Recorridos', 'icono' => 'recorridos', 'rol' => null],
        ['ruta' => 'alertas.index', 'activo' => 'alertas.*', 'texto' => 'Alertas', 'icono' => 'alerta', 'rol' => null],
        ['ruta' => 'lineas.index', 'activo' => 'lineas.*', 'texto' => 'Líneas', 'icono' => 'lineas', 'rol' => null],
        ['ruta' => 'estaciones.index', 'activo' => 'estaciones.*', 'texto' => 'Estaciones', 'icono' => 'estaciones', 'rol' => null],
        ['ruta' => 'buses.index', 'activo' => 'buses.*', 'texto' => 'Buses', 'icono' => 'buses', 'rol' => null],
        ['ruta' => 'parqueos.index', 'activo' => 'parqueos.*', 'texto' => 'Parqueos', 'icono' => 'parqueos', 'rol' => null],
        ['ruta' => 'pilotos.index', 'activo' => 'pilotos.*', 'texto' => 'Pilotos', 'icono' => 'pilotos', 'rol' => 'administrador'],
        ['ruta' => 'usuarios.index', 'activo' => 'usuarios.*', 'texto' => 'Usuarios', 'icono' => 'usuarios', 'rol' => 'administrador'],
    ];
@endphp

<!DOCTYPE html>
<html lang="es" class="scroll-pt-16">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }} · Transmetro Municipal</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('cabecera')
</head>
<body class="min-h-screen bg-celeste-50 font-sans text-slate-800 antialiased">
    {{-- Barra superior en móvil --}}
    <header class="sticky top-0 z-30 flex items-center justify-between bg-gradient-to-r from-celeste-400 to-celeste-500 px-4 py-3 text-white shadow lg:hidden">
        <span class="font-semibold">Transmetro Municipal</span>
        <div class="flex items-center gap-1">
            <x-campana id="movil" clara />
            <button type="button" data-menu-toggle class="rounded-lg p-1.5 hover:bg-white/20" aria-label="Abrir menú">
                <x-icono nombre="menu" class="size-6" />
            </button>
        </div>
    </header>

    {{-- Menú lateral --}}
    <aside data-menu class="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col bg-gradient-to-b from-celeste-400 via-celeste-500 to-celeste-600 text-white shadow-xl lg:flex">
        <div class="flex items-center gap-3 px-6 py-6">
            <div class="flex size-11 items-center justify-center rounded-2xl bg-white text-celeste-500 shadow">
                <x-icono nombre="buses" class="size-6" />
            </div>
            <div class="leading-tight">
                <p class="text-lg font-bold">Transmetro</p>
                <p class="text-xs text-celeste-50/90">Municipalidad de Guatemala</p>
            </div>
        </div>

        <nav class="flex-1 space-y-1 px-3">
            @foreach ($menu as $item)
                @continue($item['rol'] !== null && $item['rol'] !== $usuario->rol)
                <a href="{{ route($item['ruta']) }}"
                   @class([
                       'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                       'bg-white text-celeste-600 shadow' => request()->routeIs($item['activo']),
                       'text-white/90 hover:bg-white/15' => ! request()->routeIs($item['activo']),
                   ])>
                    <x-icono :nombre="$item['icono']" />
                    {{ $item['texto'] }}
                </a>
            @endforeach
        </nav>

        <div class="m-3 rounded-2xl bg-white/15 p-4">
            <p class="truncate text-sm font-semibold">{{ $usuario->name }}</p>
            <p class="truncate text-xs text-white/80">
                {{ ucfirst($usuario->rol) }}@if ($usuario->estacion) · {{ $usuario->estacion->nombre }}@endif
            </p>
            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <button class="flex w-full items-center justify-center gap-2 rounded-xl bg-white px-3 py-2 text-sm font-semibold text-celeste-600 hover:bg-celeste-50">
                    <x-icono nombre="salir" class="size-4" /> Cerrar sesión
                </button>
            </form>
        </div>
    </aside>
    <div data-menu-fondo class="fixed inset-0 z-30 hidden bg-celeste-900/30 lg:hidden"></div>

    <main class="lg:pl-64">
        {{-- Barra superior en escritorio --}}
        <div class="sticky top-0 z-20 hidden items-center justify-end gap-3 border-b border-celeste-100 bg-white px-8 py-2 shadow-sm shadow-celeste-900/5 lg:flex">
            <x-campana id="escritorio" />
            <span class="text-sm text-slate-600">{{ $usuario->name }}</span>
        </div>

        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-celeste-900">{{ $titulo }}</h1>
                    @if ($subtitulo)
                        <p class="mt-1 text-sm text-slate-500">{{ $subtitulo }}</p>
                    @endif
                </div>
                @isset($acciones)
                    <div class="flex flex-wrap gap-2">{{ $acciones }}</div>
                @endisset
            </div>

            {{ $slot }}
        </div>
    </main>

    <x-notificaciones />
    <x-confirmacion />
    <x-dialogo-atender />
</body>
</html>
