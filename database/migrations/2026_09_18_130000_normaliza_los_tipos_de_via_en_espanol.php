<?php

use App\Models\Busqueda;
use App\Support\Idioma;
use App\Support\TipoDeVia;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Expande las abreviaturas del tipo de vía en el corpus hispanohablante.
 *
 * `cl 13` y `Calle 13` son la misma calle de Soacha guardadas en dos filas,
 * con el contador partido y las dos ocupando lugar en la lista que publica la
 * ficha. Medido antes de correr esto: **4.009 filas candidatas** de las 19.676
 * calles hispanohablantes —el 20%—, concentradas en Colombia (2.687) y
 * Uruguay (874), y **710 grupos con más de una grafía** solo en Colombia.
 *
 * El criterio de fusión es el de `f835a98` y el de la migración canadiense:
 * gana la más buscada, el id menor desempata, y el ganador se queda con la
 * suma de los contadores. El respaldo de lo que se borra va a
 * `storage/app/backups/`, porque el nombre viejo no se puede reconstruir.
 *
 * Qué NO toca, y está verificado contra filas reales de la base:
 *
 * - **Los países no hispanohablantes.** En inglés el tipo de vía va al final
 *   —`Macleod Trail SE`— así que no hay abreviatura inicial que expandir, y
 *   Canadá son 471.503 calles. `TipoDeVia::normalizar()` lo corta por país.
 * - **Los títulos.** `dr carlos salvagno`, `gral artigas`, `sr ...`: parecen
 *   abreviaturas y son tratamientos. No están en la tabla de equivalencias.
 * - **`carrer`**, que es catalán, no una abreviatura de «calle».
 * - **Una palabra sola.** `cl` sin nada detrás no se expande: no sabemos si es
 *   una calle o el nombre entero.
 *
 * @see \App\Support\TipoDeVia
 */
return new class extends Migration
{
    /**
     * El prefiltro en sintaxis de MySQL. Es el mismo juego de abreviaturas que
     * `TipoDeVia::ABREVIATURAS`, escrito para que el motor descarte el 80% de
     * las filas sin traerlas a PHP.
     */
    private const ABREVIATURAS_SQL = '^(cl|cll|c|cr|cra|kra|kr|av|ave|avda|avd|psje|pje|cno|tv|trans|dg|diag|vda|sda|ctra|crta|autop|bul|blvd)[. ]';

    public function up(): void
    {
        /*
         * El prefiltro usa REGEXP, que SQLite no trae. En los tests la tabla
         * arranca vacía, así que no hay nada que normalizar.
         */
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $candidatas = $this->candidatas()->get();

        if ($candidatas->isEmpty()) {
            return;
        }

        $this->respaldar($candidatas);

        foreach ($candidatas->chunk(500) as $tanda) {
            DB::transaction(function () use ($tanda) {
                foreach ($tanda as $fila) {
                    $this->normalizarFila($fila);
                }
            });
        }
    }

    public function down(): void
    {
        // Las filas fusionadas quedaron sumadas en la dominante y el nombre
        // abreviado no se repone. El respaldo es lo que queda del estado
        // anterior.
    }

    /**
     * Las filas hispanohablantes que abren con una abreviatura conocida.
     */
    private function candidatas(): Builder
    {
        return DB::table('busquedas as b')
            ->join('ciudades as c', function ($join) {
                $join->on('c.id', '=', 'b.ciudad_id')->whereIn('c.pais_id', Idioma::PAISES);
            })
            ->select([
                'b.id', 'b.ciudad_id', 'b.nombre_calle',
                'b.nombre_calle_compacto', 'b.cantidad_busquedas', 'c.pais_id',
            ])
            ->whereRaw('LOWER(b.nombre_calle) REGEXP ?', [self::ABREVIATURAS_SQL])
            ->orderBy('b.id');
    }

    private function normalizarFila(object $fila): void
    {
        $normalizado = Busqueda::limpiar(TipoDeVia::normalizar($fila->nombre_calle, $fila->pais_id));
        $compacto = Busqueda::compactar($normalizado);

        if ($compacto === '' || $compacto === $fila->nombre_calle_compacto) {
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
     * Expandida, esta fila pasó a ser la misma calle que otra que ya estaba.
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

        // Se borra primero la gemela: si no, el índice único rechaza el update
        // porque por un instante las dos filas tendrían el mismo par.
        DB::table('busquedas')->where('id', $gemela->id)->delete();
        DB::table('busquedas')->where('id', $fila->id)->update([
            'nombre_calle' => $normalizado,
            'nombre_calle_compacto' => $compacto,
            'cantidad_busquedas' => $total,
        ]);
    }

    /** Al lado de los respaldos que dejaron f835a98 y las otras limpiezas. */
    private function respaldar(Collection $candidatas): void
    {
        $carpeta = storage_path('app/backups');
        File::ensureDirectoryExists($carpeta);

        $archivo = $carpeta.'/tipos-de-via-'.now()->format('Ymd-His').'.json';

        File::put($archivo, json_encode($candidatas, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
};
