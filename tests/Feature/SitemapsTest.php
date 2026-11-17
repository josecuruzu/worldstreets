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

        // El resto de la suite corre con el umbral en 1 (phpunit.xml); acá se
        // prueba la regla de verdad.
        config(['services.sitemap.minimo_calles' => 11]);

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

        $this->crearCiudad(3369157, 'Cape Town', 'ZA', 'ZA.11', conCalles: true, calles: 12);
        $this->crearCiudad(3361414, 'Stellenbosch', 'ZA', 'ZA.11', conCalles: false);
        $this->crearCiudad(3352136, 'Windhoek', 'NA', 'NA.21', conCalles: false);

        // El umbral del sitemap es 11 calles, inclusive.
        $this->crearCiudad(3366880, 'Paarl', 'ZA', 'ZA.11', conCalles: true, calles: 11);
        $this->crearCiudad(3382160, 'Franschhoek', 'ZA', 'ZA.11', conCalles: true, calles: 10);
        // Con calles, pero muy pocas: ni ella ni su provincia ni su país
        // tienen que declararse.
        $this->crearCiudad(964137, 'Pretoria', 'ZA', 'ZA.06', conCalles: true, calles: 5);
        $this->crearCiudad(3352137, 'Walvis Bay', 'NA', 'NA.21', conCalles: true, calles: 2);
    }

    private function crearCalles(int $ciudad, int $cantidad): void
    {
        $filas = [];

        for ($i = 1; $i <= $cantidad; $i++) {
            $filas[] = [
                'ciudad_id' => $ciudad,
                'nombre_calle' => "Calle {$i}",
                'nombre_calle_compacto' => "calle{$i}",
                'cantidad_busquedas' => 1,
            ];
        }

        DB::table('busquedas')->insert($filas);
    }

    private function crearCiudad(int $id, string $nombre, string $pais, string $provincia, bool $conCalles, int $calles = 0): void
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

        $this->crearCalles($id, $calles);
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
     * Medido el 17-nov-2026: las fichas que Google rastreó y no indexó tienen
     * mediana de 1 calle, y las que tienen clics entre 34 y 161. Las de 10
     * calles o menos no se declaran; con 11 ya sí.
     */
    public function test_el_sitemap_no_declara_fichas_con_pocas_calles(): void
    {
        $this->get('/sitemaps/south-africa.xml')
            ->assertOk()
            ->assertSee('cape-town-3369157', false)
            ->assertSee('paarl-3366880', false)
            ->assertDontSee('franschhoek', false)
            ->assertDontSee('pretoria', false);
    }

    /** No declararla no la elimina: sigue siendo navegable. */
    public function test_una_ficha_fuera_del_sitemap_sigue_respondiendo_200(): void
    {
        $this->get('/south-africa/western-cape/franschhoek-3382160')->assertOk();
    }

    /**
     * Si todas las fichas de una provincia o de un país son chicas, no se
     * declara ni su ficha ni su archivo: serían una lista casi vacía, o un 404.
     */
    public function test_provincias_y_paises_con_solo_fichas_chicas_no_se_declaran(): void
    {
        $this->get('/sitemaps/lugares.xml')
            ->assertOk()
            ->assertDontSee('/south-africa/gauteng', false)
            ->assertDontSee('/namibia', false);

        $this->get('/sitemap.xml')->assertDontSee('/sitemaps/namibia.xml', false);
        $this->get('/sitemaps/namibia.xml')->assertStatus(404);
    }

    /** Con el umbral en 1 se vuelve a declarar todo lo que tiene calles. */
    public function test_con_el_umbral_en_uno_se_declaran_todas_las_que_tienen_calles(): void
    {
        config(['services.sitemap.minimo_calles' => 1]);

        $this->get('/sitemaps/south-africa.xml')
            ->assertOk()
            ->assertSee('franschhoek-3382160', false)
            ->assertSee('pretoria-964137', false);

        $this->get('/sitemap.xml')->assertSee('/sitemaps/namibia.xml', false);
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
