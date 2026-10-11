<?php

namespace Tests\Feature;

use App\Models\Busqueda;
use App\Models\Ciudad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as PeticionHttp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/*
 * Cubre POST /api/direcciones: geocodificar calle + altura y ponerla en el
 * mapa. Es la función principal del sitio y la que se había quedado afuera de
 * la migración.
 *
 * Lo que fija esta clase, además de que funcione:
 *
 * · La calle entra en la lista de la ciudad SOLO si la dirección existe. El
 *   sitio viejo guardaba antes de preguntarle a Nominatim, y por eso la lista
 *   de Calgary tiene entradas como "st", "ave" y "2".
 * · La consulta sale acotada a la ciudad. Sin eso, "14 Ave SW" se resuelve en
 *   otro continente.
 * · La respuesta no se declara cacheable, porque es un POST en un grupo de
 *   rutas que sí lo son.
 */
class DireccionesTest extends TestCase
{
    use RefreshDatabase;

    private const CIUDAD = 3369157;

    private const FICHA = '/south-africa/western-cape/cape-town-3369157';

    protected function setUp(): void
    {
        parent::setUp();

        // El techo de una consulta por segundo es global y sobrevive de un
        // test al otro dentro del mismo proceso.
        RateLimiter::clear('nominatim.global');

        DB::table('continentes')->insert([
            'id' => 'AF', 'nombre' => 'Africa', 'url' => 'africa',
            'centro_x' => 0, 'centro_y' => 20, 'zoom' => 3, 'descripcion' => '',
        ]);
        DB::table('paises')->insert([
            'id' => 'ZA', 'nombre' => 'South Africa', 'url' => 'south-africa', 'continente_id' => 'AF',
            'centro_x' => -30, 'centro_y' => 25, 'zoom' => 5,
        ]);
        DB::table('provincias')->insert([
            'id' => 'ZA.11', 'nombre' => 'Western Cape', 'url' => 'western-cape', 'pais_id' => 'ZA',
            'centro_x' => -33.2, 'centro_y' => 21.8,
        ]);
        DB::table('ciudades')->insert([
            'id' => self::CIUDAD,
            'nombre' => 'Cape Town', 'nombre_min' => 'cape town', 'url' => 'cape-town',
            'fcode' => 'PPLA', 'population' => 3433441,
            'pais_id' => 'ZA', 'provincia_id' => 'ZA.11', 'departamento_id' => 'ZA.11.1',
            'centro_x' => -33.9, 'centro_y' => 18.4,
            'limite_sw_x' => -34, 'limite_sw_y' => 18.3,
            'limite_ne_x' => -33.8, 'limite_ne_y' => 18.5,
            'zoom' => 13, 'visitas' => 0,
            // Arranca sin calles a propósito: uno de los casos es que la
            // primera dirección encontrada le estrene la ficha.
            'tiene_calles' => false,
        ]);
    }

    public function test_devuelve_las_coordenadas_de_la_direccion(): void
    {
        $this->fingirNominatim();

        $this->postJson('/api/direcciones', $this->adderley())
            ->assertOk()
            ->assertJson([
                'encontrada' => true,
                'lat' => -33.9219,
                'lon' => 18.4241,
                'etiqueta' => 'Adderley Street, Foreshore, Cape Town, Western Cape, 8001, South Africa',
            ]);
    }

    /** La regla del proyecto: si la dirección existe, la calle entra en la lista. */
    public function test_la_calle_encontrada_entra_en_la_lista_de_la_ciudad(): void
    {
        $this->fingirNominatim();

        $this->postJson('/api/direcciones', $this->adderley())->assertOk();

        $calle = Busqueda::where('ciudad_id', self::CIUDAD)->first();

        $this->assertNotNull($calle);
        $this->assertSame('Adderley Street', $calle->nombre_calle);
        $this->assertSame(1, $calle->cantidad_busquedas);
        // La altura no se guarda: la lista es de calles, no de direcciones.
        $this->assertStringNotContainsString('12', $calle->nombre_calle);
    }

