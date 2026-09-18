<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Las fichas de país y de provincia listan sus ciudades de mayor a menor
 * población, y sin índice eso es un scan de los 4,7 M de registros por cada
 * visita. La provincia más grande tiene 155.023 ciudades, así que el orden
 * tiene que salir del índice y no de un filesort.
 *
 * `ciudades_pais_poblacion` reemplaza a `ciudades_pais`: empieza por la misma
 * columna, así que sigue sirviendo para contar los países del dataset, y de
 * paso ordena por población sin trabajo extra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ciudades', function (Blueprint $table) {
            $table->dropIndex('ciudades_pais');
            $table->index(['pais_id', 'population'], 'ciudades_pais_poblacion');
            $table->index(['provincia_id', 'population'], 'ciudades_provincia_poblacion');
        });
    }

    public function down(): void
    {
        Schema::table('ciudades', function (Blueprint $table) {
            $table->dropIndex('ciudades_pais_poblacion');
            $table->dropIndex('ciudades_provincia_poblacion');
            $table->index('pais_id', 'ciudades_pais');
        });
    }
};
