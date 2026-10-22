<?php

namespace Tests\Feature;

use App\Models\Acceso;
use App\Models\Alerta;
use App\Models\Estacion;
use App\Models\Tarjeta;
use App\Models\Transaccion;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Concerns\ConRecorridos;
use Tests\TestCase;

/**
 * Seguridad (RNF-01, RNF-02): sesión, roles, alcance del operador, CSRF, validaciones,
 * XSS, inyección SQL, contraseñas, límite de intentos, vencimiento de la sesión y "Recordarme".
 */
class SeguridadTest extends TestCase
{
    use ConRecorridos;

    /** Rutas que se usan sin iniciar sesión. */
    private const PUBLICAS = ['login', 'consulta-saldo', 'up', 'storage/{path}'];

    private function otraEstacion(): Estacion
    {
        return Estacion::where('id', '!=', $this->operadorTrebol()->estacion_id)->orderBy('id')->firstOrFail();
    }

    private function iniciarSesion(string $email, bool $recordar = false)
    {
        return $this->post('/login', ['email' => $email, 'password' => 'Transmetro2026', ...($recordar ? ['remember' => '1'] : [])]);
    }

    private function cookieRecordar(): string
    {
        return Auth::guard('web')->getRecallerName();
    }

    // --- Sesión y roles ---

    public function test_todas_las_rutas_privadas_piden_sesion(): void
    {
        $privadas = collect(Route::getRoutes()->getRoutes())->reject(fn ($r) => in_array($r->uri(), self::PUBLICAS, true));

        foreach ($privadas as $ruta) {
            $this->assertContains('auth', $ruta->gatherMiddleware(), "La ruta {$ruta->uri()} no pide sesión.");
            $this->assertContains('activo', $ruta->gatherMiddleware(), "La ruta {$ruta->uri()} no revisa que el usuario esté activo.");
        }

        // Y de verdad: sin sesión, cada pantalla manda al inicio de sesión (con cualquier id en la URL).
        foreach ($privadas->filter(fn ($r) => in_array('GET', $r->methods(), true)) as $ruta) {
            // {clave} solo acepta nombres de reporte; los demás parámetros, cualquier id.
            $url = preg_replace('/\{[^}]+\}/', '1', str_replace('{clave}', 'lineas', $ruta->uri()));
            $this->get('/'.ltrim($url, '/'))->assertRedirect(route('login'));
        }
    }

    public function test_el_operador_no_abre_nada_de_otra_estacion_ni_escribiendo_la_url(): void
    {
        $otra = $this->otraEstacion();
        $alerta = Alerta::create(['estacion_id' => $otra->id, 'tipo' => 'incidencia', 'mensaje' => 'Incidencia QA ajena', 'estado' => 'activa']);
        $acceso = Acceso::where('estacion_id', $otra->id)->firstOrFail();
        $recorrido = $this->recorridoEnCurso(); // línea de prueba con Centra Sur, Monte María y Javier: no pasa por Trébol
        $tarjeta = Tarjeta::where('estado', 'activa')->firstOrFail();
        DB::table('transacciones')->insert(['tarjeta_id' => $tarjeta->id, 'estacion_id' => $otra->id, 'tipo' => 'pago', 'monto' => 1, 'estado' => 'rechazada', 'motivo_rechazo' => 'Prueba QA', 'created_at' => now()]);
        $movimiento = Transaccion::latest('id')->firstOrFail();

        $this->actingAs($this->operadorTrebol());
        foreach ([
            route('estaciones.show', $otra),
            route('alertas.show', $alerta),
            route('torniquete.show', $acceso),
            route('recorridos.show', $recorrido),
            route('transacciones.show', $movimiento),
        ] as $url) {
            $this->get($url)->assertForbidden();
        }

        $this->patch(route('alertas.atender', $alerta))->assertForbidden();
        $this->postJson(route('torniquete.validar', $acceso), ['codigo' => $tarjeta->codigo])->assertForbidden();
        $this->post(route('recorridos.llegadas.store', $recorrido), ['estacion_id' => $otra->id, 'pasajeros_a_bordo' => 10])->assertForbidden();
        $this->assertSame('activa', $alerta->fresh()->estado);

        // Su propia estación sí la ve; los listados y reportes solo traen lo suyo.
        $this->get(route('estaciones.show', $this->operadorTrebol()->estacion_id))->assertOk();
        $this->get(route('recorridos.index'))->assertOk()->assertDontSee('href="'.route('recorridos.show', $recorrido).'"', false);
        $this->get(route('reportes.show', ['alertas', 'estacion_id' => $otra->id, 'periodo' => 'hoy']))->assertOk()->assertDontSee('Incidencia QA ajena');
        $this->get(route('estaciones.index'))->assertOk()->assertDontSee('href="'.route('estaciones.show', $otra).'"', false);
    }

