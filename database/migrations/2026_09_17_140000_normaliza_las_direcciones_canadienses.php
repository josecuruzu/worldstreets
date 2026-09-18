<?php

use App\Models\Busqueda;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/*
 * Los registros canadienses no son calles: son direcciones.
 *
 *   227a cedarbrae ave          ← con altura
 *   33-35 roland st             ← con rango de alturas
 *   garth massey dr unit 7      ← con unidad
 *   fisher mills rd rear
 *
 * Canadá son 482.100 filas, el mercado elegido (es el único RPM que se
 * mantuvo: −12% contra −60% de Estados Unidos) y el insumo del índice A–Z de
 * calles. Un índice alfabético donde "cedarbrae ave" aparece una vez por cada
 * altura que alguien buscó no es un índice de calles.
 *
 * Medido antes de escribir esto:
 *
 *   filas canadienses          482.100
 *   filas candidatas            18.682
 *   filas al terminar          471.502
 *   Waterloo (Ontario)      9.419 -> 5.036
 *
 * El HANDOFF estimaba 355.306 y ese número es de otra cosa: es
 * COUNT(DISTINCT nombre) sobre todo Canadá, o sea cuántos nombres de calle
 * distintos hay en el país, no cuántas filas quedan. Las filas son por ciudad,
 * y "main st" existe en cientos de ciudades sin que ninguna sea duplicado de
 * otra.
 *
 * Lo que NO se toca:
 *
 * - Los ordinales. 3.555 filas empiezan con número y son el nombre de la calle
 *   —`6th ave w`, `2nd ave e`—. Verificado contra la base: cero de ellas
 *   matchean el patrón de altura, porque después del número traen dos letras y
 *   el patrón admite una sola antes del espacio.
 * - Los números sueltos (`958823`, `9/13175`): son ruido tecleado, no
 *   direcciones, y sacarles algo no los mejora. Si se decide limpiarlos es otra
 *   migración, con su propio criterio.
 */
