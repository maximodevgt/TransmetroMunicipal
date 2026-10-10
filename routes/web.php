<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EstacionController;
use App\Http\Controllers\HistorialEducativoController;
use App\Http\Controllers\LineaController;
use App\Http\Controllers\LineaEstacionController;
use App\Http\Controllers\PilotoController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:6,1');
});

Route::middleware(['auth', 'activo'])->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');

    // Lectura para todos los roles; crear, editar y eliminar solo administrador (ver middleware() de cada controlador).
    Route::resource('lineas', LineaController::class)->parameters(['lineas' => 'linea']);
    Route::resource('estaciones', EstacionController::class)->parameters(['estaciones' => 'estacion']);
    Route::resource('buses', BusController::class)->parameters(['buses' => 'bus']);

    Route::middleware('rol:administrador')->group(function () {
        Route::post('lineas/{linea}/estaciones', [LineaEstacionController::class, 'store'])->name('lineas.estaciones.store');
        Route::put('lineas/{linea}/estaciones/{estacion}', [LineaEstacionController::class, 'update'])->name('lineas.estaciones.update');
        Route::delete('lineas/{linea}/estaciones/{estacion}', [LineaEstacionController::class, 'destroy'])->name('lineas.estaciones.destroy');

        Route::resource('pilotos', PilotoController::class)->parameters(['pilotos' => 'piloto']);
        Route::post('pilotos/{piloto}/historial', [HistorialEducativoController::class, 'store'])->name('pilotos.historial.store');
        Route::delete('pilotos/{piloto}/historial/{historial}', [HistorialEducativoController::class, 'destroy'])->name('pilotos.historial.destroy');

        Route::resource('usuarios', UsuarioController::class)->except(['show', 'destroy'])->parameters(['usuarios' => 'usuario']);
    });
});
