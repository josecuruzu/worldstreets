<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * La versión en español.
 *
 * Existe por un número que el HANDOFF no había mirado entero: **el 35,8% de
 * los clics del sitio entra por una URL `/esp/` del CakePHP** —2.479 de
 * 6.930—, contra 3.941 del inglés. Cuba es el segundo país por clics (832),
 * España el cuarto (422). La decisión de no volver a los cinco idiomas se
 * había tomado sobre 18 consultas en español desde EE.UU., que es la rodaja
 * estadounidense del top-1000.
 *
 * Lo que estos casos protegen, en orden de lo que cuesta romperlo:
 *
 * 1. Que `/esp/...` aterrice en español y no en la ficha inglesa. Las páginas
 *    cubanas rankean en posición 6-12 porque el título coincide con la
 *    consulta ("calles de marianao"); con el título en inglés deja de
 *    coincidir y no hay error que lo delate.
 * 2. Que la versión en español NO exista fuera de los 21 países
 *    hispanohablantes. Un espejo del corpus entero es lo que dejó al sitio con
 *    18,25 M de URLs y una penalización.
 * 3. Que los hreflang sean recíprocos. Uno que la otra página no devuelve es
 *    un hreflang que Google ignora.
 */
class VersionEnEspanolTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('continentes')->insert([
            ['id' => 'NA', 'nombre' => 'North America', 'url' => 'north-america',
                'centro_x' => 45, 'centro_y' => -100, 'zoom' => 3, 'descripcion' => ''],
            ['id' => 'AS', 'nombre' => 'Asia', 'url' => 'asia',
                'centro_x' => 30, 'centro_y' => 100, 'zoom' => 3, 'descripcion' => ''],
        ]);
        DB::table('paises')->insert([
            ['id' => 'CU', 'nombre' => 'Cuba', 'url' => 'cuba', 'continente_id' => 'NA',
                'centro_x' => 22, 'centro_y' => -80, 'zoom' => 6],
            ['id' => 'JP', 'nombre' => 'Japan', 'url' => 'japan', 'continente_id' => 'AS',
                'centro_x' => 36, 'centro_y' => 138, 'zoom' => 5],
        ]);
        DB::table('provincias')->insert([
            ['id' => 'CU.02', 'nombre' => 'La Habana', 'url' => 'la-habana-cuba', 'pais_id' => 'CU',
                'centro_x' => 23.1, 'centro_y' => -82.3],
            ['id' => 'JP.40', 'nombre' => 'Tokyo', 'url' => 'tokyo-japan', 'pais_id' => 'JP',
                'centro_x' => 35.7, 'centro_y' => 139.7],
        ]);

        $this->crearCiudad(3547640, 'Marianao', 'marianao-havana-cuba', 'CU', 'CU.02', 136020);
        $this->crearCiudad(1850147, 'Tokyo', 'tokyo-tokyo-japan', 'JP', 'JP.40', 8336599);
    }

    private function crearCiudad(int $id, string $nombre, string $url, string $pais, string $provincia, int $poblacion): void
    {
        DB::table('ciudades')->insert([
            'id' => $id,
            'nombre' => $nombre, 'nombre_min' => mb_strtolower($nombre), 'url' => $url,
            'fcode' => 'PPLA', 'population' => $poblacion,
            'pais_id' => $pais, 'provincia_id' => $provincia, 'departamento_id' => $provincia.'.1',
            'centro_x' => 23.08, 'centro_y' => -82.43,
            'limite_sw_x' => 22.9, 'limite_sw_y' => -82.6,
            'limite_ne_x' => 23.2, 'limite_ne_y' => -82.3,
            'zoom' => 13, 'visitas' => 0, 'tiene_calles' => true,
        ]);
        DB::table('busquedas')->insert([
            'ciudad_id' => $id,
            'nombre_calle' => 'calle 23', 'nombre_calle_compacto' => 'calle23',
            'cantidad_busquedas' => 3,
        ]);
    }

    public static function fichasCubanas(): array
    {
        return [
            'pais' => ['/es/cuba'],
            'provincia' => ['/es/cuba/la-habana/'],
            'ciudad' => ['/es/cuba/la-habana/marianao-3547640'],
        ];
    }

    // ── existe donde tiene que existir ───────────────────────────────────

    public function test_la_ficha_de_pais_sale_en_espanol(): void
    {
        $this->get('/es/cuba')
            ->assertOk()
            ->assertSee('<title>Calles y direcciones de Cuba | worldstreets</title>', false)
            ->assertSee('<html lang="es"', false)
            ->assertSee('Buscar direcciones y calles en Cuba');
    }

    public function test_la_ficha_de_ciudad_sale_en_espanol(): void
    {
        $this->get('/es/cuba/la-habana/marianao-3547640')
            ->assertOk()
            ->assertSee('<title>Calles y direcciones de Marianao, La Habana | worldstreets</title>', false)
            ->assertSee('Calles de Marianao')
            ->assertSee('Sobre este lugar');
    }

    /** Y la inglesa no se tocó: esa frase es el 73% de los clics del sitio. */
    public function test_la_version_inglesa_queda_intacta(): void
    {
        $this->get('/cuba')
            ->assertOk()
            ->assertSee('<title>Reverse Address Lookup Free in Cuba | worldstreets</title>', false)
            ->assertSee('<html lang="en"', false);
    }

    // ── y no existe donde no tiene que existir ───────────────────────────

    /**
     * El recorte que impide que esto sea un espejo del corpus. Japón tiene
     * 231.970 calles y ninguna versión en español.
     */
    public function test_un_pais_que_no_es_hispanohablante_no_tiene_version_en_espanol(): void
    {
        $this->get('/es/japan')->assertNotFound();
        $this->get('/es/japan/tokyo')->assertNotFound();
        $this->get('/es/japan/tokyo/tokyo-1850147')->assertNotFound();

        // Pero en inglés sigue estando.
        $this->get('/japan')->assertOk();
    }

    /** `/es` a secas todavía no es una página. */
    public function test_la_raiz_en_espanol_redirige_a_la_home(): void
    {
        $this->get('/es')->assertRedirect('/');
    }

    // ── hreflang ─────────────────────────────────────────────────────────

    #[DataProvider('fichasCubanas')]
    public function test_las_dos_versiones_se_declaran_mutuamente(string $ruta): void
    {
        $enEspanol = $this->get(rtrim($ruta, '/'))->assertOk()->getContent();
        $enIngles = $this->get(str_replace('/es/', '/', rtrim($ruta, '/')))->assertOk()->getContent();

        foreach ([$enEspanol, $enIngles] as $html) {
            preg_match_all('/hreflang="([a-z-]+)"/', $html, $encontrados);
            $this->assertSame(['en', 'es', 'x-default'], $encontrados[1]);
        }
    }

    /** Una ficha sin versión en español no declara ningún hreflang. */
    public function test_una_ficha_sin_version_en_espanol_no_declara_alternativas(): void
    {
        $this->get('/japan')->assertOk()->assertDontSee('hreflang', false);
    }

    // ── la coherencia interna de la versión ──────────────────────────────

    /**
     * Los enlaces de una página en español apuntan a páginas en español. Si
     * apuntaran a la inglesa, el primer clic saca al visitante del idioma y
     * Google concluye que la versión en español es una hoja suelta.
     */
    public function test_los_enlaces_internos_se_quedan_en_espanol(): void
    {
        $html = $this->get('/es/cuba/la-habana/marianao-3547640')->assertOk()->getContent();

        $this->assertStringContainsString('href="'.url('/es/cuba').'"', $html);
        $this->assertStringContainsString('href="'.url('/es/cuba/la-habana').'"', $html);
    }

    /**
     * El orden de los dos campos del buscador de direcciones.
     *
     * En inglés "6455 Macleod Trail SE" y el número va primero; en español
     * "Calle 23 456" y va después. El sitio viejo ya hacía esta vuelta.
     */
    public function test_en_espanol_la_calle_va_antes_que_el_numero(): void
    {
        $es = $this->get('/es/cuba/la-habana/marianao-3547640')->assertOk()->getContent();
        $en = $this->get('/cuba/la-habana/marianao-3547640')->assertOk()->getContent();

        $this->assertLessThan(strpos($es, 'id="altura"'), strpos($es, 'id="calle"'));
        $this->assertLessThan(strpos($en, 'id="calle"'), strpos($en, 'id="altura"'));
    }

    /** 2.065 y no 2,065: en español la coma es el separador decimal. */
    public function test_los_numeros_se_escriben_como_en_espanol(): void
    {
        $this->get('/es/cuba/la-habana/marianao-3547640')
            ->assertOk()
            ->assertSee('136.020');

        $this->get('/cuba/la-habana/marianao-3547640')
            ->assertOk()
            ->assertSee('136,020');
    }

    /** El bloque de formato de dirección también está traducido. */
    public function test_el_formato_de_direccion_sale_en_espanol(): void
    {
        $this->get('/es/cuba')
            ->assertOk()
            ->assertSee('Cómo se escribe una dirección en Cuba')
            ->assertSee('entre H e I');
    }

    // ── el legado, que es lo que trae el tráfico ─────────────────────────

    public function test_una_url_esp_del_cake_aterriza_en_espanol(): void
    {
        $this->get('/esp/view/find/maps/address/marianao-havana-cuba')
            ->assertRedirect(url('/es/cuba/la-habana/marianao-3547640'));

        $this->get('/esp/country/maps/address/cuba')
            ->assertRedirect(url('/es/cuba'));

        $this->get('/esp/state/maps/address/la-habana-cuba')
            ->assertRedirect(url('/es/cuba/la-habana'));
    }

    /**
     * Pero solo donde hay versión en español. `/esp/.../calgary-alberta-canada`
     * existe y tiene impresiones; Canadá no se publica en español, así que va
     * a la ficha inglesa.
     */
    public function test_una_url_esp_de_un_pais_sin_version_va_al_ingles(): void
    {
        $this->get('/esp/view/find/maps/address/tokyo-tokyo-japan')
            ->assertRedirect(url('/japan/tokyo/tokyo-1850147'));
    }

    /** Los otros tres idiomas del Cake siguen cayendo en inglés. */
    public function test_los_otros_idiomas_del_cake_van_al_ingles(): void
    {
        foreach (['fra', 'por', 'ita'] as $idioma) {
            $this->get("/{$idioma}/view/find/maps/address/marianao-havana-cuba")
                ->assertRedirect(url('/cuba/la-habana/marianao-3547640'));
        }
    }

    // ── sitemaps ─────────────────────────────────────────────────────────

    public function test_el_sitemap_declara_las_dos_versiones_solo_donde_existen(): void
    {
        $cuba = $this->get('/sitemaps/cuba.xml')->assertOk()->getContent();
        $this->assertStringContainsString('<loc>'.url('/cuba/la-habana/marianao-3547640').'</loc>', $cuba);
        $this->assertStringContainsString('<loc>'.url('/es/cuba/la-habana/marianao-3547640').'</loc>', $cuba);

        $japon = $this->get('/sitemaps/japan.xml')->assertOk()->getContent();
        $this->assertStringNotContainsString('/es/', $japon);
    }

    public function test_el_sitemap_de_lugares_declara_los_paises_en_los_dos_idiomas(): void
    {
        $html = $this->get('/sitemaps/lugares.xml')->assertOk()->getContent();

        $this->assertStringContainsString('<loc>'.url('/cuba').'</loc>', $html);
        $this->assertStringContainsString('<loc>'.url('/es/cuba').'</loc>', $html);
        $this->assertStringContainsString('<loc>'.url('/japan').'</loc>', $html);
        $this->assertStringNotContainsString('<loc>'.url('/es/japan').'</loc>', $html);
    }

    /** La versión en español también sale sin cookie y cacheable en el borde. */
    public function test_la_version_en_espanol_es_cacheable(): void
    {
        $this->get('/es/cuba')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=300, public, s-maxage=3600');
    }
}
