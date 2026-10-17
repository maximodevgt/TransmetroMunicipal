<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_la_raiz_redirige_al_login_sin_sesion(): void
    {
        $this->get('/')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_la_pantalla_de_login_carga(): void
    {
        $this->get(route('login'))->assertOk();
    }
}
