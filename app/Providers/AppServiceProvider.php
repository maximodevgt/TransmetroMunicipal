<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Consulta pública de saldo: 5 intentos por minuto por IP, para que no se adivinen códigos o DPI.
        RateLimiter::for('consulta-saldo', fn (Request $request) => Limit::perMinute(5)->by($request->ip())
            ->response(fn (Request $request, array $headers) => response()
                ->view('consulta-saldo', ['tarjeta' => null, 'movimientos' => collect(), 'bloqueado' => true], 429)
                ->withHeaders($headers)));
    }
}
