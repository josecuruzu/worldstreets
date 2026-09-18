<?php

namespace Tests\Feature;

use App\Models\Busqueda;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
 * Cubre POST /api/busquedas, el endpoint que suma al ranking de calles.
 *
 * Lo que más importa acá es que la tabla busquedas quedó autoincremental
 * (2026_09_11_090000): antes de esa migración un insert sin id explícito
 * fallaba, así que `registrarUnaCalleNueva` es la prueba de regresión de eso.
 *
 * Las filas se arman con DB::table en vez de factories porque las tablas de
 * dominio todavía no tienen ninguna.
 */
class RegistroBusquedasTest extends TestCase
{
    use RefreshDatabase;

    private const CIUDAD_ID = 2567;

    protected function setUp(): void
    {
        parent::setUp();

        // Estas tablas heredaron de GeoNames un montón de columnas NOT NULL sin
        // default, así que la jerarquía mínima igual pide bastante relleno.
        DB::table('continentes')->insert([
            'id' => 'SA', 'nombre' => 'South America', 'url' => 'south-america',
            'centro_x' => -14.0, 'centro_y' => -57.0, 'zoom' => 3, 'descripcion' => '',
        ]);
        DB::table('paises')->insert([
            'id' => 'AR', 'nombre' => 'Argentina', 'url' => 'argentina', 'continente_id' => 'SA',
            'centro_x' => -38.4, 'centro_y' => -63.6, 'zoom' => 4,
        ]);
        DB::table('provincias')->insert([
            'id' => 'AR.21', 'nombre' => 'Santa Fe', 'url' => 'santa-fe', 'pais_id' => 'AR',
            'centro_x' => -31.6, 'centro_y' => -60.7,
        ]);
        DB::table('ciudades')->insert([
            'id' => self::CIUDAD_ID,
            'nombre' => 'Rosario',
            'nombre_min' => 'rosario',
            'url' => 'rosario-santa-fe',
            'pais_id' => 'AR',
            'provincia_id' => 'AR.21',
            'departamento_id' => '82084',
            'centro_x' => -32.94682,
            'centro_y' => -60.63932,
            'limite_sw_x' => -33.0, 'limite_sw_y' => -60.7,
            'limite_ne_x' => -32.9, 'limite_ne_y' => -60.6,
            'zoom' => 13,
            'visitas' => 100,
        ]);
    }

    public function test_registrar_una_calle_nueva_le_asigna_id_solo(): void
    {
        $respuesta = $this->postJson('/api/busquedas', [
            'ciudad_id' => self::CIUDAD_ID,
            'nombre_calle' => 'san martin',
        ]);

        $respuesta->assertNoContent();

        $busqueda = Busqueda::firstWhere('nombre_calle_compacto', 'sanmartin');

        $this->assertNotNull($busqueda, 'no se insertó la búsqueda');
        $this->assertGreaterThan(0, $busqueda->id, 'el id no se autogeneró');
        $this->assertSame(1, $busqueda->cantidad_busquedas);
        $this->assertSame('san martin', $busqueda->nombre_calle);
        $this->assertSame(101, (int) DB::table('ciudades')->where('id', self::CIUDAD_ID)->value('visitas'));
    }

    public function test_una_calle_ya_registrada_suma_en_vez_de_duplicar(): void
    {
        $existente = Busqueda::create([
            'ciudad_id' => self::CIUDAD_ID,
            'nombre_calle' => 'san martin',
            'nombre_calle_compacto' => 'sanmartin',
            'cantidad_busquedas' => 826,
        ]);

        // Escrito distinto, pero es la misma calle una vez compactada.
        $this->postJson('/api/busquedas', [
            'ciudad_id' => self::CIUDAD_ID,
            'nombre_calle' => 'sanmartin',
        ])->assertNoContent();

        $this->assertSame(1, Busqueda::count(), 'se duplicó la fila en vez de incrementarla');
        $this->assertSame(827, $existente->fresh()->cantidad_busquedas);
    }

    public function test_la_misma_calle_dos_veces_en_la_misma_sesion_cuenta_una(): void
    {
        $cuerpo = ['ciudad_id' => self::CIUDAD_ID, 'nombre_calle' => 'san martin'];

        $this->postJson('/api/busquedas', $cuerpo)->assertNoContent();
        $this->postJson('/api/busquedas', $cuerpo)->assertNoContent();

        $this->assertSame(1, Busqueda::firstWhere('nombre_calle_compacto', 'sanmartin')->cantidad_busquedas);
        // La visita tampoco se cuenta dos veces.
        $this->assertSame(101, (int) DB::table('ciudades')->where('id', self::CIUDAD_ID)->value('visitas'));
    }

