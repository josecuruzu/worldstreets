<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * La etiqueta de Google Analytics.
 *
 * Esta clase existe por una omisión que ya ocurrió y que no dio ningún error:
 * el CakePHP llevaba GA4 en su layout, la migración al Laravel no lo portó, y
 * el sitio salió a producción midiendo cero. No falló nada, no hubo nada rojo
 * en ninguna consola, y el único síntoma fue que Analytics dejó de recibir
 * datos. Se descubrió porque el dueño lo notó, no porque el sitio lo dijera.
 *
 * Lo que se fija acá son las tres propiedades que hacen que eso no se repita
 * y que la etiqueta no rompa nada de lo que ya está andando:
 *
 * 1. Que la etiqueta esté, en las páginas que reciben el tráfico.
 * 2. Que vaya UNA sola vez. El Cake cargaba el gtag y además el contenedor de
 *    Tag Manager, que es la forma habitual de contar cada visita dos veces.
 * 3. Que no le saque la cacheabilidad a las rutas públicas. Toda la velocidad
 *    del sitio nuevo depende de que esas respuestas salgan sin cookie, y una
 *    etiqueta de medición mal puesta —del lado del servidor— es justo lo que
 *    lo rompería.
 */
class MedicionTest extends TestCase
{
    use RefreshDatabase;

    private const FICHA = '/canada/alberta/calgary-5913490';

    private const ID = 'G-TESTTEST00';

    protected function setUp(): void
    {
        parent::setUp();

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

    /**
     * El caso que falló de verdad: la etiqueta tiene que estar en la ficha de
     * ciudad, que es la página que recibe el tráfico de Google —3.684 de los
     * 6.930 clics del sitio—. Si esto se rompe, se deja de medir el 53% del
     * tráfico sin que nada falle.
     */
    public function test_la_ficha_de_ciudad_lleva_la_etiqueta(): void
    {
        config(['services.analytics.id' => self::ID]);

        $this->get(self::FICHA)
            ->assertOk()
            ->assertSee('googletagmanager.com/gtag/js?id='.self::ID, false)
            ->assertSee("gtag('config', '".self::ID."')", false);
    }

    /** La home también mide, aunque sean 3 URLs: es la entrada del buscador. */
    public function test_la_home_lleva_la_etiqueta(): void
    {
        config(['services.analytics.id' => self::ID]);

        $this->get('/')->assertOk()->assertSee(self::ID, false);
    }

    /**
     * Una sola vez. El Cake cargaba el gtag Y el contenedor GTM-KXJVD2T en el
     * mismo <head>: si el contenedor tenía una etiqueta de GA4 hacia esta
     * misma propiedad, cada visita se contó dos veces durante años. Acá va
     * solo el gtag, y este test es lo que impide que alguien vuelva a sumar
     * el contenedor encima en vez de en lugar de.
     */
    public function test_se_mide_una_sola_vez(): void
    {
        config(['services.analytics.id' => self::ID]);

        $html = $this->get(self::FICHA)->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'gtag/js?id='));
        $this->assertStringNotContainsString('gtm.js?id=', $html);
        $this->assertStringNotContainsString('googletagmanager.com/ns.html', $html);
    }

    /**
     * Sin ANALYTICS_ID no se emite nada. Es lo que mantiene el local y la
     * suite sin llamadas a Google, y lo que permite tener el partial puesto en
     * el layout con la variable vacía.
     */
    public function test_sin_id_configurado_no_se_mide(): void
    {
        config(['services.analytics.id' => null]);

        $html = $this->get(self::FICHA)->assertOk()->getContent();

        $this->assertStringNotContainsString('googletagmanager', $html);
        $this->assertStringNotContainsString('gtag', $html);
        $this->assertStringNotContainsString('dataLayer', $html);
    }

    /**
     * Medir no puede costar la cacheabilidad.
     *
     * Toda la velocidad del sitio nuevo se apoya en que las rutas públicas
     * salen sin `Set-Cookie` y con `s-maxage`, para que LiteSpeed las sirva
     * sin levantar PHP — es literalmente el problema que este proyecto le
     * marca al sitio viejo. La etiqueta es JavaScript del cliente: la cookie
     * `_ga` la escribe el navegador y al servidor no le llega nunca.
     */
    public function test_la_etiqueta_no_rompe_el_cacheo_de_las_rutas_publicas(): void
    {
        config(['services.analytics.id' => self::ID]);

        $respuesta = $this->get(self::FICHA)->assertOk();

        $this->assertEmpty($respuesta->headers->getCookies());
        $this->assertStringContainsString('s-maxage', $respuesta->headers->get('Cache-Control'));
        $this->assertNull($respuesta->headers->get('Vary'));
    }
}
