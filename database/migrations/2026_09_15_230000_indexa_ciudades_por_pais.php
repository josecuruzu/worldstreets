<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Contar los países que tienen ciudades — `count(distinct pais_id)` — costaba
 * 7,4 segundos sin índice, porque recorría los 4,7 M de registros. Con este
 * índice la base lo resuelve saltando de valor en valor.
 *
 * Es el `paisindex` que traía el esquema de producción y que las migraciones
 * no tenían. La home lo usa para decir cuántos países cubre, en vez del número
 * escrito a mano que quedó viejo cuando los datos dejaron de ser solo de
 * Argentina.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ciudades', function (Blueprint $table) {
            $table->index('pais_id', 'ciudades_pais');
        });
    }

    public function down(): void
    {
        Schema::table('ciudades', function (Blueprint $table) {
            $table->dropIndex('ciudades_pais');
        });
    }
};
