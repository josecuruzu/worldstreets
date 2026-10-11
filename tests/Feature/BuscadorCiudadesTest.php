<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
 * Cubre GET /api/ciudades, el autocompletado de la home.
 *
 * Lo que fija esta clase es la semántica de `Ciudad::scopeBuscar()`: se busca
 * por prefijo de palabra, no por subcadena. En MySQL eso lo resuelve el índice
 * fulltext (2026_09_15_210000) y en SQLite una emulación con LIKE, así que los
 * casos de acá valen para los dos motores.
 *
 * Las filas se arman con DB::table en vez de factories porque las tablas de
 * dominio todavía no tienen ninguna.
 */
class BuscadorCiudadesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('continentes')->insert([
            'id' => 'SA', 'nombre' => 'South America', 'url' => 'south-america',
            'centro_x' => -14.0, 'centro_y' => -57.0, 'zoom' => 3, 'descripcion' => '',
        ]);
        DB::table('paises')->insert([
            'id' => 'AR', 'nombre' => 'Argentina', 'url' => 'argentina', 'continente_id' => 'SA',
            'centro_x' => -38.4, 'centro_y' => -63.6, 'zoom' => 4,
        ]);
        DB::table('provincias')->insert([
            'id' => 'AR.14', 'nombre' => 'Cordoba', 'url' => 'cordoba', 'pais_id' => 'AR',
            'centro_x' => -32.1, 'centro_y' => -63.8,
        ]);

        // Villa Cordobita tiene más visitas que Córdoba a propósito: sirve para
        // ver que el empezar-con gana sobre el ser-más-visitada.
        $this->crearCiudad(1, 'Córdoba', 'cordoba', 500, 'PPLA');
        $this->crearCiudad(2, 'Villa Cordobita', 'villa cordobita', 900);
        $this->crearCiudad(3, 'New York City', 'new york city', 1000);
        $this->crearCiudad(4, 'San Juan', 'san juan', 5);
        $this->crearCiudad(5, 'Villa San Martin', 'villa san martin', 900);
    }

    public function test_encuentra_una_ciudad_por_el_principio_del_nombre(): void
    {
        $this->getJson('/api/ciudades?q=cordo')
            ->assertOk()
            ->assertJsonPath('0.nombre', 'Córdoba')
            ->assertJsonPath('0.provincia', 'Cordoba')
            ->assertJsonPath('0.pais', 'Argentina')
            ->assertJsonPath('0.capital', true);
    }

    public function test_encuentra_por_una_palabra_del_medio_del_nombre(): void
    {
        // El caso que un LIKE 'york%' no resuelve y que motivó el fulltext.
        $this->getJson('/api/ciudades?q=york')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.nombre', 'New York City');
    }

    public function test_no_encuentra_por_un_pedazo_del_medio_de_una_palabra(): void
    {
        // Cambio de comportamiento deliberado: antes `LIKE '%ordo%'` traía
        // Córdoba, pero eso costaba un full scan de la tabla entera.
        $this->getJson('/api/ciudades?q=ordo')
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_el_que_empieza_con_el_termino_va_primero_aunque_tenga_menos_visitas(): void
    {
        $this->getJson('/api/ciudades?q=san')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.nombre', 'San Juan')
            ->assertJsonPath('1.nombre', 'Villa San Martin');
    }

    public function test_entre_los_que_empiezan_igual_manda_el_mas_poblado(): void
    {
        // El caso Santiago: la de Cuba tenía más visitas que la de Chile, que
        // es capital y tiene 4,8 M de habitantes, y le ganaba en el ranking.
        $this->crearCiudad(6, 'Cordoba Chica', 'cordoba chica', visitas: 900, population: 12000);
        $this->crearCiudad(7, 'Cordoba Grande', 'cordoba grande', visitas: 0, population: 4837295);

        $this->getJson('/api/ciudades?q=cordoba')
            ->assertOk()
            ->assertJsonPath('0.nombre', 'Cordoba Grande')
            ->assertJsonPath('1.nombre', 'Cordoba Chica');
    }

    public function test_sin_poblacion_cargada_desempatan_las_visitas(): void
    {
        // Para el 92% de las ciudades la población viene en cero o nula, así
        // que las visitas siguen siendo el criterio que queda.
        $this->crearCiudad(6, 'Cordoba La Nueva', 'cordoba la nueva', 900);

        $this->getJson('/api/ciudades?q=cordoba')
            ->assertOk()
            ->assertJsonPath('0.nombre', 'Cordoba La Nueva')
            ->assertJsonPath('1.nombre', 'Córdoba');
    }

    public function test_ignora_los_operadores_de_busqueda(): void
    {
        // En modo booleano estos caracteres son operadores; si llegaran crudos
        // al índice, la consulta sería un error de sintaxis de SQL.
        $this->getJson('/api/ciudades?q='.urlencode('+cordo* -("~'))
            ->assertOk()
            ->assertJsonPath('0.nombre', 'Córdoba');
    }

    public function test_encuentra_una_ciudad_por_su_nombre_local(): void
    {
        // El dataset nombra a las ciudades con su exónimo inglés, así que sin
        // mirar `alternatenames` nadie puede buscar en su propio idioma.
        $this->crearCiudad(6, 'Munich', 'munich', 0, alternatenames: 'München,Munique,Monaco di Baviera,ミュンヘン');

        $this->getJson('/api/ciudades?q=Munique')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.nombre', 'Munich');
    }

    public function test_el_nombre_local_tambien_pesa_en_el_orden(): void
    {
        // "Tokio" devolvía un paraje de Bolivia que se llama así en inglés,
        // antes que Tokyo, porque el empujón por prefijo solo miraba el nombre
        // en inglés y Tokyo únicamente podía coincidir por el nombre local.
        $this->crearCiudad(6, 'Tokio', 'tokio', 0, population: 300);
        $this->crearCiudad(7, 'Tokyo', 'tokyo', 0, population: 37000000, alternatenames: 'Tokio,東京,Tóquio');

        $this->getJson('/api/ciudades?q=Tokio')
            ->assertOk()
            ->assertJsonPath('0.nombre', 'Tokyo')
            ->assertJsonPath('1.nombre', 'Tokio');
    }

    public function test_no_busca_con_menos_de_dos_caracteres(): void
    {
        $this->getJson('/api/ciudades?q=c')
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_un_termino_sin_letras_ni_numeros_no_devuelve_nada(): void
    {
        $this->getJson('/api/ciudades?q='.urlencode('***'))
            ->assertOk()
            ->assertJsonCount(0);
    }

    /**
     * El bug que este caso fija no se veía: la sugerencia salía completa y
     * correcta, solo `ruta` venía en null. Con eso el enlace a la ficha no
     * apareció nunca en la home —ni para Calgary, que tiene 8.013 calles—,
     * porque la columna `tiene_calles` que mira `rutaDeLaFicha()` no estaba en
     * el SELECT del autocompletado y el cast la daba por falsa.
     */
    public function test_la_sugerencia_trae_la_ruta_de_la_ficha(): void
    {
        $this->crearCiudad(20, 'Rosario', 'rosario', 0, tieneCalles: true);

        $this->getJson('/api/ciudades?q=Rosario')
            ->assertOk()
            ->assertJsonPath('0.ruta', url('/argentina/cordoba/rosario-20'));
    }

    /** Y sigue en null cuando la ciudad no tiene ficha, que es lo que evita
     *  ofrecer un enlace a una página que responde 410. */
    public function test_la_sugerencia_no_trae_ruta_sin_calles(): void
    {
        $this->crearCiudad(21, 'Villa Maria', 'villa maria', 0);

        $this->getJson('/api/ciudades?q=Villa')
            ->assertOk()
            ->assertJsonPath('0.ruta', null);
    }

    /**
     * `pais` acota el autocompletado a un país. Lo usa el buscador de la ficha
     * de país, que antes no existía.
     */
    public function test_el_autocompletado_se_puede_acotar_a_un_pais(): void
    {
        $this->crearCiudad(30, 'London', 'london', 0);

        DB::table('paises')->insert([
            'id' => 'GB', 'nombre' => 'United Kingdom', 'url' => 'united-kingdom', 'continente_id' => 'SA',
            'centro_x' => 54, 'centro_y' => -2, 'zoom' => 5,
        ]);
        DB::table('provincias')->insert([
            'id' => 'GB.ENG', 'nombre' => 'England', 'url' => 'england', 'pais_id' => 'GB',
            'centro_x' => 52, 'centro_y' => -1,
        ]);
        DB::table('ciudades')->insert([
            'id' => 31, 'nombre' => 'London', 'nombre_min' => 'london', 'url' => 'london-england',
            'fcode' => 'PPLC', 'pais_id' => 'GB', 'provincia_id' => 'GB.ENG', 'departamento_id' => 'GB.ENG.1',
            'centro_x' => 51.5, 'centro_y' => -0.12,
            'limite_sw_x' => 51.2, 'limite_sw_y' => -0.5,
            'limite_ne_x' => 51.7, 'limite_ne_y' => 0.3,
            'zoom' => 11, 'visitas' => 0,
        ]);

        // Sin filtro aparecen las dos.
        $this->getJson('/api/ciudades?q=London')->assertOk()->assertJsonCount(2);

        // Con filtro, solo la del país pedido.
        $this->getJson('/api/ciudades?q=London&pais=argentina')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.pais', 'Argentina');
    }

    /** Un slug de país que no existe no puede devolver el mundo entero. */
    public function test_un_pais_inexistente_no_devuelve_nada(): void
    {
        $this->crearCiudad(32, 'Rosario', 'rosario', 0);

        $this->getJson('/api/ciudades?q=Rosario&pais=narnia')
            ->assertOk()
            ->assertJsonCount(0);
    }

    /** Y la caché no puede confundir el resultado acotado con el global. */
    public function test_el_filtro_de_pais_no_comparte_cache_con_la_busqueda_global(): void
    {
        $this->crearCiudad(33, 'Parana', 'parana', 0);

        $this->getJson('/api/ciudades?q=Parana')->assertOk()->assertJsonCount(1);
        $this->getJson('/api/ciudades?q=Parana&pais=narnia')->assertOk()->assertJsonCount(0);
        $this->getJson('/api/ciudades?q=Parana')->assertOk()->assertJsonCount(1);
    }

    private function crearCiudad(
        int $id,
        string $nombre,
        string $nombreMin,
        int $visitas,
        ?string $fcode = 'PPL',
        ?int $population = null,
        ?string $alternatenames = null,
        bool $tieneCalles = false,
    ): void {
        DB::table('ciudades')->insert([
            'id' => $id,
            'nombre' => $nombre,
            'nombre_min' => $nombreMin,
            'url' => str_replace(' ', '-', $nombreMin),
            'fcode' => $fcode,
            'population' => $population,
            'alternatenames' => $alternatenames,
            'pais_id' => 'AR',
            'provincia_id' => 'AR.14',
            'departamento_id' => '14014',
            'centro_x' => -31.4, 'centro_y' => -64.1,
            'limite_sw_x' => -31.5, 'limite_sw_y' => -64.2,
            'limite_ne_x' => -31.3, 'limite_ne_y' => -64.0,
            'zoom' => 13,
            'visitas' => $visitas,
            'tiene_calles' => $tieneCalles,
        ]);
    }
}
