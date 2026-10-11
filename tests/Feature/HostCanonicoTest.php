<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * El host canónico.
 *
 * Esta clase existe por la forma del despliegue: el Laravel reemplaza al
 * CakePHP como proyecto completo, y para probarlo se publica primero en OTRO
 * dominio con el corpus entero adentro. Dos copias completas del sitio,
 * indexables las dos, en un dominio que viene de una caída algorítmica.
 *
 * Lo que lo vuelve peligroso es que no falla: el dominio de pruebas responde
 * 200, se ve bien, y con APP_URL apuntando ahí los canonical y los <loc> de
 * los sitemaps salen apuntando ahí también. No hay ningún síntoma hasta que
 * Search Console muestra la copia compitiendo con el original.
 */
class HostCanonicoTest extends TestCase
{
    use RefreshDatabase;

    private const CANONICO = 'worldstreets.global';

    private const PRUEBAS = 'http://pruebas.example';

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.canonical_host' => self::CANONICO]);

        DB::table('continentes')->insert([
            'id' => 'NA', 'nombre' => 'North America', 'url' => 'north-america',
            'centro_x' => 45, 'centro_y' => -100, 'zoom' => 3, 'descripcion' => '',
        ]);
        DB::table('paises')->insert([
            'id' => 'CA', 'nombre' => 'Canada', 'url' => 'canada', 'continente_id' => 'NA',
            'centro_x' => 56, 'centro_y' => -106, 'zoom' => 4,
        ]);
        DB::table('provincias')->insert([
            'id' => 'CA.01', 'nombre' => 'Alberta', 'url' => 'alberta', 'pais_id' => 'CA',
            'centro_x' => 55, 'centro_y' => -115,
        ]);
        DB::table('ciudades')->insert([
            'id' => 5913490,
            'nombre' => 'Calgary', 'nombre_min' => 'calgary', 'url' => 'calgary',
            'fcode' => 'PPLA', 'population' => 1019942,
            'pais_id' => 'CA', 'provincia_id' => 'CA.01', 'departamento_id' => 'CA.01.1',
            'centro_x' => 51.05, 'centro_y' => -114.08,
            'limite_sw_x' => 50.7, 'limite_sw_y' => -114.6,
            'limite_ne_x' => 51.36, 'limite_ne_y' => -113.83,
            'zoom' => 13, 'visitas' => 0, 'tiene_calles' => true,
        ]);
        DB::table('busquedas')->insert([
            'ciudad_id' => 5913490,
            'nombre_calle' => 'macleod trail se', 'nombre_calle_compacto' => 'macleodtrailse',
            'cantidad_busquedas' => 3,
        ]);
    }

    public static function paginas(): array
    {
        return [
            'home' => ['/'],
            'pais' => ['/canada'],
            'provincia' => ['/canada/alberta'],
            'ciudad' => ['/canada/alberta/calgary-5913490'],
        ];
    }

    #[DataProvider('paginas')]
    public function test_el_dominio_de_pruebas_responde_noindex(string $ruta): void
    {
        $this->get(self::PRUEBAS.$ruta)
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, follow');
    }

    /**
     * El sitemap también. Es una URL que se indexa, y además es por donde el
     * rastreador entra al corpus entero: si la copia lo publica sin noindex,
     * declara sus 22.581 URLs como propias.
     */
    public function test_el_sitemap_del_dominio_de_pruebas_responde_noindex(): void
    {
        $this->get(self::PRUEBAS.'/sitemap.xml')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, follow');
    }

    #[DataProvider('paginas')]
    public function test_el_host_canonico_no_lleva_noindex(string $ruta): void
    {
        $this->get('https://'.self::CANONICO.$ruta)
            ->assertOk()
            ->assertHeaderMissing('X-Robots-Tag');
    }

    /** Sin host configurado el mecanismo está apagado: es el caso de local. */
    public function test_sin_host_configurado_no_se_desindexa_nada(): void
    {
        config(['app.canonical_host' => null]);

        $this->get(self::PRUEBAS.'/canada')
            ->assertOk()
            ->assertHeaderMissing('X-Robots-Tag');
    }

    /**
     * El canonical va contra el host real aunque la pagina la sirva otro.
     *
     * Los enlaces de la navegacion NO: siguen siendo del host que sirvio la
     * pagina, porque si no el dominio de pruebas no se puede recorrer —el
     * primer clic salta a produccion—. Por eso acá se mira el canonical y no
     * el HTML entero.
     */
    #[DataProvider('paginas')]
    public function test_el_canonical_apunta_al_host_real_aunque_sirva_otro(string $ruta): void
    {
        $html = $this->get(self::PRUEBAS.$ruta)->assertOk()->getContent();

        preg_match_all('/<link rel="canonical" href="([^"]*)">/', $html, $c);

        $this->assertCount(1, $c[1]);
        $this->assertSame('https://'.self::CANONICO.$ruta, $c[1][0]);
    }

    /**
     * La query string no entra en el canonical. Cada utm_ de una campaña, cada
     * gclid, es si no una URL más con el mismo contenido.
     */
    public function test_el_canonical_se_come_los_parametros_de_campana(): void
    {
        $this->get('https://'.self::CANONICO.'/canada?utm_source=x&gclid=123')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="https://'.self::CANONICO.'/canada">', false);
    }

    /**
     * Menos `page`: la página 3 de una provincia lista ciudades distintas de
     * la 1, y canonicalizarla a la 1 le dice a Google que no existen.
     */
    public function test_el_canonical_conserva_la_paginacion(): void
    {
        $this->get('https://'.self::CANONICO.'/canada/alberta?page=2')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="https://'.self::CANONICO.'/canada/alberta?page=2">', false);
    }

    /**
     * Los <loc> de los sitemaps. Salen de `route()`, que usa APP_URL, y encima
     * están cacheados doce horas: si el host sale mal, sale mal para el corpus
     * entero y por medio día.
     */
    public function test_los_sitemaps_declaran_el_host_canonico(): void
    {
        $this->get(self::PRUEBAS.'/sitemap.xml')
            ->assertOk()
            ->assertSee('<loc>https://'.self::CANONICO.'/sitemaps/lugares.xml</loc>', false)
            ->assertDontSee('localhost', false)
            ->assertDontSee('pruebas.example', false);

        $this->get(self::PRUEBAS.'/sitemaps/canada.xml')
            ->assertOk()
            ->assertSee('<loc>https://'.self::CANONICO.'/canada/alberta/calgary-5913490</loc>', false)
            ->assertDontSee('pruebas.example', false);

        $this->get(self::PRUEBAS.'/sitemaps/lugares.xml')
            ->assertOk()
            ->assertSee('<loc>https://'.self::CANONICO.'/canada</loc>', false)
            ->assertDontSee('pruebas.example', false);
    }
}
