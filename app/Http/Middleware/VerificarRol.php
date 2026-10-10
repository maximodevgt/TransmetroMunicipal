<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe una ruta a uno o varios roles. Uso: ->middleware('rol:administrador').
 */
class VerificarRol
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless(in_array($request->user()?->rol, $roles, true), 403, 'No tiene permiso para realizar esta acción.');

        return $next($request);
    }
}
