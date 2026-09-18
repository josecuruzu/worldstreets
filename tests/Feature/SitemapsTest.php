<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Los sitemaps.
 *
 * El sitio viejo publica 21.140 URLs —portada, continentes, países y
 * provincias— y **ninguna ciudad**, que es justo donde está el contenido: 704
 * fichas de ciudad se llevaron 3.684 de los 6.930 clics de 16 meses sin estar
 * declaradas en ningún lado.
 *
 * Lo que se declara acá es exactamente lo que la regla de ciudad-con-contenido
 * dejó en pie. Declarar de más es pedirle a un rastreador que hace 240
 * peticiones diarias que gaste una en nada.
 */
class SitemapsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('continentes')->insert([
            'id' => 'AF', 'nombre' => 'Africa', 'url' => 'africa',
            'centro_x' => 0, 'centro_y' => 20, 'zoom' => 3, 'descripcion' => '',
        ]);
        DB::table('paises')->insert([
            ['id' => 'ZA', 'nombre' => 'South Africa', 'url' => 'south-africa', 'continente_id' => 'AF',
                'capital' => 'Pretoria', 'population' => 59308690, 'centro_x' => -30, 'centro_y' => 25, 'zoom' => 5],
            // Un país sin ninguna ciudad con página: no tiene que aparecer.
            ['id' => 'NA', 'nombre' => 'Namibia', 'url' => 'namibia', 'continente_id' => 'AF',
                'capital' => 'Windhoek', 'population' => 2540905, 'centro_x' => -22, 'centro_y' => 17, 'zoom' => 5],
        ]);
        DB::table('provincias')->insert([
            ['id' => 'ZA.11', 'nombre' => 'Western Cape', 'url' => 'western-cape-south-africa', 'pais_id' => 'ZA',
                'centro_x' => -33.2, 'centro_y' => 21.8],
            // Una provincia sin ciudades con página.
            ['id' => 'ZA.06', 'nombre' => 'Gauteng', 'url' => 'gauteng-south-africa', 'pais_id' => 'ZA',
                'centro_x' => -26.0, 'centro_y' => 28.1],
            ['id' => 'NA.21', 'nombre' => 'Khomas', 'url' => 'khomas-namibia', 'pais_id' => 'NA',
                'centro_x' => -22.5, 'centro_y' => 17.0],
        ]);

        $this->crearCiudad(3369157, 'Cape Town', 'ZA', 'ZA.11', conCalles: true);
        $this->crearCiudad(3361414, 'Stellenbosch', 'ZA', 'ZA.11', conCalles: false);
        $this->crearCiudad(3352136, 'Windhoek', 'NA', 'NA.21', conCalles: false);
    }

    private function crearCiudad(int $id, string $nombre, string $pais, string $provincia, bool $conCalles): void
    {
        DB::table('ciudades')->insert([
            'id' => $id,
            'nombre' => $nombre,
            'nombre_min' => strtolower($nombre),
            'url' => str_replace(' ', '-', strtolower($nombre)),
            'fcode' => 'PPL',
            'population' => 100000,
            'pais_id' => $pais,
            'provincia_id' => $provincia,
            'departamento_id' => $provincia.'.1',
            'centro_x' => -33.9, 'centro_y' => 18.4,
            'limite_sw_x' => -34, 'limite_sw_y' => 18.3,
            'limite_ne_x' => -33.8, 'limite_ne_y' => 18.5,
            'zoom' => 13,
            'visitas' => 0,
            'tiene_calles' => $conCalles,
        ]);
    }

    public function test_el_indice_declara_un_archivo_por_pais_con_contenido(): void
    {
        $respuesta = $this->get('/sitemap.xml')->assertOk();

        $respuesta->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $respuesta->assertSee('<sitemapindex', false);
        $respuesta->assertSee('/sitemaps/lugares.xml', false);
        $respuesta->assertSee('/sitemaps/south-africa.xml', false);

        // Namibia no tiene ninguna ciudad con página.
        $respuesta->assertDontSee('/sitemaps/namibia.xml', false);
    }

    public function test_el_sitemap_de_ciudades_declara_las_que_tienen_pagina(): void
    {
        $respuesta = $this->get('/sitemaps/south-africa.xml')->assertOk();

        $respuesta->assertSee('/south-africa/western-cape/cape-town-3369157', false);
        $respuesta->assertDontSee('stellenbosch', false);
    }

    /**
     * Una ficha de ciudad sin calles responde 410. Declararla en el sitemap
     * sería mandar al rastreador a buscar una página que se acaba de eliminar
     * a propósito.
     */
    public function test_no_declara_ciudades_que_responden_410(): void
    {
        $this->get('/south-africa/western-cape/stellenbosch-3361414')->assertStatus(410);

        $this->get('/sitemaps/south-africa.xml')
            ->assertOk()
            ->assertDontSee('stellenbosch-3361414', false);
    }

    public function test_el_sitemap_de_lugares_trae_paises_y_provincias_con_contenido(): void
    {
        $respuesta = $this->get('/sitemaps/lugares.xml')->assertOk();

        $respuesta->assertSee('<urlset', false);
        $respuesta->assertSee('/south-africa<', false);
        $respuesta->assertSee('/south-africa/western-cape<', false);

        // Gauteng y Namibia no tienen ciudades con página: su ficha sería una
        // lista vacía.
        $respuesta->assertDontSee('/south-africa/gauteng', false);
        $respuesta->assertDontSee('/namibia', false);
    }

    /** Un país sin ciudades con página no tiene sitemap propio. */
    public function test_un_pais_sin_ciudades_da_404(): void
    {
        $this->get('/sitemaps/namibia.xml')->assertStatus(404);
        $this->get('/sitemaps/pais-que-no-existe.xml')->assertStatus(404);
    }

    /**
     * El sitio viejo publicaba `sitemaps.xml`. Google ya lo conoce, así que se
     * redirige en vez de dejarlo morir.
     */
    public function test_los_sitemaps_viejos_redirigen_al_indice(): void
    {
        foreach (['/sitemaps.xml', '/sitemap'] as $vieja) {
            $this->get($vieja)
                ->assertStatus(301)
                ->assertRedirect('/sitemap.xml');
        }
    }

    /**
     * Las rutas de sitemap tienen forma de slug y comparten espacio con
     * `/{pais}`. Si `/{pais}` ganara, `/sitemap.xml` sería una ficha de país.
     */
    public function test_las_rutas_de_sitemap_le_ganan_a_las_de_lugar(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    }

    /** El robots.txt tiene que apuntar al índice nuevo. */
    public function test_el_robots_declara_el_sitemap(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));

        $this->assertStringContainsString('Sitemap: https://worldstreets.global/sitemap.xml', $robots);

        // Las URLs del Cake no se bloquean: lo que se bloquea no se rastrea, y
        // un 410 que Google no llega a leer no limpia nada.
        $this->assertStringNotContainsString('Disallow: /view/find', $robots);
        $this->assertStringNotContainsString('Disallow: /country', $robots);
    }
}
