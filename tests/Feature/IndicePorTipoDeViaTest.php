<?php

namespace Tests\Feature;

use App\Models\Busqueda;
use App\Support\TipoDeVia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * El índice de calles agrupado por tipo de vía.
 *
 * Agrupar por la letra inicial funciona en inglés, donde el tipo va al final
 * —`Macleod Trail SE`—, y no funciona en español, donde va adelante. Medido
 * sobre el corpus real: **el 81% de las calles colombianas empieza con «C»**
 * de Calle y Carrera, y con el tope de 120 por grupo la ficha publicaba el 4%
 * de sus calles. Soacha es la consulta en español con más clics del sitio.
 *
 * Después del cambio, medido contra la base real: Medellín pasó de 120 de
 * 2.750 calles visibles a 642, y Bogotá de 120 de 372 a las 372.
 */
class IndicePorTipoDeViaTest extends TestCase
{
    use RefreshDatabase;

    private const COLOMBIANA = 3674962;

    private const CANADIENSE = 5913490;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('continentes')->insert([
            ['id' => 'SA', 'nombre' => 'South America', 'url' => 'south-america',
                'centro_x' => -15, 'centro_y' => -60, 'zoom' => 3, 'descripcion' => ''],
            ['id' => 'NA', 'nombre' => 'North America', 'url' => 'north-america',
                'centro_x' => 45, 'centro_y' => -100, 'zoom' => 3, 'descripcion' => ''],
        ]);
        DB::table('paises')->insert([
            ['id' => 'CO', 'nombre' => 'Colombia', 'url' => 'colombia', 'continente_id' => 'SA',
                'centro_x' => 4, 'centro_y' => -73, 'zoom' => 5],
            ['id' => 'CA', 'nombre' => 'Canada', 'url' => 'canada', 'continente_id' => 'NA',
                'centro_x' => 56, 'centro_y' => -106, 'zoom' => 4],
        ]);
        DB::table('provincias')->insert([
            ['id' => 'CO.02', 'nombre' => 'Antioquia', 'url' => 'antioquia', 'pais_id' => 'CO',
                'centro_x' => 6.2, 'centro_y' => -75.6],
            ['id' => 'CA.01', 'nombre' => 'Alberta', 'url' => 'alberta', 'pais_id' => 'CA',
                'centro_x' => 55, 'centro_y' => -115],
        ]);

        $this->crearCiudad(self::COLOMBIANA, 'Medellin', 'CO', 'CO.02');
        $this->crearCiudad(self::CANADIENSE, 'Calgary', 'CA', 'CA.01');

        // Una cuadrícula colombiana en chico, con el orden desordenado a
        // propósito para que se vea que el índice ordena.
        $this->calles(self::COLOMBIANA, [
            'calle 10', 'calle 2', 'calle 1', 'calle 10a', 'calle 1 sur',
            'carrera 7', 'carrera 70', 'carrera 7b',
            'diagonal 25', 'transversal 4', 'vereda la loma',
            'el poblado', 'san juan', '80',
        ]);

