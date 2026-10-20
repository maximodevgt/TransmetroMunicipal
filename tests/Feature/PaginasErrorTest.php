<?php

namespace Tests\Feature;

use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Páginas de error en español, con el diseño del sistema y el botón para volver al panel.
 */
class PaginasErrorTest extends TestCase
{
    private function esPaginaDeError(TestResponse $respuesta, int $codigo, string $titulo): void
    {
        $respuesta->assertStatus($codigo)
            ->assertSee("<title>{$codigo} · {$titulo} · Transmetro Municipal</title>", false)
            ->assertSee('data-error="'.$codigo.'"', false)
            ->assertSee($titulo)
            ->assertSee('Volver al panel')
            ->assertSee('Página anterior')
            ->assertDontSee('Not Found')
            ->assertDontSee('Server Error')
            ->assertDontSee('Forbidden');
    }

    public function test_404_en_espanol(): void
    {
        $this->esPaginaDeError($this->actingAs($this->administrador())->get('/una-pagina-que-no-existe'), 404, 'Página no encontrada');
        $this->esPaginaDeError($this->get('/estaciones/999999'), 404, 'Página no encontrada');
    }

    public function test_403_en_espanol_con_el_motivo(): void
    {
        $respuesta = $this->actingAs($this->operadorTrebol())->get(route('estaciones.show', 1));
        $this->esPaginaDeError($respuesta, 403, 'Acceso restringido');
        $respuesta->assertSee('Solo puede ver la ficha de su estación.');
    }

    public function test_419_en_espanol(): void
    {
        Route::middleware('web')->get('/prueba-419', fn () => throw new TokenMismatchException);
        $this->esPaginaDeError($this->get('/prueba-419'), 419, 'La sesión expiró');
    }

    public function test_429_en_espanol(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', ['email' => 'admin@transmetro.gt', 'password' => 'incorrecta']);
        }
        $respuesta = $this->post('/login', ['email' => 'admin@transmetro.gt', 'password' => 'incorrecta']);
        $this->esPaginaDeError($respuesta, 429, 'Demasiados intentos');
        $respuesta->assertSee('segundos y vuelva a intentarlo');
    }

    public function test_500_en_espanol_sin_detalles_tecnicos(): void
    {
        config(['app.debug' => false]);
        Route::middleware('web')->get('/prueba-500', fn () => throw new \RuntimeException('detalle interno QA'));

        $respuesta = $this->get('/prueba-500');
        $this->esPaginaDeError($respuesta, 500, 'Ocurrió un error inesperado');
        $respuesta->assertDontSee('detalle interno QA');
    }

    public function test_otros_errores_usan_la_pagina_general(): void
    {
        // GET a una ruta que solo acepta POST: 405.
        $this->esPaginaDeError($this->actingAs($this->administrador())->get('/logout'), 405, 'No se pudo atender la solicitud');
    }

    public function test_503_en_espanol(): void
    {
        $html = view('errors.503')->render();
        $this->assertStringContainsString('Sistema en mantenimiento', $html);
        $this->assertStringContainsString('Volver al panel', $html);
    }
}