return new class extends Migration
{
    /**
     * La altura al principio: `227a `, `33-35 `, `620b `.
     *
     * El `[a-z]?` es una sola letra a propósito: es lo que separa una altura
     * (`227a cedarbrae ave`) de un ordinal (`6th ave w`), que trae dos.
     */
    private const ALTURA = '/^\d+[a-z]?(\s*-\s*\d+[a-z]?)?\s+/iu';

    /** El sufijo de unidad: `unit 7`, `apt 3`, `rear`, `bsmt`. */
    private const UNIDAD = '/\s+(unit|apt|suite|ste|rear|upper|lower|bsmt|basement|floor|fl)(\s*[0-9a-z]*)?$/iu';

    /**
     * Tipos de vía y direccionales: las palabras que NO alcanzan para nombrar
     * una calle. Si al sacar la altura no queda ninguna otra, el número era el
     * nombre y no la altura.
     */
    private const SIN_NOMBRE = '/^(?:(?:ave|avenue|st|street|rd|road|dr|drive|blvd|boulevard|cres|crescent|way|pl|place|ct|court|ln|lane|tr|trail|pkwy|parkway|cir|circle|sq|square|gate|gdns|gardens|hwy|highway|close|terr|terrace|row|walk|mews|grove|hts|heights|n|s|e|w|ne|nw|se|sw|north|south|east|west)\s*)+$/iu';

    /** Equivalentes en la sintaxis de MySQL, para el prefiltro. */
    private const ALTURA_SQL = '^[0-9]+[a-z]?([ ]*-[ ]*[0-9]+[a-z]?)?[ ]+';

    private const UNIDAD_SQL = '[ ]+(unit|apt|suite|ste|rear|upper|lower|bsmt|basement|floor|fl)([ ]*[0-9a-z]*)?$';

    public function up(): void
    {
        /*
         * El prefiltro usa REGEXP, que SQLite no trae. No es una limitación: en
         * los tests la tabla arranca vacía, así que no hay nada que normalizar.
         */
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $originales = $this->candidatas()->get();

        if ($originales->isEmpty()) {
            return;
        }

        $this->respaldar($originales);

        foreach ($originales->chunk(500) as $tanda) {
            DB::transaction(function () use ($tanda) {
                foreach ($tanda as $fila) {
                    $this->normalizarFila($fila);
                }
            });
        }
    }

    public function down(): void
    {
        // La altura y la unidad no se reponen, y las filas fusionadas quedaron
        // sumadas en la dominante. El respaldo en storage/app/backups es lo que
        // queda del estado anterior.
    }

    /**
     * Las filas canadienses que traen altura o sufijo de unidad.
     *
     * El join contra `ciudades` acota a Canadá: es el único corpus con este
     * problema, y aplicar el recorte al resto del mundo sacaría alturas donde
     * no las hay. Va por SQL porque la tabla tiene más de un millón de filas y
     * las candidatas son el 3,9% de las canadienses.
     */
    private function candidatas(): Builder
    {
        return DB::table('busquedas as b')
            ->join('ciudades as c', function ($join) {
                $join->on('c.id', '=', 'b.ciudad_id')->where('c.pais_id', '=', 'CA');
            })
            ->select(['b.id', 'b.ciudad_id', 'b.nombre_calle', 'b.nombre_calle_compacto', 'b.cantidad_busquedas'])
            ->where(function (Builder $query) {
                $query
                    ->whereRaw('b.nombre_calle REGEXP ?', [self::ALTURA_SQL])
                    ->orWhereRaw('b.nombre_calle REGEXP ?', [self::UNIDAD_SQL]);
            })
            ->orderBy('b.id');
    }

    /**
     * El nombre sin la altura ni el sufijo de unidad.
     *
     * Se pasa además por `Busqueda::limpiar()`, que es el criterio con el que
     * entra todo lo que se busca desde el sitio: sacar la altura puede dejar
     * espacios dobles.
     */
    public static function normalizar(string $nombre): string
    {
        $limpio = preg_replace(self::UNIDAD, '', $nombre) ?? $nombre;

        $sinAltura = preg_replace(self::ALTURA, '', $limpio) ?? $limpio;

        /*
         * Calgary, Edmonton y Saskatoon numeran las calles: en `14 Ave SW` el
         * número ES el nombre, no la altura. Sacárselo deja "Ave SW", que no
         * nombra nada.
         *
         * Los ordinales ya quedaban afuera por la forma del patrón, pero estos
         * no: `14 Ave SW` es indistinguible de `14 Cedarbrae Ave` mirando solo
         * el principio. Lo que los separa es lo que queda después: si no sobra
         * ninguna palabra que no sea un tipo de vía o un direccional, el número
         * era el nombre.
         *
         * Medido: sin esta guarda la migración destruye 5.882 nombres de
         * calle del mercado que el proyecto eligió, y no falla al hacerlo —
         * se descubrió corriéndola y comparando contra el respaldo.
         */
        if ($sinAltura !== $limpio && preg_match(self::SIN_NOMBRE, trim($sinAltura))) {
            return Busqueda::limpiar($limpio);
        }

        return Busqueda::limpiar($sinAltura);
    }

    private function normalizarFila(object $fila): void
    {
        $normalizado = self::normalizar($fila->nombre_calle);
        $compacto = Busqueda::compactar($normalizado);

        if ($compacto === $fila->nombre_calle_compacto) {
            return;
        }

        /*
         * Si recortar no deja nada, la fila era solo una altura o solo la
         * palabra "unit". No queda calle que guardar y `registrar()` tampoco la
         * aceptaría hoy.
         */
        if ($compacto === '') {
            DB::table('busquedas')->where('id', $fila->id)->delete();

            return;
        }

        $gemela = DB::table('busquedas')
            ->where('ciudad_id', $fila->ciudad_id)
            ->where('nombre_calle_compacto', $compacto)
            ->where('id', '<>', $fila->id)
            ->first();

        if ($gemela === null) {
            DB::table('busquedas')->where('id', $fila->id)->update([
                'nombre_calle' => $normalizado,
                'nombre_calle_compacto' => $compacto,
            ]);

            return;
        }

        $this->fusionar($fila, $normalizado, $compacto, $gemela);
    }

    /**
     * Al sacarle la altura, esta fila pasó a ser la misma calle que otra que ya
     * estaba. Gana la más buscada —el id menor desempata— y se queda con la
     * suma, que es el criterio de f835a98 y de la migración de limpieza.
     */
    private function fusionar(object $fila, string $normalizado, string $compacto, object $gemela): void
    {
        $suyas = (int) $fila->cantidad_busquedas;
        $ajenas = (int) $gemela->cantidad_busquedas;
        $total = $suyas + $ajenas;

        $ganaLaGemela = $ajenas > $suyas || ($ajenas === $suyas && (int) $gemela->id < (int) $fila->id);

        if ($ganaLaGemela) {
            DB::table('busquedas')->where('id', $gemela->id)->update(['cantidad_busquedas' => $total]);
            DB::table('busquedas')->where('id', $fila->id)->delete();

            return;
        }

        // Se borra primero la gemela: si no, el índice único rechazaría el
        // update, porque por un instante las dos filas tendrían el mismo par.
        DB::table('busquedas')->where('id', $gemela->id)->delete();
        DB::table('busquedas')->where('id', $fila->id)->update([
            'nombre_calle' => $normalizado,
            'nombre_calle_compacto' => $compacto,
            'cantidad_busquedas' => $total,
        ]);
    }

    /** Al lado de los respaldos que dejaron f835a98 y la migración de limpieza. */
    private function respaldar(Collection $originales): void
    {
        $carpeta = storage_path('app/backups');
        File::ensureDirectoryExists($carpeta);

        File::put(
            $carpeta.'/busquedas-canada-'.now()->format('Ymd-His').'.json',
            $originales->toJson(JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }
};
