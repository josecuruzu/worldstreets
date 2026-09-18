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

    public function test_ya_no_se_presenta_como_un_sitio_argentino(): void
    {
        $this->crearPais('AR', 'Argentina');
        $this->crearCiudad(1, 'Rosario', 'AR');

        $respuesta = $this->get('/')->assertOk();

        $respuesta->assertDontSee('Address Finder for Argentina');
        $respuesta->assertSee('Address Finder Worldwide');
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
