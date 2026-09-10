<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * La migración original de departamentos (2025_09_09_111449) quedó como stub:
 * creaba solo id + timestamps, pese a que el modelo Departamento declara
 * nombre, alias, geonameid, url, provincia_id, pais_id y centro_x/centro_y,
 * y a que ciudades.departamento_id es un varchar(105).
 *
 * Se rehace acá en vez de editar aquella porque ya está corrida. La tabla
 * está vacía, así que el drop no pierde nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('departamentos');

        Schema::create('departamentos', function (Blueprint $table) {
            // Mismo ancho que ciudades.departamento_id. En Argentina se puebla
            // con el código INDEC, que GeoNames usa como admin2.
            $table->string('id', 105)->primary();
            $table->string('nombre', 200)->nullable();
            $table->string('alias', 200)->nullable();
            $table->integer('geonameid')->nullable();
            $table->string('url', 255)->nullable();
            $table->string('provincia_id', 25);
            $table->string('pais_id', 2);
            $table->decimal('centro_x', 10, 7)->nullable();
            $table->decimal('centro_y', 10, 7)->nullable();

            $table->index(['pais_id', 'provincia_id'], 'departamento_pais_provincia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departamentos');

        // Se restaura el stub original para que el rollback sea simétrico.
        Schema::create('departamentos', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }
};
