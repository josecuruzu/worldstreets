<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Las URLs del sitio viejo en CakePHP.
 *
 * La migración no convive: el Laravel reemplaza al Cake entero, en la misma
 * carpeta. El día del corte el Cake deja de existir, así que una URL vieja sin
 * destino no cae en ningún fallback: es un 404 para Google, en un sitio que ya
 * viene de una caída algorítmica. Estos tests son la red.
 *
 * Los slugs viejos salen de la columna `url` de cada tabla, que es la misma que
 * el Cake usaba para armar los enlaces.
 */
class RedireccionesDelSitioViejoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('continentes')->insert([
            'id' => 'AF', 'nombre' => 'Africa', 'url' => 'africa',
            'centro_x' => 0, 'centro_y' => 20, 'zoom' => 3, 'descripcion' => '',
        ]);
        DB::table('paises')->insert([
            'id' => 'ZA', 'nombre' => 'South Africa', 'url' => 'south-africa', 'continente_id' => 'AF',
            'capital' => 'Pretoria', 'population' => 59308690,
            'centro_x' => -30, 'centro_y' => 25, 'zoom' => 5,
        ]);
        DB::table('provincias')->insert([
            'id' => 'ZA.11', 'nombre' => 'Western Cape', 'url' => 'western-cape-south-africa', 'pais_id' => 'ZA',
            'centro_x' => -33.2, 'centro_y' => 21.8,
        ]);

        $this->crearCiudad(3369157, 'Cape Town', 'cape-town-western-cape-south-africa', 3433441);
    }

    private function crearCiudad(int $id, string $nombre, string $url, int $population): void
    {
        DB::table('ciudades')->insert([
            'id' => $id,
            'nombre' => $nombre,
            'nombre_min' => strtolower($nombre),
            'url' => $url,
            'fcode' => 'PPL',
            'population' => $population,
            'pais_id' => 'ZA',
            'provincia_id' => 'ZA.11',
            'departamento_id' => 'ZA.11.1',
            'centro_x' => -33.9, 'centro_y' => 18.4,
            'limite_sw_x' => -34, 'limite_sw_y' => 18.3,
            'limite_ne_x' => -33.8, 'limite_ne_y' => 18.5,
            'zoom' => 13,
            'visitas' => 0,
            // Con calles: una ciudad sin ellas no tiene ficha, así que la
            // redirección no tendría a dónde ir (ver el test de más abajo).
            'tiene_calles' => true,
        ]);
    }

    public function test_la_ficha_de_ciudad_vieja_redirige_a_la_nueva(): void
    {
        $this->get('/view/find/maps/address/cape-town-western-cape-south-africa')
            ->assertStatus(301)
            ->assertRedirect('/south-africa/western-cape/cape-town-3369157');
    }

    /**
     * El JSON-LD de la ficha del Cake declaraba la ciudad bajo
     * `/county/find/…`, una ruta que no existía. Google la descubrió y la
     * reportaba como 404: lleva el slug de la ciudad, así que va a su ficha.
     */
    public function test_la_url_del_json_ld_viejo_va_a_la_ficha_de_ciudad(): void
    {
        $this->get('/county/find/maps/address/cape-town-western-cape-south-africa')
            ->assertStatus(301)
            ->assertRedirect('/south-africa/western-cape/cape-town-3369157');

        $this->get('/county/find/maps/address/ciudad-que-no-existe')->assertStatus(410);
    }

    public function test_el_pais_viejo_redirige_al_nuevo(): void
    {

        $this->get('/country/maps/address/south-africa')
            ->assertStatus(301)
            ->assertRedirect('/south-africa');
    }

    /**
     * El slug de provincia cambia: el viejo traía la jerarquía pegada
     * (`western-cape-south-africa`) y el nuevo sale del nombre y cuelga del
     * país.
     */
    public function test_la_provincia_vieja_redirige_a_la_nueva(): void
    {

        $this->get('/state/maps/address/western-cape-south-africa')
            ->assertStatus(301)
            ->assertRedirect('/south-africa/western-cape');
    }

    /**
     * Cuatro idiomas publicaba el sitio viejo, y cada URL estaba multiplicada
     * por los cuatro. Todas van a la versión en inglés: el mercado anglo es el
     * 83,1% de los ingresos.
     */
    public function test_las_variantes_por_idioma_van_a_la_version_en_ingles(): void
    {

        foreach (['esp', 'por', 'fra', 'ita'] as $idioma) {
            $this->get("/{$idioma}/view/find/maps/address/cape-town-western-cape-south-africa")
                ->assertStatus(301)
                ->assertRedirect('/south-africa/western-cape/cape-town-3369157');

            $this->get("/{$idioma}/country/maps/address/south-africa")
                ->assertStatus(301)
                ->assertRedirect('/south-africa');
        }
    }

    public function test_la_home_por_idioma_va_a_la_home(): void
    {
        foreach (['esp', 'por', 'fra', 'ita'] as $idioma) {
            $this->get("/{$idioma}")->assertStatus(301)->assertRedirect('/');
        }
    }

    /**
     * 868 slugs de ciudad con tráfico matchean 996 filas. En los 65 casos
     * ambiguos el patrón es siempre el mismo: una ciudad real y duplicados de
     * GeoNames con población 0. Gana la de más población.
     */
    public function test_un_slug_ambiguo_va_a_la_ciudad_con_poblacion(): void
    {
        // El duplicado de GeoNames: mismo slug, sin población, id más chico.
        $this->crearCiudad(100, 'Duplicado', 'duplicada-western-cape-south-africa', 0);
        $this->crearCiudad(200, 'Real', 'duplicada-western-cape-south-africa', 500000);

        $this->get('/view/find/maps/address/duplicada-western-cape-south-africa')
            ->assertStatus(301)
            ->assertRedirect('/south-africa/western-cape/real-200');
    }

    /** A igualdad de población gana el id más chico, que es el que servía el Cake. */
    public function test_a_igual_poblacion_gana_el_id_mas_chico(): void
    {

        foreach ([[300, 'Primera'], [400, 'Segunda']] as [$id, $nombre]) {
            $this->crearCiudad($id, $nombre, 'empatadas-western-cape-south-africa', 1000);
        }

        $this->get('/view/find/maps/address/empatadas-western-cape-south-africa')
            ->assertStatus(301)
            ->assertRedirect('/south-africa/western-cape/primera-300');
    }

    /**
     * 410 y no 404. El 404 le dice a Google "no está, volvé a probar"; el 410,
     * "no está y no vuelve". Con 240 rastreos diarios contra 18 M de URLs
     * conocidas, esa diferencia decide cuánto tarda la limpieza.
     */
    public function test_condados_y_continentes_dan_410(): void
    {
        $this->get('/county/maps/address/cape-winelands-western-cape-south-africa')->assertStatus(410);
        $this->get('/continent/maps/address/europe')->assertStatus(410);
        $this->get('/esp/continent/maps/address/europe')->assertStatus(410);
    }

    public function test_un_slug_que_ya_no_existe_da_410(): void
    {

        $this->get('/view/find/maps/address/ciudad-que-no-existe')->assertStatus(410);
        $this->get('/country/maps/address/pais-que-no-existe')->assertStatus(410);
        $this->get('/state/maps/address/provincia-que-no-existe')->assertStatus(410);
    }

    /**
     * Una URL vieja que apunta a una ciudad sin calles da 410, no un 301 hacia
     * una ficha que también responde 410.
     *
     * Encadenar redirección y desaparición es la peor combinación para un
     * rastreador con 240 peticiones diarias: gasta dos para enterarse de lo
     * mismo. Medido contra Search Console, son 86 de las 868 fichas con
     * tráfico y 116 de 3.684 clics.
     */
    public function test_una_ciudad_sin_calles_da_410_en_vez_de_redirigir(): void
    {
        $this->crearCiudad(3361413, 'Klipheuwel', 'klipheuwel-western-cape-south-africa', 0);
        DB::table('ciudades')->where('id', 3361413)->update(['tiene_calles' => false]);

        $this->get('/view/find/maps/address/klipheuwel-western-cape-south-africa')
            ->assertStatus(410);
    }

    /**
     * Las rutas legadas comparten espacio de nombres con las fichas de lugar.
     * Si `/{pais}` llegara a ganarle a `/esp`, la home en español devolvería un
     * 404 en vez de redirigir.
     */
    public function test_las_rutas_nuevas_siguen_ganando(): void
    {

        $this->get('/south-africa')->assertStatus(200);
        $this->get('/south-africa/western-cape')->assertStatus(200);
    }
}
