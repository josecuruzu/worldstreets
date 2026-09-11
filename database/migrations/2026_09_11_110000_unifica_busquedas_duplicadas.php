<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * El import trajo del origen 84 grupos de filas (181 en total) que son la
 * misma calle de la misma ciudad: difieren en mayúsculas, en espacios, o
 * arrastran un BOM invisible. Una incluso está partida en 8 filas.
 *
 * Mientras existieron, `registrar()` tenía que elegir a mano cuál de las
 * gemelas incrementar, y el ranking podía listar la misma calle dos veces.
 * Acá se fusionan sumando los contadores en la fila dominante y se pone el
 * índice único que impide que vuelva a pasar.
 *
 * El agrupado lo resuelve la base con la collation de la columna, que en
 * MySQL (utf8mb4_unicode_ci) es insensible a mayúsculas y trata el BOM como
 * ignorable — que es exactamente el criterio con el que `registrar()` busca.
 * En SQLite la collation por defecto es binaria, así que el índice es más
 * estricto allá; no afecta a los tests, que parten de una tabla vacía.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $grupos = DB::table('busquedas')
                ->select('ciudad_id', 'nombre_calle_compacto')
                ->groupBy('ciudad_id', 'nombre_calle_compacto')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            foreach ($grupos as $grupo) {
                $filas = DB::table('busquedas')
                    ->where('ciudad_id', $grupo->ciudad_id)
                    ->where('nombre_calle_compacto', $grupo->nombre_calle_compacto)
                    // La dominante gana: conserva la grafía que más gente usó,
                    // que suele ser la limpia. El id desempata para que la
                    // migración sea determinista.
                    ->orderByDesc('cantidad_busquedas')
                    ->orderBy('id')
                    ->get();

                $superviviente = $filas->first();
                $total = $filas->sum('cantidad_busquedas');

                DB::table('busquedas')
                    ->where('id', $superviviente->id)
                    ->update(['cantidad_busquedas' => $total]);

                DB::table('busquedas')
                    ->whereIn('id', $filas->skip(1)->pluck('id'))
                    ->delete();
            }
        });

        Schema::table('busquedas', function (Blueprint $table) {
            $table->unique(['ciudad_id', 'nombre_calle_compacto'], 'busqueda_unica_por_ciudad');
        });
    }

    public function down(): void
    {
        // Las filas fusionadas no vuelven: quedaron sumadas en la dominante.
        Schema::table('busquedas', function (Blueprint $table) {
            $table->dropUnique('busqueda_unica_por_ciudad');
        });
    }
};