    public function test_el_operador_no_usa_las_pantallas_del_administrador(): void
    {
        $estacion = Estacion::firstOrFail();
        $this->actingAs($this->operadorTrebol());

        foreach ([
            route('estaciones.create'), route('estaciones.edit', $estacion), route('lineas.create'), route('buses.create'),
            route('parqueos.create'), route('pilotos.index'), route('usuarios.index'), route('usuarios.create'),
            route('bitacora.index'), route('recorridos.create'), route('estaciones.accesos.create', $estacion),
        ] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->delete(route('estaciones.destroy', $estacion))->assertForbidden();
        $this->post(route('seguimiento.simulacion'), ['accion' => 'iniciar'])->assertForbidden();
        $this->assertModelExists($estacion);
    }

    // --- CSRF ---

    public function test_los_formularios_sin_token_csrf_se_rechazan(): void
    {
        $this->assertContains(PreventRequestForgery::class, app(Kernel::class)->getMiddlewareGroups()['web']);

        // Las pruebas desactivan el CSRF; aquí se usa el middleware real.
        $middleware = new class(app(), app('encrypter')) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        };
        $sesion = app('session.store');
        $sesion->start();

        $sinToken = Request::create('/logout', 'POST');
        $sinToken->setLaravelSession($sesion);
        try {
            $middleware->handle($sinToken, fn () => response('pasó'));
            $this->fail('Un POST sin token CSRF no debe pasar.');
        } catch (TokenMismatchException) {
            $this->assertTrue(true);
        }

        $conToken = Request::create('/logout', 'POST', ['_token' => $sesion->token()]);
        $conToken->setLaravelSession($sesion);
        $this->assertSame('pasó', $middleware->handle($conToken, fn () => response('pasó'))->getContent());
    }

    public function test_todos_los_formularios_post_llevan_el_token_csrf(): void
    {
        $vistas = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($vistas as $archivo) {
            if (! str_ends_with($archivo->getFilename(), '.blade.php')) {
                continue;
            }
            $contenido = file_get_contents($archivo->getPathname());
            preg_match_all('/<form\b[^>]*method="post"[^>]*>(.*?)<\/form>/is', $contenido, $formularios);
            foreach ($formularios[1] as $cuerpo) {
                $this->assertStringContainsString('@csrf', $cuerpo, "Un formulario POST de {$archivo->getFilename()} no lleva @csrf.");
            }
        }
        // Las peticiones fetch que modifican datos mandan el token en la cabecera.
        foreach (['torniquete.js', 'seguimiento.js', 'alertas.js'] as $js) {
            $codigo = file_get_contents(resource_path("js/{$js}"));
            if (str_contains($codigo, "method: 'POST'")) {
                $this->assertStringContainsString('X-CSRF-TOKEN', $codigo, "{$js} hace POST sin el token.");
            }
        }
    }

    // --- Validaciones, XSS e inyección SQL ---

    public function test_las_validaciones_salen_en_espanol(): void
    {
        $this->actingAs($this->administrador())->post(route('estaciones.store'), [])
            ->assertSessionHasErrors(['nombre' => 'El campo nombre es obligatorio.']);

        $this->post('/logout');
        $this->post('/login', ['email' => 'no-es-correo', 'password' => ''])
            ->assertSessionHasErrors(['email' => 'El campo correo debe ser un correo válido.', 'password' => 'El campo contraseña es obligatorio.']);
    }

    public function test_el_texto_del_usuario_se_escapa_en_la_pantalla(): void
    {
        $estacion = Estacion::create(['municipalidad_id' => 1, 'nombre' => '<script>alert("xss")</script> QA', 'estado' => 'activa']);

        $this->actingAs($this->administrador())->get(route('estaciones.index', ['buscar' => 'QA']))
            ->assertOk()
            ->assertDontSee('<script>alert("xss")</script>', false)
            ->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt; QA', false);
        $this->get(route('estaciones.show', $estacion))->assertOk()->assertDontSee('<script>alert("xss")', false);
    }

    public function test_los_filtros_con_inyeccion_sql_no_hacen_nada(): void
    {
        $tarjetas = Tarjeta::count();
        $this->actingAs($this->administrador());

        $this->get(route('estaciones.index', ['buscar' => "' OR '1'='1"]))->assertOk()->assertSee('No se encontraron estaciones.');
        $this->get(route('tarjetas.index', ['buscar' => "x'; DROP TABLE tarjetas; --"]))->assertOk();
        $this->get(route('reportes.show', ['alertas', 'tipo' => "' OR 1=1 --", 'desde' => "2026-01-01' OR '1'='1"]))->assertOk();
        $this->get(route('recorridos.index', ['linea_id' => '1 OR 1=1', 'fecha' => "'; DELETE FROM recorridos; --"]))->assertOk();
        $this->assertSame($tarjetas, Tarjeta::count());
    }