    public function test_el_indice_unico_impide_duplicar_una_calle(): void
    {
        /*
         * Fija el contrato del que depende el catch de `registrar()`: con el
         * índice de 2026_09_11_110000 puesto, dar de alta dos veces la misma
         * calle tira UniqueConstraintViolationException en vez de dejar una
         * fila repetida. Eso es lo que pasa cuando dos peticiones simultáneas
         * pasan las dos por el select que no la encuentra.
         *
         * Verifica la excepción y el índice, no el catch en sí: forzar esa
         * ventana desde un test de integración pediría concurrencia real, y el
         * cuerpo del catch es el mismo increment que ya cubre
         * `una_calle_ya_registrada_suma_en_vez_de_duplicar`.
         */
        DB::table('busquedas')->insert([
            'ciudad_id' => self::CIUDAD_ID,
            'nombre_calle' => 'san martin',
            'nombre_calle_compacto' => 'sanmartin',
            'cantidad_busquedas' => 1,
        ]);

        $duplicado = fn () => Busqueda::create([
            'ciudad_id' => self::CIUDAD_ID,
            'nombre_calle' => 'san martin',
            'nombre_calle_compacto' => 'sanmartin',
            'cantidad_busquedas' => 1,
        ]);

        $this->assertThrows($duplicado, UniqueConstraintViolationException::class);
        $this->assertSame(1, Busqueda::count());
    }

    public function test_limpia_los_caracteres_invisibles_del_nombre(): void
    {
        // Un BOM adelante, un carácter de control en el medio, espacios de más
        // y un acento descompuesto: todo lo que el buscador viejo dejó entrar.
        $sucio = "\u{FEFF}Av  San\u{0011} Marti\u{0301}n ";

        $this->postJson('/api/busquedas', [
            'ciudad_id' => self::CIUDAD_ID,
            'nombre_calle' => $sucio,
        ])->assertNoContent();

        $busqueda = Busqueda::sole();

        $this->assertSame('Av San Martín', $busqueda->nombre_calle);
        $this->assertSame('AvSanMartín', $busqueda->nombre_calle_compacto);
    }

    public function test_un_nombre_que_es_solo_basura_invisible_no_se_guarda(): void
    {
        /*
         * Acá no llega ni a `limpiar()`: el TrimStrings de Laravel ya recorta
         * BOM y caracteres de ancho cero, así que el campo queda vacío y lo
         * frena `required`. Se deja fijado porque es el comportamiento que
         * queremos — un 422, no una fila en blanco.
         */
        $this->postJson('/api/busquedas', [
            'ciudad_id' => self::CIUDAD_ID,
            'nombre_calle' => "\u{FEFF}\u{200B}",
        ])->assertStatus(422)->assertJsonValidationErrors('nombre_calle');

        $this->assertSame(0, Busqueda::count());
        // Tampoco cuenta como visita a la ciudad.
        $this->assertSame(100, (int) DB::table('ciudades')->where('id', self::CIUDAD_ID)->value('visitas'));
    }

    public function test_rechaza_una_ciudad_que_no_existe(): void
    {
        $this->postJson('/api/busquedas', ['ciudad_id' => 999999, 'nombre_calle' => 'san martin'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ciudad_id');

        $this->assertSame(0, Busqueda::count());
    }

    public function test_rechaza_un_nombre_de_calle_vacio(): void
    {
        $this->postJson('/api/busquedas', ['ciudad_id' => self::CIUDAD_ID, 'nombre_calle' => '   '])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nombre_calle');

        $this->assertSame(0, Busqueda::count());
    }

    public function test_registrar_invalida_el_ranking_cacheado(): void
    {
        Busqueda::create([
            'ciudad_id' => self::CIUDAD_ID,
            'nombre_calle' => 'san martin',
            'nombre_calle_compacto' => 'sanmartin',
            'cantidad_busquedas' => 826,
        ]);

        // Deja el top cacheado.
        $this->getJson('/api/ciudades/'.self::CIUDAD_ID.'/calles')
            ->assertJsonPath('calles.0.busquedas', 826);

        $this->postJson('/api/busquedas', [
            'ciudad_id' => self::CIUDAD_ID,
            'nombre_calle' => 'san martin',
        ])->assertNoContent();

        $this->getJson('/api/ciudades/'.self::CIUDAD_ID.'/calles')
            ->assertJsonPath('calles.0.busquedas', 827);
    }

    /**
     * Una calle recién buscada tiene que aparecer en el índice A–Z de la ficha,
     * que es la página que Google recorre. El índice va cacheado 12 horas: sin
     * invalidarlo, la ficha vacía seguiría diciendo "be the first and you start
     * it" después de que alguien ya la empezó.
     */
    public function test_una_calle_nueva_aparece_en_la_ficha_de_la_ciudad(): void
    {
        // Antes de la primera búsqueda la ciudad no tiene página: es la regla
        // de ciudad-con-contenido, que deja fuera a 4,68 M de fichas vacías.
        $this->get('/argentina/santa-fe/rosario-'.self::CIUDAD_ID)->assertStatus(410);

        $this->withSession([])->postJson('/api/busquedas', [
            'ciudad_id' => self::CIUDAD_ID,
            'nombre_calle' => 'adderley street',
        ])->assertNoContent();

        // Y la gana sola con la primera calle que alguien busque.
        $this->get('/argentina/santa-fe/rosario-'.self::CIUDAD_ID)
            ->assertOk()
            ->assertSee('Adderley Street');
    }
}
