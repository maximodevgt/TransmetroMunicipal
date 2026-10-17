<?php

use App\Http\Controllers\AccesoController;
use App\Http\Controllers\AlertaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BitacoraController;
use App\Http\Controllers\BusController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EstacionController;
use App\Http\Controllers\GuardiaController;
use App\Http\Controllers\HistorialEducativoController;
use App\Http\Controllers\LineaController;
use App\Http\Controllers\LineaEstacionController;
use App\Http\Controllers\MiEstacionController;
use App\Http\Controllers\ParqueoController;
use App\Http\Controllers\PilotoController;
use App\Http\Controllers\RecorridoController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:6,1');
});

Route::middleware(['auth', 'activo'])->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/alertas/nuevas', [AlertaController::class, 'nuevas'])->middleware('throttle:60,1')->name('alertas.nuevas');

    // Lectura para todos los roles; crear, editar y eliminar solo administrador (ver middleware() de cada controlador).
    Route::resource('lineas', LineaController::class)->parameters(['lineas' => 'linea']);
    Route::resource('estaciones', EstacionController::class)->parameters(['estaciones' => 'estacion']);
    Route::resource('buses', BusController::class)->parameters(['buses' => 'bus']);
    Route::resource('parqueos', ParqueoController::class)->parameters(['parqueos' => 'parqueo']);

    // Recorridos: iniciar y finalizar solo administrador; la llegada la registran ambos (el operador en su estación).
    Route::resource('recorridos', RecorridoController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('recorridos/{recorrido}/llegadas', [RecorridoController::class, 'llegada'])->name('recorridos.llegadas.store');
    Route::patch('recorridos/{recorrido}/finalizar', [RecorridoController::class, 'finalizar'])->name('recorridos.finalizar');
    Route::post('recorridos/{recorrido}/confirmar-espera', [RecorridoController::class, 'confirmarEspera'])->name('recorridos.confirmar-espera');
    Route::post('buses/{bus}/asignar-linea', [BusController::class, 'asignarLinea'])->name('buses.asignar-linea');

    // Alertas: el operador solo ve y atiende las de su estación (lo revisa el controlador).
    Route::get('alertas', [AlertaController::class, 'index'])->name('alertas.index');
    Route::get('alertas/{alerta}', [AlertaController::class, 'show'])->whereNumber('alerta')->name('alertas.show');
    Route::patch('alertas/{alerta}/atender', [AlertaController::class, 'atender'])->whereNumber('alerta')->name('alertas.atender');

    Route::middleware('rol:operador')->group(function () {
        Route::post('alertas/{alerta}/solicitar-unidad', [AlertaController::class, 'solicitarUnidad'])->whereNumber('alerta')->name('alertas.solicitar-unidad');
        Route::get('mi-estacion', [MiEstacionController::class, 'show'])->name('mi-estacion');
        Route::post('mi-estacion/pasajeros', [MiEstacionController::class, 'registrarEspera'])->name('mi-estacion.pasajeros');
    });

    Route::middleware('rol:administrador')->group(function () {
        Route::post('lineas/{linea}/estaciones', [LineaEstacionController::class, 'store'])->name('lineas.estaciones.store');
        Route::put('lineas/{linea}/estaciones/{estacion}', [LineaEstacionController::class, 'update'])->name('lineas.estaciones.update');
        Route::delete('lineas/{linea}/estaciones/{estacion}', [LineaEstacionController::class, 'destroy'])->name('lineas.estaciones.destroy');

        // Accesos dentro de su estación y guardias dentro de su acceso (rutas anidadas solo para crear).
        Route::resource('estaciones.accesos', AccesoController::class)->shallow()->except(['index', 'show'])
            ->parameters(['estaciones' => 'estacion', 'accesos' => 'acceso']);
        Route::resource('accesos.guardias', GuardiaController::class)->shallow()->except(['index', 'show'])
            ->parameters(['accesos' => 'acceso', 'guardias' => 'guardia']);

        Route::resource('pilotos', PilotoController::class)->parameters(['pilotos' => 'piloto']);
        Route::post('pilotos/{piloto}/historial', [HistorialEducativoController::class, 'store'])->name('pilotos.historial.store');
        Route::delete('pilotos/{piloto}/historial/{historial}', [HistorialEducativoController::class, 'destroy'])->name('pilotos.historial.destroy');

        Route::get('bitacora', [BitacoraController::class, 'index'])->name('bitacora.index');

        Route::resource('usuarios', UsuarioController::class)->except(['show', 'destroy'])->parameters(['usuarios' => 'usuario']);
    });
});
