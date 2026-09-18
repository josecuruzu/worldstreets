<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * `ciudades` venía con un solo índice: el primary key. Con las 6.921 ciudades
 * argentinas eso no se notaba, pero con las 4.697.141 del dataset global cada
 * tecleo del autocompletado era un full scan de 976 MB — `EXPLAIN` daba
 * `type: ALL` sobre 4,7 M de filas más un filesort, y la consulta tardaba
 * 8 segundos.
 *
 * Se agregan tres índices:
 *
 * - `ciudades_nombre_min_fulltext`, el que usa el buscador. Habilita
 *   `MATCH ... AGAINST ('cordo*' IN BOOLEAN MODE)`, que resuelve por prefijo
 *   de palabra: "york" encuentra "new york city", algo que un B-tree no puede
 *   hacer porque el comodín va al principio.
 * - `ciudades_nombre_min_ranking`, para los términos de menos de 3 caracteres.
 *   El fulltext de InnoDB los ignora (`innodb_ft_min_token_size` = 3), así que
 *   ahí `scopeBuscar()` cae a `LIKE 'ab%'`, que este índice resuelve por rango.
 *   Lleva además `population` y `visitas`, que son las dos columnas por las que
 *   ordena el ranking, para que la consulta se resuelva entera dentro del
 *   índice: un `LIKE 'sa%'` son 161.910 filas, y tener que ir a buscarles la
 *   población a la tabla costaba 2,8 segundos contra 321 ms así.
 * - `ciudades_url`, que es por donde busca `Ciudad::scopeByUrl()`.
 *
 * SQLite no tiene FULLTEXT, así que en los tests se crea solo el B-tree y
 * `scopeBuscar()` se bifurca por driver igual que acá.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ciudades', function (Blueprint $table) {
            $table->index(['nombre_min', 'population', 'visitas'], 'ciudades_nombre_min_ranking');
            $table->index('url', 'ciudades_url');

            if ($this->soportaFulltext()) {
                $table->fullText('nombre_min', 'ciudades_nombre_min_fulltext');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ciudades', function (Blueprint $table) {
            $table->dropIndex('ciudades_nombre_min_ranking');
            $table->dropIndex('ciudades_url');

            if ($this->soportaFulltext()) {
                $table->dropFullText('ciudades_nombre_min_fulltext');
            }
        });
    }

    private function soportaFulltext(): bool
    {
        return Schema::getConnection()->getDriverName() === 'mysql';
    }
};
