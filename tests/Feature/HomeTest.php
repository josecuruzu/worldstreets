<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
 * La home anunciaba "6,921 cities across Argentina" escrito a mano, y quedó
 * viejo apenas entraron los datos globales. Ahora los totales salen de la base,
 * así que lo que se prueba acá es justamente eso: que el número no vuelva a
 * quedar clavado en el HTML.
 */
class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_muestra_los_totales_reales_del_dataset(): void
    {
        $this->crearPais('AR', 'Argentina');
        $this->crearPais('CA', 'Canada');

        $this->crearCiudad(1, 'Rosario', 'AR');
        $this->crearCiudad(2, 'Cordoba', 'AR');
        $this->crearCiudad(3, 'Toronto', 'CA');

        $this->get('/')
            ->assertOk()
            ->assertSee('3 cities', false)
            ->assertSee('2 countries', false);
    }

    /**
     * El alcance es global. El proyecto nació como la versión mundial de
     * calles-argentinas y la home arrastraba ese encuadre; hoy el mercado
     * elegido es el anglo, que es el 83,1% de los ingresos.
     *
     * No se asienta sobre el titular concreto —eso es copy y cambia— sino
     * sobre lo que la página promete: cuántos países cubre y que no se
     * presenta como un sitio de un país.
     */
    public function test_se_presenta_con_alcance_global(): void
    {
        $this->crearPais('AR', 'Argentina');
        $this->crearCiudad(1, 'Rosario', 'AR');

        $respuesta = $this->get('/')->assertOk();

        $respuesta->assertDontSee('Address Finder for Argentina');
        $respuesta->assertSee('countries');
    }

    /**
     * "Reverse address lookup" es el 73% de los clics del sitio. Describe lo
     * contrario de lo que el producto hace —de calle a mapa, no de dirección a
     * persona— y aun así no se toca: lo que hay que hacer es que la página
     * entregue algo real para esa consulta.
     */
    public function test_el_titulo_conserva_la_frase_que_trae_el_trafico(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Reverse Address Lookup', false);
    }

    /**
     * Fuera el FAQ genérico, "Why Use Our Address Finder" y "Our Commitment to
     * You". Y de paso se va la afirmación del FAQ de que no se guarda la IP,
     * que era falsa: `SESSION_DRIVER=database` y la tabla `sessions` tiene la
     * columna cargada.
     */
    public function test_no_quedan_los_bloques_de_relleno(): void
    {
        $respuesta = $this->get('/')->assertOk();

        $respuesta->assertDontSee('Why Use Our Address Finder');
        $respuesta->assertDontSee('Our Commitment to You');
        $respuesta->assertDontSee('Frequently Asked Questions');
        $respuesta->assertDontSee('we do not store your IP address');
    }

    private function crearPais(string $id, string $nombre): void
    {
        DB::table('paises')->insert([
            'id' => $id, 'nombre' => $nombre, 'url' => strtolower($nombre),
            'centro_x' => 0, 'centro_y' => 0, 'zoom' => 4,
        ]);
    }

    private function crearCiudad(int $id, string $nombre, string $paisId): void
    {
        DB::table('ciudades')->insert([
            'id' => $id,
            'nombre' => $nombre,
            'nombre_min' => strtolower($nombre),
            'url' => strtolower($nombre).'-'.strtolower($paisId),
            'pais_id' => $paisId,
            'provincia_id' => $paisId.'.01',
            'departamento_id' => '1',
            'centro_x' => 0, 'centro_y' => 0,
            'limite_sw_x' => 0, 'limite_sw_y' => 0,
            'limite_ne_x' => 0, 'limite_ne_y' => 0,
            'zoom' => 13,
            'visitas' => 0,
        ]);
    }
}
