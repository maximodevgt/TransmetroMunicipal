<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Menú lateral en pantallas bajas: solo la lista de enlaces se desplaza; el logo y "Cerrar sesión" quedan fijos.
 */
class MenuLateralTest extends TestCase
{
    public function test_la_lista_de_enlaces_tiene_su_propio_desplazamiento(): void
    {
        $html = $this->actingAs($this->administrador())->get(route('usuarios.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<aside data-menu class="[^"]*\bh-dvh\b[^"]*\bflex-col\b/', $html);
        $this->assertMatchesRegularExpression('/<nav data-menu-enlaces aria-label="Menú principal" class="[^"]*\bmin-h-0\b[^"]*\bflex-1\b[^"]*\boverflow-y-auto\b/', $html);
        // El logo y la tarjeta con "Cerrar sesión" no se encogen ni se desplazan.
        $this->assertStringContainsString('<div class="flex shrink-0 items-center gap-3 px-6 py-6">', $html);
        $this->assertStringContainsString('<div class="m-3 shrink-0 rounded-2xl bg-white/15 p-4">', $html);
    }

    public function test_el_enlace_activo_se_marca_para_mostrarlo_al_cargar(): void
    {
        $html = $this->actingAs($this->administrador())->get(route('usuarios.index'))->getContent();

        preg_match('/<nav data-menu-enlaces.*?<\/nav>/s', $html, $menu);
        $this->assertSame(1, substr_count($menu[0], 'aria-current="page"'), 'Un solo enlace activo en el menú.');
        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('usuarios.index'), '/').'"\s+aria-current="page"/', $html);
        $this->assertStringContainsString("querySelector('[aria-current=\"page\"]')", file_get_contents(resource_path('js/app.js')));
        $this->assertStringContainsString('.menu-desplazable', file_get_contents(resource_path('css/app.css')));
    }
}
