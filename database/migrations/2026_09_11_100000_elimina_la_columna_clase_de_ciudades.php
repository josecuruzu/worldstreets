<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * `ciudades.clase` se agregó en worldstreets sin semántica definida y sin
 * equivalente en el origen argentino, así que el importador la venía llenando
 * con una copia de `fcode` solo para no dejarla vacía (tenía un TODO puesto).
 *
 * No aporta nada: es `varchar(15) NOT NULL` idéntica a `fcode` en las 6.921
 * filas, y no la lee nadie en la app. El par fclass/fcode de GeoNames ya cubre
 * la clasificación. Se elimina.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ciudades', function (Blueprint $table) {
            $table->dropColumn('clase');
        });
    }

    public function down(): void
    {
        Schema::table('ciudades', function (Blueprint $table) {
            // Con default '' para poder agregarla a una tabla que ya tiene
            // filas sin depender de doctrine/dbal; el contenido se repone abajo.
            $table->string('clase', 15)->default('');
        });

        DB::table('ciudades')->update(['clase' => DB::raw('fcode')]);
    }
};
