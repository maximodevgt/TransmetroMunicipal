<?php

use App\Exceptions\ReglaNegocioException;
use App\Http\Middleware\VerificarRol;
use App\Http\Middleware\VerificarUsuarioActivo;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Railway atiende el HTTPS en su proxy y reenvía la petición a la aplicación.
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'rol' => VerificarRol::class,
            'activo' => VerificarUsuarioActivo::class,
        ]);
        $middleware->redirectUsersTo('/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Datos de la tarjeta bancaria simulada: nunca se guardan en la sesión al volver al formulario con errores.
        $exceptions->dontFlash(['numero', 'cvv', 'vencimiento']);
        $exceptions->render(fn (ReglaNegocioException $e, Request $request) => $request->expectsJson()
            ? response()->json(['message' => $e->getMessage()], 422)
            : back()->withInput()->with('error', $e->getMessage()));
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
