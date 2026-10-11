<?php

namespace Tests\Feature;

use App\Models\EnlaceCompartido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Compartir una dirección.
 *
 * El enlace corto existe porque una dirección **no tiene URL propia** en este
 * sitio y no la va a tener: la altura es infinita y una página por altura es
 * lo que dejó al sitio con 18,25 M de URLs descubiertas y 26.335 indexadas.
 *
 * De ahí lo que estos casos cuidan: que el botón le dé al visitante algo que
 * pegar en WhatsApp, y que eso no le agregue **ni una URL indexable** al
 * corpus. Las dos cosas a la vez, o no sirve.
 */
class CompartirTest extends TestCase
{
    use RefreshDatabase;

    private const CIUDAD = 3547640;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('continentes')->insert([
            'id' => 'NA', 'nombre' => 'North America', 'url' => 'north-america',
            'centro_x' => 45, 'centro_y' => -100, 'zoom' => 3, 'descripcion' => '',
        ]);
        DB::table('paises')->insert([
            'id' => 'CU', 'nombre' => 'Cuba', 'url' => 'cuba', 'continente_id' => 'NA',
            'centro_x' => 22, 'centro_y' => -80, 'zoom' => 6,
        ]);
        DB::table('provincias')->insert([
            'id' => 'CU.02', 'nombre' => 'La Habana', 'url' => 'la-habana-cuba', 'pais_id' => 'CU',
            'centro_x' => 23.1, 'centro_y' => -82.3,
        ]);
        DB::table('ciudades')->insert([
            'id' => self::CIUDAD,
            'nombre' => 'Marianao', 'nombre_min' => 'marianao', 'url' => 'marianao-havana-cuba',
            'fcode' => 'PPLA', 'population' => 136020,
            'pais_id' => 'CU', 'provincia_id' => 'CU.02', 'departamento_id' => 'CU.02.1',
            'centro_x' => 23.08, 'centro_y' => -82.43,
            'limite_sw_x' => 22.9, 'limite_sw_y' => -82.6,
            'limite_ne_x' => 23.2, 'limite_ne_y' => -82.3,
            'zoom' => 13, 'visitas' => 0, 'tiene_calles' => true,
        ]);
        DB::table('busquedas')->insert([
            'ciudad_id' => self::CIUDAD,
            'nombre_calle' => 'calle 23', 'nombre_calle_compacto' => 'calle23',
            'cantidad_busquedas' => 3,
        ]);
    }

    /** @param  array<string, mixed>  $extra */
    private function compartir(array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('Origin', config('app.url'))
            ->postJson('/api/compartir', array_merge([
                'ciudad_id' => self::CIUDAD,
                'calle' => 'calle 23',
                'altura' => '456',
            ], $extra));
    }

    // ── crear el enlace ──────────────────────────────────────────────────

    public function test_devuelve_una_url_corta(): void
    {
        $respuesta = $this->compartir()->assertOk();

        $enlace = EnlaceCompartido::sole();

        $respuesta->assertExactJson(['url' => url('/r/'.$enlace->codigo)]);
        $this->assertSame(self::CIUDAD, $enlace->ciudad_id);
        $this->assertSame('calle 23', $enlace->calle);
        $this->assertSame('456', $enlace->altura);
        $this->assertTrue($enlace->expires_at->isFuture());
    }

    /**
     * El botón se puede apretar muchas veces —es gratis— y compartir dos veces
     * la misma dirección tiene que dar el mismo enlace, no dos filas.
     */
    public function test_la_misma_direccion_reusa_el_enlace(): void
    {
        $primera = $this->compartir()->json('url');
        $segunda = $this->compartir()->json('url');

        $this->assertSame($primera, $segunda);
        $this->assertSame(1, EnlaceCompartido::count());
    }

    public function test_una_ciudad_que_no_existe_no_crea_nada(): void
    {
        $this->compartir(['ciudad_id' => 99999999])->assertStatus(422);

        $this->assertSame(0, EnlaceCompartido::count());
    }

    /**
     * La página que llama a este endpoint no tiene sesión —es lo que la hace
     * cacheable— así que no hay token CSRF. Lo que protege la escritura es que
     * la petición venga del propio sitio.
     */
    public function test_una_peticion_de_otro_origen_se_rechaza(): void
    {
        $this->withHeader('Origin', 'https://otro-sitio.example')
            ->postJson('/api/compartir', [
                'ciudad_id' => self::CIUDAD, 'calle' => 'calle 23', 'altura' => '456',
            ])
            ->assertForbidden();

        $this->postJson('/api/compartir', [
            'ciudad_id' => self::CIUDAD, 'calle' => 'calle 23', 'altura' => '456',
        ])->assertForbidden();

        $this->assertSame(0, EnlaceCompartido::count());
    }

    // ── abrir el enlace ──────────────────────────────────────────────────

    public function test_el_enlace_abre_la_ficha_con_la_direccion_puesta(): void
    {
        $codigo = EnlaceCompartido::paraLaDireccion(self::CIUDAD, 'calle 23', '456', 'en')->codigo;

        $this->get("/r/{$codigo}")
            ->assertOk()
            ->assertSee('Marianao')
            // La dirección le llega al componente, que la busca al montarse.
            ->assertSee('calle 23', false)
            ->assertSee('456', false);
    }

    /**
     * Y NO agrega una URL indexable. Es la mitad del motivo de existir del
     * enlace corto: el sitio viene de una penalización por tener 18,25 M de
     * URLs, y una por dirección compartida sería volver a empezar.
     */
    public function test_la_pagina_compartida_no_se_indexa(): void
    {
        $codigo = EnlaceCompartido::paraLaDireccion(self::CIUDAD, 'calle 23', '456', 'en')->codigo;

        $html = $this->get("/r/{$codigo}")->assertOk()->getContent();

        $this->assertStringContainsString('<meta name="robots" content="noindex,follow">', $html);

        // Y el canonical manda a la ficha, que sí es una página del sitio.
        preg_match('/<link rel="canonical" href="([^"]*)">/', $html, $canonical);
        $this->assertStringEndsWith('/cuba/la-habana/marianao-3547640', $canonical[1]);

        // Sin hreflang: la pareja de idiomas es de la ficha, no de esto.
        $this->assertStringNotContainsString('hreflang', $html);
    }

    /** Un enlace que ya caducó manda a la home, no a un 404. */
    public function test_un_enlace_vencido_manda_a_la_home(): void
    {
        $enlace = EnlaceCompartido::paraLaDireccion(self::CIUDAD, 'calle 23', '456', 'en');
        $enlace->forceFill(['expires_at' => now()->subDay()])->save();

        $this->get("/r/{$enlace->codigo}")->assertRedirect('/');
    }

    public function test_un_codigo_inventado_manda_a_la_home(): void
    {
        $this->get('/r/aB3xK9pQ2m')->assertRedirect('/');
    }

    /**
     * El enlace se abre en el idioma en el que se compartió. Quien recibe uno
     * de un amigo cubano tiene que ver la página que vio él.
     */
    public function test_el_enlace_conserva_el_idioma_con_el_que_se_compartio(): void
    {
        $codigo = EnlaceCompartido::paraLaDireccion(self::CIUDAD, 'calle 23', '456', 'es')->codigo;

        $this->get("/r/{$codigo}")
            ->assertOk()
            ->assertSee('<html lang="es"', false)
            ->assertSee('Calles de Marianao');
    }

    /** Y cuenta cuántas veces se abrió, que es la única métrica que hay. */
    public function test_cuenta_las_visitas(): void
    {
        $enlace = EnlaceCompartido::paraLaDireccion(self::CIUDAD, 'calle 23', '456', 'en');

        $this->get("/r/{$enlace->codigo}")->assertOk();
        $this->get("/r/{$enlace->codigo}")->assertOk();

        $this->assertSame(2, $enlace->fresh()->visitas);
    }

    /**
     * Una ciudad puede perder su ficha entre que se compartió y que se abre
     * el mensaje: la regla de ciudad-con-contenido la saca del sitio si se
     * queda sin calles. El enlace no puede reventar por eso.
     */
    public function test_si_la_ciudad_perdio_su_ficha_manda_a_la_home(): void
    {
        $enlace = EnlaceCompartido::paraLaDireccion(self::CIUDAD, 'calle 23', '456', 'en');

        DB::table('ciudades')->where('id', self::CIUDAD)->update(['tiene_calles' => false]);

        $this->get("/r/{$enlace->codigo}")->assertRedirect('/');
    }

    /** El botón está en la ficha, con su respaldo para cuando no hay API. */
    public function test_la_ficha_lleva_el_boton_de_compartir(): void
    {
        $this->get('/cuba/la-habana/marianao-3547640')
            ->assertOk()
            ->assertSee('compartir()', false)
            ->assertSee('copiarDireccion()', false)
            ->assertSee('Share', false);
    }
}
