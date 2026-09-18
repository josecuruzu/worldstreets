<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Las páginas públicas tienen que poder cachearse en el servidor.
 *
 * El handoff lo marca como una de las cosas que hundieron al sitio viejo: la
 * home en CakePHP responde `no-store, no-cache, must-revalidate` y setea una
 * cookie de sesión en cada visita, así que el caché de LiteSpeed no guarda
 * nada y cada petición cuesta 2.400 ms. Con 240 rastreos diarios contra 18,28
 * M de URLs conocidas, la velocidad decide cuánto tarda Google en reevaluar.
 *
 * Es una regresión fácil de introducir y difícil de ver: basta con mover una
 * ruta al grupo `web` o tocar la sesión desde una vista pública, y la página
 * sigue respondiendo 200 mientras el caché deja de funcionar en silencio.
 */
class RutasCacheablesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('continentes')->insert([
            'id' => 'NA', 'nombre' => 'North America', 'url' => 'north-america',
            'centro_x' => 54, 'centro_y' => -105, 'zoom' => 3, 'descripcion' => '',
        ]);
        DB::table('paises')->insert([
            'id' => 'CA', 'nombre' => 'Canada', 'url' => 'canada', 'continente_id' => 'NA',
            'capital' => 'Ottawa', 'population' => 37058856, 'centro_x' => 56, 'centro_y' => -106, 'zoom' => 4,
        ]);
        DB::table('provincias')->insert([
            'id' => 'CA.08', 'nombre' => 'Ontario', 'url' => 'ontario-canada', 'pais_id' => 'CA',
            'centro_x' => 49, 'centro_y' => -85,
        ]);
        DB::table('ciudades')->insert([
            'id' => 6176823, 'nombre' => 'Waterloo', 'nombre_min' => 'waterloo',
            'url' => 'waterloo-ontario-canada', 'fcode' => 'PPL', 'population' => 97475,
            'pais_id' => 'CA', 'provincia_id' => 'CA.08', 'departamento_id' => 'CA.08.1',
            'centro_x' => 43.4668, 'centro_y' => -80.51639,
            'limite_sw_x' => 43.4, 'limite_sw_y' => -80.6,
            'limite_ne_x' => 43.5, 'limite_ne_y' => -80.4,
            'zoom' => 13, 'visitas' => 0, 'tiene_calles' => true,
        ]);
        DB::table('busquedas')->insert([
            'ciudad_id' => 6176823, 'nombre_calle' => 'king street north',
            'nombre_calle_compacto' => 'kingstreetnorth', 'cantidad_busquedas' => 3,
        ]);
    }

    /** @return list<array{0: string}> */
    public static function rutasPublicas(): array
    {
        return [
            ['/canada'],
            ['/canada/ontario'],
            ['/canada/ontario/waterloo-6176823'],
            ['/sitemap.xml'],
            ['/sitemaps/lugares.xml'],
            ['/sitemaps/canada.xml'],
        ];
    }

    /**
     * Ni una cookie. Una respuesta con Set-Cookie no se puede compartir entre
     * visitantes: el borde le serviría la cookie de uno a otro, así que ningún
     * caché compartido la guarda.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('rutasPublicas')]
    public function test_una_ruta_publica_no_manda_cookies(string $ruta): void
    {
        $respuesta = $this->get($ruta)->assertOk();

        $this->assertSame([], $respuesta->headers->getCookies(), "{$ruta} manda Set-Cookie");
    }

    /** Y ninguna abre sesión: la tabla `sessions` no crece con visitas anónimas. */
    #[\PHPUnit\Framework\Attributes\DataProvider('rutasPublicas')]
    public function test_una_ruta_publica_no_abre_sesion(string $ruta): void
    {
        DB::table('sessions')->delete();

        $this->get($ruta)->assertOk();

        $this->assertSame(0, DB::table('sessions')->count(), "{$ruta} escribió una sesión");
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rutasPublicas')]
    public function test_una_ruta_publica_se_declara_cacheable(string $ruta): void
    {
        $cacheControl = $this->get($ruta)->assertOk()->headers->get('Cache-Control');

        $this->assertStringContainsString('public', $cacheControl, "{$ruta} no es cacheable");
        $this->assertStringContainsString('s-maxage=', $cacheControl);
        $this->assertStringNotContainsString('no-cache', $cacheControl);
        $this->assertStringNotContainsString('private', $cacheControl);
    }

    /**
     * Las redirecciones del sitio viejo también: un 301 que depende solo del
     * slug es la respuesta más cacheable que hay, y son las que más va a pedir
     * el rastreador mientras limpia las 18,28 M de URLs que conoce.
     */
    public function test_las_redirecciones_del_sitio_viejo_son_cacheables(): void
    {
        $respuesta = $this->get('/view/find/maps/address/waterloo-ontario-canada')->assertStatus(301);

        $this->assertStringContainsString('public', $respuesta->headers->get('Cache-Control'));
        $this->assertSame([], $respuesta->headers->getCookies());
    }

    /**
     * Un 410 no se cachea: es el mecanismo con el que se está limpiando el
     * índice, y conviene que Google lo vuelva a pedir en vez de quedárselo una
     * hora. Tampoco un 404.
     */
    public function test_los_errores_no_se_cachean(): void
    {
        $respuesta = $this->get('/county/maps/address/lo-que-sea')->assertStatus(410);

        $this->assertStringNotContainsString('s-maxage', (string) $respuesta->headers->get('Cache-Control'));
    }

    /**
     * La home sí lleva sesión: su formulario registra búsquedas por POST y
     * necesita el token CSRF. Son 3 URLs y 154 de los 6.930 clics, y el
     * rastreo se lo llevan las fichas.
     */
    public function test_la_home_conserva_la_sesion(): void
    {
        $respuesta = $this->get('/')->assertOk();

        $respuesta->assertSee('csrf-token', false);
        $this->assertNotSame([], $respuesta->headers->getCookies());
    }

    /**
     * Y las fichas NO llevan el token: sin sesión, `csrf_token()` lanza y la
     * página entera se cae con un 500.
     */
    public function test_una_ficha_no_pide_el_token_csrf(): void
    {
        $this->get('/canada/ontario/waterloo-6176823')
            ->assertOk()
            ->assertDontSee('csrf-token', false);
    }

    /** Los GET del buscador son públicos; el POST que registra, no. */
    public function test_los_endpoints_de_lectura_del_buscador_son_publicos(): void
    {
        $respuesta = $this->getJson('/api/ciudades?q=waterloo')->assertOk();

        $this->assertSame([], $respuesta->headers->getCookies());
    }
}
