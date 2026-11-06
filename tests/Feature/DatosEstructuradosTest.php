<?php

namespace Tests\Feature;

use App\Models\EnlaceCompartido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
 * Los datos estructurados de la ficha de ciudad.
 *
 * Son un Place con sus coordenadas y su jerarquía, y las migas de pan. Solo
 * lo que la base sabe de verdad: nada de reseñas, direcciones postales ni
 * población, que Place ni siquiera tiene como propiedad.
 */
class DatosEstructuradosTest extends TestCase
{
    use RefreshDatabase;

    private const FICHA = '/canada/alberta/calgary-5913490';

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

    /** @return array<string, mixed> */
    private function datos(string $url): array
    {
        $html = $this->get($url)->assertOk()->getContent();

        $this->assertSame(1, preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $m));

        $datos = json_decode($m[1][0], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('https://schema.org', $datos['@context']);

        return $datos;
    }

    public function test_la_ficha_declara_un_lugar_con_coordenadas_y_jerarquia(): void
    {
        $lugar = $this->datos(self::FICHA)['@graph'][0];

        $this->assertSame('Place', $lugar['@type']);
        $this->assertSame('Calgary', $lugar['name']);
        $this->assertSame(['@type' => 'GeoCoordinates', 'latitude' => 51.05, 'longitude' => -114.08], $lugar['geo']);
        $this->assertSame('Alberta', $lugar['containedInPlace']['name']);
        $this->assertSame('AdministrativeArea', $lugar['containedInPlace']['@type']);
        $this->assertSame('Canada', $lugar['containedInPlace']['containedInPlace']['name']);
        $this->assertSame('Country', $lugar['containedInPlace']['containedInPlace']['@type']);
    }

    public function test_las_migas_siguen_el_orden_de_la_jerarquia(): void
    {
        $migas = $this->datos(self::FICHA)['@graph'][1];

        $this->assertSame('BreadcrumbList', $migas['@type']);
        $this->assertSame(
            // La primera es `lugares.inicio`, la misma del breadcrumb visible.
            [__('lugares.inicio'), 'Canada', 'Alberta', 'Calgary'],
            array_column($migas['itemListElement'], 'name')
        );
        $this->assertSame([1, 2, 3, 4], array_column($migas['itemListElement'], 'position'));
        $this->assertStringEndsWith('/canada/alberta/calgary-5913490', $migas['itemListElement'][3]['item']);
    }

    /** Solo lo que la base sabe: sin población ni dirección postal ni reseñas. */
    public function test_no_inventa_datos_que_la_base_no_tiene(): void
    {
        $json = json_encode($this->datos(self::FICHA));

        $this->assertStringNotContainsString('population', $json);
        $this->assertStringNotContainsString('PostalAddress', $json);
        $this->assertStringNotContainsString('aggregateRating', $json);
    }

    /** Sin coordenadas, el lugar sale sin `geo` y no con ceros. */
    public function test_sin_coordenadas_no_hay_geo(): void
    {
        DB::table('ciudades')->where('id', 5913490)->update(['centro_x' => null, 'centro_y' => null]);

        $lugar = $this->datos(self::FICHA)['@graph'][0];

        $this->assertArrayNotHasKey('geo', $lugar);
    }

    /** El JSON no puede cerrar el <script> aunque el nombre traiga etiquetas. */
    public function test_un_nombre_con_etiquetas_no_rompe_el_script(): void
    {
        DB::table('ciudades')->where('id', 5913490)->update(['alias' => 'Calgary</script><b>x']);

        // El alias cambia el slug, así que la ficha redirige a su URL nueva.
        $html = $this->followingRedirects()->get(self::FICHA)->assertOk()->getContent();

        $this->assertSame(1, preg_match_all('/<script type="application\/ld\+json">/', $html));
        $this->assertSame(1, preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $m));
        $this->assertNotNull(json_decode($m[1], true));
    }

    /** La página de un enlace compartido no se indexa: no lleva datos. */
    public function test_el_enlace_compartido_no_lleva_datos_estructurados(): void
    {
        $codigo = EnlaceCompartido::paraLaDireccion(5913490, 'macleod trail se', '100', 'en')->codigo;

        $html = $this->get("/r/{$codigo}")->assertOk()->getContent();

        $this->assertStringNotContainsString('application/ld+json', $html);
    }
}
