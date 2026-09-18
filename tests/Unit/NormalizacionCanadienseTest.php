<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * El recorte que aplica la migración 2026_09_17_140000 sobre el corpus
 * canadiense.
 *
 * Los registros canadienses no son calles, son direcciones: traen la altura
 * adelante y a veces la unidad atrás. Son 482.100 filas, el mercado elegido y
 * el insumo del índice A–Z, así que el recorte tiene que sacar la altura sin
 * llevarse por delante los nombres que empiezan con número de verdad.
 *
 * El riesgo está ahí: 3.555 filas canadienses empiezan con un número que es el
 * nombre de la calle (`6th ave w`). Tocarlas rompería 3.555 calles reales, y
 * ningún test de base lo detectaría porque la migración no falla: deja mal el
 * dato.
 */
class NormalizacionCanadienseTest extends TestCase
{
    private static function normalizar(string $nombre): string
    {
        $migracion = require database_path('migrations/2026_09_17_140000_normaliza_las_direcciones_canadienses.php');

        return $migracion::normalizar($nombre);
    }

    /** @return list<array{0: string, 1: string}> */
    public static function direccionesConAltura(): array
    {
        return [
            ['227a cedarbrae ave', 'cedarbrae ave'],
            ['620b pinerow cres', 'pinerow cres'],
            ['33-35 roland st', 'roland st'],
            ['33 - 35 roland st', 'roland st'],
            ['12 main st', 'main st'],
        ];
    }

    #[DataProvider('direccionesConAltura')]
    public function test_saca_la_altura_del_principio(string $crudo, string $esperado): void
    {
        $this->assertSame($esperado, self::normalizar($crudo));
    }

    /** @return list<array{0: string, 1: string}> */
    public static function direccionesConUnidad(): array
    {
        return [
            ['garth massey dr unit 7', 'garth massey dr'],
            ['fisher mills rd unit 47', 'fisher mills rd'],
            ['rife rd rear', 'rife rd'],
            ['king st apt 3', 'king st'],
            ['queen ave suite 200', 'queen ave'],
            ['bay st bsmt', 'bay st'],
        ];
    }

    #[DataProvider('direccionesConUnidad')]
    public function test_saca_el_sufijo_de_unidad(string $crudo, string $esperado): void
    {
        $this->assertSame($esperado, self::normalizar($crudo));
    }

    public function test_saca_las_dos_cosas_a_la_vez(): void
    {
        $this->assertSame('cedarbrae ave', self::normalizar('227a cedarbrae ave unit 12'));
    }

    /**
     * Lo que más importa del recorte: 3.555 filas canadienses empiezan con un
     * número que ES el nombre de la calle. El patrón admite una sola letra
     * entre el número y el espacio, y un ordinal trae dos, que es lo que las
     * separa.
     */
    #[DataProvider('nombresQueEmpiezanConNumero')]
    public function test_no_toca_las_calles_numeradas(string $nombre): void
    {
        $this->assertSame($nombre, self::normalizar($nombre));
    }

    /** @return list<array{0: string}> */
    public static function nombresQueEmpiezanConNumero(): array
    {
        return [
            ['6th ave w'], ['2nd ave e'], ['3rd ave e'], ['1st avenue'],
            ['10th st'], ['21st street'], ['102nd ave'], ['53rd st'],
        ];
    }

    /**
     * Los números sueltos son ruido tecleado, no direcciones. Recortarles algo
     * no los mejora, así que quedan tal cual: limpiarlos es otra decisión.
     */
    public function test_deja_en_paz_los_numeros_sueltos(): void
    {
        foreach (['958823', '9450045', '9/13175', '101ave'] as $ruido) {
            $this->assertSame($ruido, self::normalizar($ruido));
        }
    }

    /** Las palabras de unidad solo cuentan al final, y precedidas de espacio. */
    public function test_no_recorta_una_palabra_de_unidad_que_es_parte_del_nombre(): void
    {
        $this->assertSame('lower sackville dr', self::normalizar('lower sackville dr'));
        $this->assertSame('upper james st', self::normalizar('upper james st'));
        $this->assertSame('rear guard rd', self::normalizar('rear guard rd'));
    }

    /**
     * Un nombre que es solo una altura o solo la palabra "unit" no tiene de
     * dónde recortar: el patrón exige que haya algo después. La migración borra
     * la fila únicamente cuando el recorte deja el nombre vacío, y estos casos
     * no llegan ahí.
     */
    public function test_un_nombre_sin_calle_queda_como_esta(): void
    {
        $this->assertSame('227a', self::normalizar('227a'));
        $this->assertSame('unit 7', self::normalizar('unit 7'));
    }

    public function test_los_nombres_limpios_no_cambian(): void
    {
        foreach (['heritage meadows rd se', 'riverwood cres S', 'a boesak street', 'main st'] as $nombre) {
            $this->assertSame($nombre, self::normalizar($nombre));
        }
    }

    /** Sacar la altura puede dejar espacios de más; el nombre sale listo para guardar. */
    public function test_el_resultado_sale_limpio(): void
    {
        $this->assertSame('cedarbrae ave', self::normalizar('227a   cedarbrae  ave'));
    }
}