    /** Y la contracara, que es el punto: el sitio viejo guardaba igual. */
    public function test_la_direccion_que_no_existe_no_deja_calle(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([], 200)]);

        $this->postJson('/api/direcciones', [
            'ciudad_id' => self::CIUDAD,
            'calle' => 'Calle Que No Existe',
            'altura' => '999',
        ])
            ->assertOk()
            ->assertExactJson(['encontrada' => false]);

        $this->assertSame(0, Busqueda::where('ciudad_id', self::CIUDAD)->count());
        $this->assertFalse(Ciudad::find(self::CIUDAD)->tiene_calles);
    }

    /** Que Nominatim no conteste tampoco es motivo para guardar nada. */
    public function test_un_fallo_de_nominatim_no_deja_calle(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response('', 503)]);

        $this->postJson('/api/direcciones', $this->adderley())
            ->assertOk()
            ->assertExactJson(['encontrada' => false]);

        $this->assertSame(0, Busqueda::where('ciudad_id', self::CIUDAD)->count());
    }

    /**
     * La ciudad estrena ficha con la primera dirección encontrada: es la regla
     * de ciudad-con-contenido, y esta es la vía por la que se revierte un 410.
     */
    public function test_la_primera_direccion_le_da_pagina_a_la_ciudad(): void
    {
        $this->fingirNominatim();

        $this->get(self::FICHA)->assertGone();

        $this->postJson('/api/direcciones', $this->adderley())
            ->assertOk()
            ->assertJsonPath('ruta', url(self::FICHA));

        $this->assertTrue(Ciudad::find(self::CIUDAD)->tiene_calles);
        $this->get(self::FICHA)->assertOk();
    }

    /**
     * La consulta va acotada a la ciudad. Sin `bounded`, el viewbox es una
     * preferencia y no un límite: Nominatim devuelve el mejor resultado del
     * mundo y el mapa se va a otro país sin avisar.
     */
    public function test_la_consulta_queda_encerrada_en_la_ciudad(): void
    {
        $this->fingirNominatim();

        $this->postJson('/api/direcciones', $this->adderley())->assertOk();

        Http::assertSent(function (PeticionHttp $peticion) {
            $datos = $peticion->data();

            return $datos['street'] === '12 Adderley Street'
                && $datos['city'] === 'Cape Town'
                && $datos['state'] === 'Western Cape'
                && $datos['countrycodes'] === 'za'
                && (int) $datos['bounded'] === 1
                // lon_oeste, lat_sur, lon_este, lat_norte
                && $datos['viewbox'] === '18.3,-34,18.5,-33.8';
        });
    }

    /** Y con el User-Agent que pide la política de uso. */
    public function test_se_identifica_ante_nominatim(): void
    {
        $this->fingirNominatim();

        $this->postJson('/api/direcciones', $this->adderley())->assertOk();

        Http::assertSent(fn (PeticionHttp $peticion) => str_contains(
            $peticion->header('User-Agent')[0] ?? '',
            'worldstreets'
        ));
    }

    /**
     * Segunda vez, misma dirección: sale de la caché. Es lo que mantiene el
     * volumen bajo el techo de la instancia pública de Nominatim.
     */
    public function test_la_segunda_consulta_no_vuelve_a_salir(): void
    {
        $this->fingirNominatim();

        $this->postJson('/api/direcciones', $this->adderley())->assertOk();
        $this->postJson('/api/direcciones', $this->adderley())->assertJsonPath('encontrada', true);

        Http::assertSentCount(1);
    }

    /** La misma calle desde la misma IP no cuenta dos veces. */
    public function test_no_cuenta_dos_veces_la_misma_calle(): void
    {
        $this->fingirNominatim();

        $this->postJson('/api/direcciones', $this->adderley())->assertOk();
        $this->postJson('/api/direcciones', $this->adderley('30'))->assertOk();

        $calles = Busqueda::where('ciudad_id', self::CIUDAD)->get();

        $this->assertCount(1, $calles);
        $this->assertSame(1, $calles->first()->cantidad_busquedas);
    }

    /**
     * El endpoint vive en el grupo de rutas cacheables. Si la respuesta saliera
     * `public, s-maxage`, el borde le serviría a un visitante la dirección que
     * buscó otro.
     */
    public function test_la_respuesta_no_se_comparte_en_el_borde(): void
    {
        $this->fingirNominatim();

        $control = (string) $this->postJson('/api/direcciones', $this->adderley())
            ->assertOk()
            ->headers->get('Cache-Control');

        $this->assertStringNotContainsString('s-maxage', $control);
        $this->assertStringNotContainsString('public', $control);
    }

    public function test_pide_las_tres_cosas(): void
    {
        Http::fake();

        $this->postJson('/api/direcciones', ['ciudad_id' => self::CIUDAD, 'calle' => 'Adderley Street'])
            ->assertJsonValidationErrors('altura');

        $this->postJson('/api/direcciones', ['ciudad_id' => self::CIUDAD, 'altura' => '12'])
            ->assertJsonValidationErrors('calle');

        $this->postJson('/api/direcciones', ['calle' => 'Adderley Street', 'altura' => '12'])
            ->assertJsonValidationErrors('ciudad_id');

        Http::assertNothingSent();
    }

    /** Una respuesta que Nominatim da, pero sin coordenadas usables. */
    public function test_una_respuesta_sin_coordenadas_no_cuenta_como_encontrada(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([['display_name' => 'Algo']], 200),
        ]);

        $this->postJson('/api/direcciones', $this->adderley())
            ->assertJsonPath('encontrada', false);

        $this->assertSame(0, Busqueda::where('ciudad_id', self::CIUDAD)->count());
    }

    /** @return array<string, string|int> */
    private function adderley(string $altura = '12'): array
    {
        return [
            'ciudad_id' => self::CIUDAD,
            'calle' => 'Adderley Street',
            'altura' => $altura,
        ];
    }

    private function fingirNominatim(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([[
                'lat' => '-33.9219000',
                'lon' => '18.4241000',
                'display_name' => 'Adderley Street, Foreshore, Cape Town, Western Cape, 8001, South Africa',
            ]], 200),
        ]);
    }
}
