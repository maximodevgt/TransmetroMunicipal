<?php

use App\Http\Controllers\AccesoController;
use App\Http\Controllers\AlertaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BitacoraController;
use App\Http\Controllers\BusController;
use App\Http\Controllers\ConsultaSaldoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EstacionController;
use App\Http\Controllers\GuardiaController;
use App\Http\Controllers\HistorialEducativoController;
use App\Http\Controllers\LineaController;
use App\Http\Controllers\LineaEstacionController;
use App\Http\Controllers\MiEstacionController;
use App\Http\Controllers\ParqueoController;
use App\Http\Controllers\PilotoController;
use App\Http\Controllers\RecargaController;
use App\Http\Controllers\RecorridoController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\SeguimientoController;
use App\Http\Controllers\TarjetaController;
use App\Http\Controllers\TorniqueteController;
use App\Http\Controllers\TransaccionController;
use App\Http\Controllers\UsuarioController;
use App\Reportes\Catalogo;
use Illuminate\Support\Facades\Route;

// Consulta pública de saldo (sin sesión), con límite de intentos.
Route::get('/consulta-saldo', [ConsultaSaldoController::class, 'show'])->name('consulta-saldo');
Route::post('/consulta-saldo', [ConsultaSaldoController::class, 'consultar'])->middleware('throttle:consulta-saldo')->name('consulta-saldo.consultar');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:6,1');
});

// Cada límite de intentos lleva su propio prefijo: sin él, Laravel cuenta todas las rutas del usuario juntas
// y el sondeo de alertas y del mapa agotaría el cupo de las recargas.
Route::middleware(['auth', 'activo'])->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/panel/graficas', [DashboardController::class, 'graficas'])->middleware('throttle:60,1,graficas')->name('panel.graficas');
    Route::get('/alertas/nuevas', [AlertaController::class, 'nuevas'])->middleware('throttle:60,1,alertas')->name('alertas.nuevas');

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

    // Seguimiento en vivo: el mapa consulta los datos cada 3 s y avanza la simulación si está activa.
    Route::get('seguimiento', [SeguimientoController::class, 'index'])->name('seguimiento.index');
    Route::get('seguimiento/datos', [SeguimientoController::class, 'datos'])->middleware('throttle:120,1,seguimiento-datos')->name('seguimiento.datos');
    Route::post('seguimiento/avanzar', [SeguimientoController::class, 'avanzar'])->middleware('throttle:120,1,seguimiento-avanzar')->name('seguimiento.avanzar');
    Route::post('seguimiento/simulacion', [SeguimientoController::class, 'simulacion'])->middleware('rol:administrador')->name('seguimiento.simulacion');

    // Tarjeta Transmetro (ambos roles). Pagos SIMULADOS; el saldo lo calculan los triggers.
    Route::resource('tarjetas', TarjetaController::class)->only(['index', 'create', 'store', 'show'])->parameters(['tarjetas' => 'tarjeta']);
    Route::patch('tarjetas/{tarjeta}/bloquear', [TarjetaController::class, 'bloquear'])->name('tarjetas.bloquear');
    Route::patch('tarjetas/{tarjeta}/desbloquear', [TarjetaController::class, 'desbloquear'])->name('tarjetas.desbloquear');
    Route::get('tarjetas/{tarjeta}/recargar', [RecargaController::class, 'create'])->name('recargas.create');
    Route::post('tarjetas/{tarjeta}/recargas/efectivo', [RecargaController::class, 'efectivo'])->middleware('rol:operador')->name('recargas.efectivo');
    Route::post('tarjetas/{tarjeta}/recargas/en-linea', [RecargaController::class, 'enLinea'])->middleware('throttle:20,1,recargas')->name('recargas.en-linea');
    Route::get('transacciones', [TransaccionController::class, 'index'])->name('transacciones.index');
    Route::get('transacciones/{transaccion}', [TransaccionController::class, 'show'])->name('transacciones.show');
    Route::get('torniquete', [TorniqueteController::class, 'index'])->name('torniquete.index');
    Route::get('torniquete/{acceso}', [TorniqueteController::class, 'show'])->name('torniquete.show');
    Route::post('torniquete/{acceso}/validar', [TorniqueteController::class, 'validar'])->middleware('throttle:120,1,torniquete')->name('torniquete.validar');

    // Reportes (ambos roles; el operador ve lo de su estación): pantalla, impresión y CSV.
    Route::get('reportes', [ReporteController::class, 'index'])->name('reportes.index');
    Route::whereIn('clave', array_keys(Catalogo::REPORTES))->group(function () {
        Route::get('reportes/{clave}', [ReporteController::class, 'show'])->name('reportes.show');
        Route::get('reportes/{clave}/imprimir', [ReporteController::class, 'imprimir'])->name('reportes.imprimir');
        Route::get('reportes/{clave}/csv', [ReporteController::class, 'csv'])->middleware('throttle:30,1,reportes-csv')->name('reportes.csv');
    });

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
        Route::patch('lineas/{linea}/estaciones/{estacion}/mover', [LineaEstacionController::class, 'mover'])->name('lineas.estaciones.mover');

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
