<?php

namespace Tests\Feature;

use App\Models\Busqueda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
 * Cubre las fichas de país, provincia y ciudad: las páginas que hacen que las
 * ciudades del dataset existan como URL propia y no solo como resultado del
 * buscador.
 *
 * Lo que más importa acá es la resolución de la ficha de ciudad. El slug no
 * identifica nada — hay 433.217 grupos de URLs repetidas, 578 de ellas
 * llamadas "Krajan" en la misma provincia — así que la ficha se resuelve por
 * id, la jerarquía de la URL tiene que coincidir, y un slug viejo redirige al
 * canónico en vez de servir contenido duplicado.
 */
class FichasDeLugarTest extends TestCase
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

        $this->crearCiudad(3369157, 'Cape Town', population: 3433441);
        $this->crearCiudad(3361414, 'Stellenbosch', population: 77476);

        // Una ciudad tiene página cuando alguien buscó una calle ahí, así que
        // las dos del fixture necesitan al menos una para existir.
        $this->crearCalle(3369157, 'seed street', 0);
        $this->crearCalle(3361414, 'dorp street', 0);
    }

    public function test_la_ficha_de_pais_lista_sus_provincias_y_sus_ciudades_grandes(): void
    {
        $this->get('/south-africa')
            ->assertOk()
            ->assertSee('South Africa')
            ->assertSee('Western Cape')
            ->assertSee('Cape Town')
            ->assertSee('/south-africa/western-cape', false);
    }

    /**
     * El orden de "Best covered cities" es por CALLES, no por población.
     *
     * Es el cambio de contenido con más valor de intención de la ficha de
     * país: el visitante que llega de `reverse address lookup canada` viene a
     * encontrar una dirección, y para eso una ciudad con 8.014 calles le sirve
     * y una más poblada con 3 no. En Canadá, Toronto tiene más gente que
     * Calgary; Calgary tiene la lista.
     */
    public function test_las_ciudades_del_pais_se_ordenan_por_calles_y_no_por_poblacion(): void
    {
        // Stellenbosch tiene 77.476 habitantes contra los 3.433.441 de Cape
        // Town, y acá tres calles contra una.
        $this->crearCalle(3361414, 'andringa street', 0);
        $this->crearCalle(3361414, 'bird street', 0);

        $html = $this->get('/south-africa')->assertOk()->getContent();

        $this->assertStringContainsString('Best covered cities', $html);
        $this->assertLessThan(
            strpos($html, 'Cape Town', strpos($html, 'Best covered cities')),
            strpos($html, 'Stellenbosch', strpos($html, 'Best covered cities')),
            'Stellenbosch tiene más calles, así que va primero.'
        );
    }

    /** Y cada región declara sus calles, no su cantidad de ciudades. */
    public function test_la_ficha_de_pais_declara_las_calles_de_cada_region(): void
    {
        $this->get('/south-africa')
            ->assertOk()
            ->assertSee('Streets on record in each region', false);
    }

    /**
     * La ficha de país tiene buscador.
     *
     * Es la página más eficiente del sitio —246 URLs se llevaron 2.951 clics—
     * y era la más pasiva: el visitante tenía que elegir región, después
     * ciudad, y recién ahí podía buscar una dirección.
     */
    public function test_la_ficha_de_pais_tiene_buscador_acotado_al_pais(): void
    {
        $respuesta = $this->get('/south-africa')->assertOk();

        $respuesta->assertSee('buscadorDePais(', false);
        $respuesta->assertSee('south-africa', false);
        // Y el h1 lleva la frase que rankea, no el nombre del país a secas.
        $respuesta->assertSee('Reverse address lookup in South Africa', false);
    }

    /**
     * El bloque de formato de dirección.
     *
     * Es lo que atiende las 41 consultas estadounidenses de código postal que
     * hoy dan 528 impresiones y cero clics, y lo único de valor que la ficha
     * de país puede dar cuando el país tiene pocas calles.
     */
    public function test_la_ficha_de_pais_explica_como_se_escribe_una_direccion(): void
    {
        $respuesta = $this->get('/south-africa')->assertOk();

        $respuesta->assertSee('How an address works in South Africa', false);
        $respuesta->assertSee('Postal code', false);
        $respuesta->assertSee('8001', false);
        $respuesta->assertSee('Suburb', false);
    }

    /** Donde el dato no es firme no se inventa: el bloque no aparece. */
    public function test_un_pais_sin_ficha_de_formato_no_muestra_el_bloque(): void
    {
        DB::table('paises')->insert([
            'id' => 'KS', 'nombre' => 'Kosovo', 'url' => 'kosovo', 'continente_id' => 'AF',
            'centro_x' => 42.6, 'centro_y' => 20.9, 'zoom' => 8,
        ]);

        $this->get('/kosovo')
            ->assertOk()
            ->assertDontSee('How an address works in', false);
    }

    public function test_la_ficha_de_provincia_lista_las_ciudades_de_mayor_a_menor(): void
    {
        $respuesta = $this->get('/south-africa/western-cape')->assertOk();

        $respuesta->assertSeeInOrder(['Cape Town', 'Stellenbosch']);
    }

    /**
     * El índice va alfabético y no por contador: 1.017.913 de las 1.059.464
     * filas tienen el contador en 0, así que ordenar por popularidad es ordenar
     * por ruido. Antes la ficha decía "Most searched streets" y listaba por
     * cantidad; "Long Street" con 120 búsquedas iba antes que "Adderley" con 30.
     */
    public function test_el_indice_de_calles_va_alfabetico_y_no_por_popularidad(): void
    {
        $this->crearCalle(3369157, 'long street', 120);
        $this->crearCalle(3369157, 'adderley street', 30);

        $this->get('/south-africa/western-cape/cape-town-3369157')
            ->assertOk()
            ->assertSee('Cape Town')
            ->assertSeeInOrder(['Adderley Street', 'Long Street']);
    }

    /**
     * El tope de calles es por letra, no global.
     *
     * Con un tope global una ciudad grande lo agotaba en las primeras
     * iniciales: Cape Town llenaba las 2.000 entre `#`, A y B, y de la C a la Z
     * la barra salía apagada como si no tuviera ninguna calle. La página
     * afirmaba algo falso sobre su propio contenido, y ningún test lo veía
     * porque no fallaba nada.
     */
    public function test_todas_las_iniciales_entran_aunque_una_letra_sea_enorme(): void
    {
        for ($i = 0; $i < Busqueda::CALLES_POR_LETRA + 40; $i++) {
            $this->crearCalle(3369157, 'a street '.$i, 0);
        }
        $this->crearCalle(3369157, 'zebra lane', 0);

        $respuesta = $this->get('/south-africa/western-cape/cape-town-3369157')->assertOk();

        // La Z existe y es alcanzable, aunque la A se haya llevado el tope.
        $respuesta->assertSee('href="#letra-z"', false);
        $respuesta->assertSee('Zebra Lane');
    }

    /** Una letra recortada dice cuántas tiene de verdad, no solo las que muestra. */
    public function test_una_letra_recortada_declara_su_total(): void
    {
        $total = Busqueda::CALLES_POR_LETRA + 40;

        for ($i = 0; $i < $total; $i++) {
            $this->crearCalle(3369157, 'a street '.$i, 0);
        }

        $this->get('/south-africa/western-cape/cape-town-3369157')
            ->assertOk()
            ->assertSee(Busqueda::CALLES_POR_LETRA.' of '.$total);
    }

    /** Los nombres están guardados en minúscula; sin capitalizar se ven rotos. */
    public function test_el_indice_capitaliza_los_nombres(): void
    {
        $this->crearCalle(3369157, 'a boesak street', 0);

        $this->get('/south-africa/western-cape/cape-town-3369157')
            ->assertOk()
            ->assertSee('A Boesak Street')
            ->assertDontSee('>a boesak street<', false);
    }

    /**
     * Ningún contador a la vista. Mostrar "1 search" al lado de casi todas las
     * calles describe el ruido, no la demanda.
     */
    public function test_el_indice_no_muestra_contadores(): void
    {
        $this->crearCalle(3369157, 'long street', 120);

        $respuesta = $this->get('/south-africa/western-cape/cape-town-3369157')->assertOk();

        $respuesta->assertDontSee('120 searches');
        $respuesta->assertDontSee('Most searched');
    }

    /**
     * Las letras son anclas dentro de la misma página. Si fueran URLs, serían
     * 26 por ciudad × 18.361 ciudades, que es el problema que el sitio está
     * tratando de sacarse de encima.
     */
    /**
     * El buscador de la ficha busca en la ficha.
     *
     * Estuvo un rato siendo un `<form method="get">` que apuntaba a la home con
     * `street`, `number` y `city` en la query string — tres parámetros que
     * `home()` no lee. Buscar una calle desde la página que recibe el tráfico
     * de Google devolvía la home vacía.
     */
    public function test_el_buscador_de_la_ficha_no_manda_a_la_home(): void
    {
        $this->crearCalle(3369157, 'adderley street', 0);

        $respuesta = $this->get('/south-africa/western-cape/cape-town-3369157')->assertOk();

        $respuesta->assertDontSee('<form', false);
        $respuesta->assertDontSee('name="street"', false);
        // El componente de Alpine, con la ciudad ya resuelta: el paso 1 del
        // buscador no hace falta acá.
        // `addressFinderApp(` y no `addressFinderApp()`: desde que existe el
        // botón de compartir, el componente recibe la dirección que puede venir
        // de un enlace `/r/{codigo}`, y en una ficha normal eso es `null`.
        $respuesta->assertSee('addressFinderApp(', false);
        $respuesta->assertSee('buscar()', false);
        // Y el mapa es nuestro, no la página de OSM embebida: el marcador de
        // la dirección se mueve sin recargar nada.
        $respuesta->assertSee('pintarMapa($el, ciudad, resultado)', false);
    }

    public function test_la_barra_de_letras_no_crea_urls(): void
    {
        $this->crearCalle(3369157, 'adderley street', 0);

        $respuesta = $this->get('/south-africa/western-cape/cape-town-3369157')->assertOk();

        $respuesta->assertSee('href="#letra-a"', false);
        $respuesta->assertDontSee('letter=', false);
    }

    /** La letra sin calles queda a la vista pero apagada, sin enlace. */
    public function test_una_letra_sin_calles_no_es_un_enlace(): void
    {
        $this->crearCalle(3369157, 'adderley street', 0);

        $this->get('/south-africa/western-cape/cape-town-3369157')
            ->assertOk()
            ->assertDontSee('href="#letra-z"', false);
    }

    /**
     * Las calles numeradas van bajo `#`: en Canadá son calles de verdad
     * —`14 Ave SW`— y son 5.882 en el mercado elegido.
     */
    public function test_las_calles_numeradas_van_bajo_almohadilla(): void
    {
        $this->crearCalle(3369157, '14 ave sw', 0);

        $this->get('/south-africa/western-cape/cape-town-3369157')
            ->assertOk()
            ->assertSee('href="#letra-num"', false)
            ->assertSee('14 Ave SW');
    }

    /**
     * El orden alfabético empieza con direcciones mal tecleadas —una fila de
     * nombre vacío, `--102 scott st`—. Un índice que abre con eso se lee como
     * una página rota.
     */
    public function test_los_nombres_sin_inicial_quedan_fuera_del_indice(): void
    {
        $this->crearCalle(3369157, '--102 scott st', 0);
        $this->crearCalle(3369157, 'adderley street', 0);

        $this->get('/south-africa/western-cape/cape-town-3369157')
            ->assertOk()
            ->assertSee('Adderley Street')
            ->assertDontSee('102 Scott St');
    }

    public function test_muestra_los_nombres_locales_de_la_ciudad(): void
    {
        DB::table('ciudades')->where('id', 3369157)
            ->update(['alternatenames' => 'Kaapstad,Le Cap,Ciudad del Cabo,ケープタウン']);

        $this->get('/south-africa/western-cape/cape-town-3369157')
            ->assertOk()
            ->assertSee('Ciudad del Cabo')
            ->assertSee('ケープタウン', false);
    }

    /**
     * La regla de ciudad-con-contenido, que es el centro del arreglo del sitio.
     *
     * De las 4.697.141 ciudades, 18.361 tienen calles. Publicar el resto es lo
     * que dejó 18.283.514 URLs descubiertas contra 26.335 indexadas (0,14%).
     *
     * Antes esto se resolvía con `noindex,follow`, que para un sitio degradado
     * es demasiado permisivo: además, con 240 rastreos diarios contra 18 M de
     * URLs, un noindex que Google tiene que venir a leer no llega nunca.
     */
    public function test_una_ciudad_sin_calles_no_tiene_pagina(): void
    {
        $this->crearCiudad(3361413, 'Klipheuwel', population: 0);

        $this->get('/south-africa/western-cape/klipheuwel-3361413')->assertStatus(410);
    }

    /**
     * Ni siquiera con población: el criterio es haber sido usada, no ser
     * grande. Stellenbosch tiene 77.476 habitantes y hasta que alguien no
     * busque una calle ahí, no hay nada que mostrar que no esté ya en la ficha
     * de la provincia.
     */
    public function test_la_poblacion_sola_no_alcanza_para_tener_pagina(): void
    {
        $this->crearCiudad(3361415, 'Paarl', population: 112045);

        $this->get('/south-africa/western-cape/paarl-3361415')->assertStatus(410);
    }

    /** Y al revés: sin población pero con calles, la ficha existe. */
    public function test_una_ciudad_con_calles_tiene_pagina_aunque_no_tenga_poblacion(): void
    {
        $this->crearCiudad(3361412, 'Pniel', population: 0);
        $this->crearCalle(3361412, 'main road', 4);

        $this->get('/south-africa/western-cape/pniel-3361412')
            ->assertOk()
            ->assertDontSee('noindex', false);
    }

    /**
     * 410 y no 404. El 404 dice "no está, volvé a probar"; el 410, "no está y
     * no vuelve". Con 240 rastreos diarios esa diferencia decide cuánto tarda
     * la limpieza del índice.
     */
    public function test_la_ficha_sin_calles_no_devuelve_404(): void
    {
        $this->crearCiudad(3361416, 'Franschhoek', population: 0);

        $this->get('/south-africa/western-cape/franschhoek-3361416')
            ->assertStatus(410)
            ->assertStatus(410);
    }

    /**
     * Las listas no pueden enlazar a una ficha que responde 410: sería mandar
     * al visitante y al rastreador contra una pared.
     */
    public function test_las_listas_no_enlazan_ciudades_sin_pagina(): void
    {
        $this->crearCiudad(3361417, 'Ceres', population: 900000);

        $this->get('/south-africa/western-cape')
            ->assertOk()
            ->assertDontSee('Ceres');

        $this->get('/south-africa')
            ->assertOk()
            ->assertDontSee('Ceres');
    }

    public function test_un_slug_que_no_es_el_canonico_redirige(): void
    {
        $this->get('/south-africa/western-cape/ciudad-del-cabo-3369157')
            ->assertRedirect('/south-africa/western-cape/cape-town-3369157')
            ->assertStatus(301);
    }

    public function test_no_sirve_la_ficha_si_la_jerarquia_de_la_url_no_corresponde(): void
    {
        // Sin esto, cada camino que llevara al mismo id sería otra página con
        // el mismo contenido.
        $this->get('/south-africa/gauteng/cape-town-3369157')->assertNotFound();
    }

    public function test_una_ciudad_inexistente_da_404(): void
    {
        $this->get('/south-africa/western-cape/cape-town-99999999')->assertNotFound();
        $this->get('/south-africa/western-cape/cape-town')->assertNotFound();
    }

    public function test_las_rutas_de_la_aplicacion_ganan_sobre_las_de_lugares(): void
    {
        // `/{pais}` matchea cualquier segmento suelto, así que esto es la
        // prueba de que no se comió las rutas declaradas antes.
        $this->get('/login')->assertOk();
    }

    private function crearCiudad(int $id, string $nombre, int $population): void
    {
        DB::table('ciudades')->insert([
            'id' => $id,
            'nombre' => $nombre,
            'nombre_min' => strtolower($nombre),
            'url' => str_replace(' ', '-', strtolower($nombre)).'-western-cape-south-africa',
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
        ]);
    }

    private function crearCalle(int $ciudadId, string $nombre, int $cantidad): void
    {
        DB::table('busquedas')->insert([
            'ciudad_id' => $ciudadId,
            'nombre_calle' => $nombre,
            'nombre_calle_compacto' => str_replace(' ', '', $nombre),
            'cantidad_busquedas' => $cantidad,
        ]);

        // Lo mismo que hace BuscadorController::registrar(): la ciudad pasa a
        // tener página en cuanto alguien busca una calle ahí.
        DB::table('ciudades')->where('id', $ciudadId)->update(['tiene_calles' => true]);
    }
}
