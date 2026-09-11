<?php

namespace Tests\Feature;

use App\Models\Busqueda;
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

        DB::table('continentes')->insert(['id' => 'SA', 'nombre' => 'South America']);
        DB::table('paises')->insert(['id' => 'AR', 'nombre' => 'Argentina', 'continente_id' => 'SA']);
        DB::table('provincias')->insert(['id' => 'AR.21', 'nombre' => 'Santa Fe', 'pais_id' => 'AR']);
        DB::table('ciudades')->insert([
            'id' => self::CIUDAD_ID,
            'nombre' => 'Rosario',
            'nombre_min' => 'rosario',
            'url' => 'rosario-santa-fe',
            'pais_id' => 'AR',
            'provincia_id' => 'AR.21',
            'centro_x' => -32.94682,
            'centro_y' => -60.63932,
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
}