    // --- Contraseñas, inicio de sesión y vencimiento ---

    public function test_las_contrasenas_se_guardan_con_hash(): void
    {
        $this->assertSame(0, User::where('password', 'not like', '$2y$%')->count(), 'Todas las contraseñas deben ser hash bcrypt.');

        $usuario = User::create(['name' => 'Usuario QA', 'email' => 'usuario.qa@transmetro.gt', 'password' => 'ClaveSegura2026', 'rol' => 'operador', 'activo' => true]);
        $guardada = DB::table('users')->where('id', $usuario->id)->value('password');
        $this->assertNotSame('ClaveSegura2026', $guardada);
        $this->assertTrue(Hash::check('ClaveSegura2026', $guardada));
    }

    public function test_el_inicio_de_sesion_limita_los_intentos(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->post('/login', ['email' => 'admin@transmetro.gt', 'password' => 'incorrecta'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => 'admin@transmetro.gt', 'password' => 'incorrecta'])->assertStatus(429);
        $this->assertGuest();
    }

    public function test_el_usuario_inactivo_no_entra_y_si_ya_estaba_queda_fuera(): void
    {
        $operador = $this->operadorTrebol();
        $operador->update(['activo' => false]);

        $this->iniciarSesion($operador->email)
            ->assertSessionHasErrors(['email' => 'Su usuario está desactivado. Comuníquese con el administrador.']);
        $this->assertGuest();

        $this->actingAs($operador)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_la_sesion_vence_y_se_renueva_al_entrar(): void
    {
        $this->assertLessThanOrEqual(120, config('session.lifetime'));
        $this->assertTrue(config('session.expire_on_close'));
        $this->assertTrue(config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
        $this->assertSame(60 * 24 * 30, config('auth.guards.web.remember'), '"Recordarme" dura 30 días.');

        $this->get('/login');
        $antes = session()->getId();
        $this->iniciarSesion('admin@transmetro.gt')->assertRedirect(route('dashboard'));
        $this->assertNotSame($antes, session()->getId(), 'Al entrar se cambia el id de la sesión.');

        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    // --- Recordarme ---

    public function test_con_recordarme_se_crea_la_cookie_remember_web(): void
    {
        $this->iniciarSesion('admin@transmetro.gt', recordar: true)->assertRedirect(route('dashboard'))->assertCookie($this->cookieRecordar());
        $this->assertStringStartsWith('remember_web_', $this->cookieRecordar());
    }

    public function test_sin_recordarme_no_hay_cookie_remember_web(): void
    {
        $this->iniciarSesion('admin@transmetro.gt')->assertRedirect(route('dashboard'))->assertCookieMissing($this->cookieRecordar());
    }

    public function test_recordarme_entra_aunque_la_sesion_haya_vencido(): void
    {
        $cookie = $this->iniciarSesion('admin@transmetro.gt', recordar: true)->getCookie($this->cookieRecordar())->getValue();

        // Sesión vencida: sin datos de sesión ni usuario en memoria, solo la cookie de "Recordarme".
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->withCookie($this->cookieRecordar(), $cookie)->get(route('dashboard'))->assertOk();
        $this->assertAuthenticatedAs($this->administrador());
    }

    public function test_cerrar_sesion_borra_la_cookie_de_recordarme(): void
    {
        $cookie = $this->iniciarSesion('admin@transmetro.gt', recordar: true)->getCookie($this->cookieRecordar())->getValue();
        $token = $this->administrador()->remember_token;

        $this->withCookie($this->cookieRecordar(), $cookie)->post('/logout')->assertCookieExpired($this->cookieRecordar());
        $this->assertNotSame($token, $this->administrador()->remember_token, 'El token anterior deja de servir.');

        // La cookie vieja ya no abre la sesión.
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->withCookie($this->cookieRecordar(), $cookie)->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_recordarme_no_deja_entrar_a_un_usuario_desactivado(): void
    {
        $cookie = $this->iniciarSesion($this->operadorTrebol()->email, recordar: true)->getCookie($this->cookieRecordar())->getValue();
        $this->operadorTrebol()->update(['activo' => false]);

        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->withCookie($this->cookieRecordar(), $cookie)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_el_login_tiene_recordarme_y_el_boton_de_ver_la_contrasena(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('name="remember"', false)
            ->assertSee('Recordarme')
            ->assertSee('type="button" data-ver-clave="campo_password"', false)
            ->assertSee('aria-label="Mostrar contraseña"', false)
            ->assertSee('id="campo_password" type="password"', false);
    }
}
