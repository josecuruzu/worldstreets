<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/*
 * Los metadatos que producen el tráfico.
 *
 * Esta clase existe porque el rediseño los rompió sin que nada avisara. El
 * sitio viejo titula todo el corpus `Reverse Address Lookup Free in {lugar} |
 * WSG` y esa frase es el 73% de los clics; las fichas de país la usan para
 * rankear `reverse address lookup canada` (24 clics de EE.UU. sobre 1.393
 * impresiones, posición 16-20). El rediseño la reemplazó por
 * "Canada — regions, cities and streets", que no la contiene, y se llevó
 * también todas las meta descriptions.
 *
 * Nada de eso da error, no se ve en pantalla y no lo agarra ningún otro test:
 * se nota tres meses después en Search Console. De ahí estos casos.
 */
class MetadatosDeBusquedaTest extends TestCase
{
    use RefreshDatabase;

    /** La frase que no se toca. */
    private const FRASE = 'Reverse Address Lookup Free in';

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

    public static function fichas(): array
    {
        return [
            'pais' => ['/canada', 'Canada'],
            'provincia' => ['/canada/alberta', 'Alberta, Canada'],
            'ciudad' => ['/canada/alberta/calgary-5913490', 'Calgary, Alberta'],
        ];
    }

    #[DataProvider('fichas')]
    public function test_el_titulo_arranca_con_la_frase_que_rankea(string $url, string $lugar): void
    {
        $this->get($url)
            ->assertOk()
            ->assertSee('<title>'.self::FRASE.' '.$lugar.' | worldstreets</title>', false);
    }

    #[DataProvider('fichas')]
    public function test_la_ficha_declara_una_descripcion(string $url): void
    {
        $html = $this->get($url)->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<meta name="description" content="[^"]{80,}">/', $html);

        // En una sola línea: el <meta> se armaba con un bloque de Blade y
        // salía con saltos de línea y sangría adentro del atributo.
        preg_match('/<meta name="description" content="([^"]*)">/', $html, $c);
        $this->assertStringNotContainsString("\n", $c[1]);
    }

    /** La descripción de la ficha de ciudad lleva su población y sus calles. */
    public function test_la_descripcion_de_la_ciudad_lleva_poblacion_y_calles(): void
    {
        $html = $this->get('/canada/alberta/calgary-5913490')->assertOk()->getContent();
        preg_match('/<meta name="description" content="([^"]*)">/', $html, $c);

        $this->assertStringContainsString('Calgary, Alberta, Canada', $c[1]);
        $this->assertStringContainsString('home to 1,019,942 people', $c[1]);
        $this->assertStringContainsString('1 street on record', $c[1]);
    }

    /** Sin población conocida no se inventa: cae a la plantilla sin ella. */
    public function test_sin_poblacion_la_descripcion_no_la_menciona(): void
    {
        DB::table('ciudades')->where('id', 5913490)->update(['population' => 0]);

        $html = $this->get('/canada/alberta/calgary-5913490')->assertOk()->getContent();
        preg_match('/<meta name="description" content="([^"]*)">/', $html, $c);

        $this->assertStringNotContainsString('people', $c[1]);
        $this->assertStringContainsString('1 street on record', $c[1]);
    }

    /** El título no cambia: es la frase que rankea. */
    public function test_el_titulo_de_la_ciudad_no_lleva_cifras(): void
    {
        $this->get('/canada/alberta/calgary-5913490')
            ->assertSee('<title>'.self::FRASE.' Calgary, Alberta | worldstreets</title>', false);
    }

    /** La home también rankea con la frase, y no tenía descripción. */
    public function test_la_home_conserva_su_titulo_y_gana_descripcion(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<title>Free Address Finder &amp; Reverse Address Lookup | worldstreets</title>', false)
            ->assertSee('name="description"', false);
    }

    /**
     * La descripción del país lleva las cifras de cobertura. Es lo único que
     * el sitio nuevo tiene y el viejo no, y en escritorio —105.867
     * impresiones al 0,36% de CTR en posición 21,5— el snippet es lo único
     * que decide el clic.
     */
    public function test_la_descripcion_del_pais_declara_la_cobertura_real(): void
    {
        $this->get('/canada')
            ->assertOk()
            ->assertSee('1 street on record across 1 city', false);
    }

    /** Y no promete cobertura donde no hay. */
    public function test_un_pais_sin_calles_no_promete_calles(): void
    {
        DB::table('paises')->insert([
            'id' => 'MX', 'nombre' => 'Mexico', 'url' => 'mexico', 'continente_id' => 'NA',
            'centro_x' => 23, 'centro_y' => -102, 'zoom' => 5,
        ]);
        DB::table('provincias')->insert([
            'id' => 'MX.19', 'nombre' => 'Jalisco', 'url' => 'jalisco', 'pais_id' => 'MX',
            'centro_x' => 20, 'centro_y' => -103,
        ]);

        $respuesta = $this->get('/mexico')->assertOk();

        $respuesta->assertDontSee('streets on record across', false);
        $respuesta->assertSee('Reverse Address Lookup Free in Mexico', false);
    }
}
