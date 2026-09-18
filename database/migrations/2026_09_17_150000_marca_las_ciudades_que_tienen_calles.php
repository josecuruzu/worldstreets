<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * `ciudades.tiene_calles`: si alguien buscó alguna calle en esa ciudad.
 *
 * Es la columna sobre la que se apoya la regla de ciudad-con-contenido. De las
 * 4.697.141 ciudades del dataset, 18.361 tienen calles; el resto es un punto en
 * el mapa. Publicarlas todas como páginas es lo que dejó al sitio con
 * 18.283.514 URLs descubiertas y 26.335 indexadas — 0,14%.
 *
 * Por qué una columna y no un EXISTS. Las fichas de provincia y país listan
 * ciudades ordenadas por población, y filtrar con una subconsulta obliga a
 * recorrer el índice hasta juntar las 60 que hacen falta. Medido contra la
 * provincia más grande (IN.36, 155.023 ciudades):
 *
 *     sin filtrar                    1 ms
 *     con EXISTS                   621 ms   (330.380 filas recorridas)
 *
 * Los dos índices de listado se reemplazan por su versión con la columna en el
 * medio, en vez de agregar dos nuevos: `ciudades` ya carga 966 MB de índices y
 * el orden (lugar, tiene_calles, population) resuelve filtro y orden de una
 * sola pasada. Las consultas que solo filtran por provincia o país siguen
 * andando: la columna de lugar sigue siendo el prefijo del índice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ciudades', function (Blueprint $tabla) {
            $tabla->boolean('tiene_calles')->default(false)->after('visitas');
        });

        $this->marcarLasQueTienenCalles();

        Schema::table('ciudades', function (Blueprint $tabla) {
            $tabla->dropIndex('ciudades_provincia_poblacion');
            $tabla->dropIndex('ciudades_pais_poblacion');
        });

        Schema::table('ciudades', function (Blueprint $tabla) {
            $tabla->index(['provincia_id', 'tiene_calles', 'population'], 'ciudades_provincia_poblacion');
            $tabla->index(['pais_id', 'tiene_calles', 'population'], 'ciudades_pais_poblacion');
        });
    }

    public function down(): void
    {
        Schema::table('ciudades', function (Blueprint $tabla) {
            $tabla->dropIndex('ciudades_provincia_poblacion');
            $tabla->dropIndex('ciudades_pais_poblacion');
        });

        Schema::table('ciudades', function (Blueprint $tabla) {
            $tabla->index(['provincia_id', 'population'], 'ciudades_provincia_poblacion');
            $tabla->index(['pais_id', 'population'], 'ciudades_pais_poblacion');
            $tabla->dropColumn('tiene_calles');
        });
    }

    /**
     * Un UPDATE con join contra los ciudad_id distintos de `busquedas`.
     *
     * Son 18.361 ciudades sobre 4,7 M, así que se recorre la lista corta y se
     * tocan solo esas filas, en vez de pasar por la tabla entera preguntando.
     */
    private function marcarLasQueTienenCalles(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            // SQLite (los tests): sin sintaxis de UPDATE con join, y con la
            // tabla vacía no hay nada que marcar.
            DB::table('ciudades')
                ->whereIn('id', DB::table('busquedas')->select('ciudad_id')->distinct())
                ->update(['tiene_calles' => true]);

            return;
        }

        DB::statement('
            UPDATE ciudades c
            JOIN (SELECT DISTINCT ciudad_id FROM busquedas) b ON b.ciudad_id = c.id
            SET c.tiene_calles = 1
        ');
    }
};