        $this->calles(self::CANADIENSE, ['macleod trail se', '14 ave sw', 'bow street']);
    }

    private function crearCiudad(int $id, string $nombre, string $pais, string $provincia): void
    {
        DB::table('ciudades')->insert([
            'id' => $id,
            'nombre' => $nombre, 'nombre_min' => mb_strtolower($nombre), 'url' => mb_strtolower($nombre),
            'fcode' => 'PPLA', 'population' => 100000,
            'pais_id' => $pais, 'provincia_id' => $provincia, 'departamento_id' => $provincia.'.1',
            'centro_x' => 6.25, 'centro_y' => -75.56,
            'limite_sw_x' => 6.1, 'limite_sw_y' => -75.7,
            'limite_ne_x' => 6.4, 'limite_ne_y' => -75.4,
            'zoom' => 13, 'visitas' => 0, 'tiene_calles' => true,
        ]);
    }

    /** @param  list<string>  $nombres */
    private function calles(int $ciudad, array $nombres): void
    {
        foreach ($nombres as $nombre) {
            DB::table('busquedas')->insert([
                'ciudad_id' => $ciudad,
                'nombre_calle' => $nombre,
                'nombre_calle_compacto' => Busqueda::compactar($nombre),
                'cantidad_busquedas' => 1,
            ]);
        }
    }

    // ── el agrupado ──────────────────────────────────────────────────────

    public function test_agrupa_por_tipo_de_via_en_espanol(): void
    {
        $indice = Busqueda::indiceDeCiudad(self::COLOMBIANA, 'CO');

        $this->assertSame(
            ['calle', 'carrera', 'diagonal', 'transversal', 'vereda', '#', 'E', 'S'],
            $indice->keys()->all(),
            'Los tipos van primero y en orden de peso, después # y después las letras.'
        );
    }

    /**
     * Y en inglés NO: el tipo va al final del nombre, así que la letra inicial
     * ya es la del nombre real. Canadá son 471.503 calles que no hay por qué
     * tocar.
     */
    public function test_en_ingles_sigue_agrupando_por_letra(): void
    {
        $indice = Busqueda::indiceDeCiudad(self::CANADIENSE, 'CA');

        $this->assertSame(['#', 'B', 'M'], $indice->keys()->all());
        $this->assertFalse($indice->every(fn (array $g) => $g['tipo']));
    }

    /** El tipo no se repite adentro del grupo: el grupo ya se llama así. */
    public function test_el_nombre_del_grupo_no_se_repite_en_cada_fila(): void
    {
        $indice = Busqueda::indiceDeCiudad(self::COLOMBIANA, 'CO');

        $this->assertSame(
            ['1', '1 Sur', '2', '10', '10a'],
            $indice['calle']['calles']->all()
        );
    }

    /**
     * Orden numérico y no alfabético. Sobre una cuadrícula numerada, el orden
     * de texto da 1, 10, 10a, 2 — que no es una lista, es un acertijo.
     */
    public function test_ordena_los_numeros_como_numeros(): void
    {
        $indice = Busqueda::indiceDeCiudad(self::COLOMBIANA, 'CO');

        $this->assertSame(['7', '7b', '70'], $indice['carrera']['calles']->all());
    }

    public function test_marca_cuales_grupos_son_tipos_de_via(): void
    {
        $indice = Busqueda::indiceDeCiudad(self::COLOMBIANA, 'CO');

        $this->assertTrue($indice['calle']['tipo']);
        $this->assertFalse($indice['S']['tipo']);
        $this->assertFalse($indice['#']['tipo']);
    }

    // ── la ficha ─────────────────────────────────────────────────────────

    public function test_la_ficha_colombiana_muestra_la_barra_de_tipos(): void
    {
        $this->get('/colombia/antioquia/medellin-3674962')
            ->assertOk()
            ->assertSee('href="#letra-calle"', false)
            ->assertSee('>Calle<', false)
            ->assertSee('href="#letra-carrera"', false)
            ->assertSee('id="letra-calle"', false);
    }

    public function test_la_ficha_canadiense_no_la_muestra(): void
    {
        $this->get('/canada/alberta/calgary-5913490')
            ->assertOk()
            ->assertDontSee('href="#letra-calle"', false)
            ->assertSee('href="#letra-m"', false);
    }

    // ── la normalización de abreviaturas ─────────────────────────────────

    public static function abreviaturas(): array
    {
        return [
            'cl' => ['cl 34f', 'CO', 'calle 34f'],
            'cr' => ['cr 32d', 'CO', 'carrera 32d'],
            'punto' => ['C.Pertera Leonortia', 'ES', 'calle Pertera Leonortia'],
            'psje' => ['psje bolivar', 'UY', 'pasaje bolivar'],
            'cno' => ['cno las pleyades', 'UY', 'camino las pleyades'],
            'ave' => ['Ave 1ra', 'CU', 'avenida 1ra'],
            // Ya canónico: no se toca.
            'calle' => ['calle 23', 'CU', 'calle 23'],
        ];
    }

    #[DataProvider('abreviaturas')]
    public function test_expande_las_abreviaturas(string $entra, string $pais, string $sale): void
    {
        $this->assertSame($sale, TipoDeVia::normalizar($entra, $pais));
    }

    /**
     * Lo que NO se toca, verificado contra filas reales de la base. Son los
     * casos que hacen que esta clase pueda escribir sobre el corpus sin
     * romperlo.
     */
    public static function intocables(): array
    {
        return [
            'un título, no un tipo' => ['dr carlos salvagno', 'UY'],
            'otro título' => ['gral artigas', 'UY'],
            'catalán, no abreviatura' => ['carrer major', 'ES'],
            'una palabra sola' => ['cl', 'CO'],
            'país no hispanohablante' => ['Macleod Trail SE', 'CA'],
            'abreviatura en país inglés' => ['cr 32d', 'CA'],
        ];
    }

    #[DataProvider('intocables')]
    public function test_no_toca_lo_que_no_es_un_tipo_de_via(string $nombre, string $pais): void
    {
        $this->assertSame($nombre, TipoDeVia::normalizar($nombre, $pais));
    }
}
