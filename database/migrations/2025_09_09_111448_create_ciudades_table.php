<?php
// database/migrations/xxxx_xx_xx_create_ciudades_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateCiudadesTable extends Migration
{
    public function up()
    {
        Schema::create('ciudades', function (Blueprint $table) {
            $table->integer('id');
            $table->string('nombre', 200)->nullable();
            $table->string('alias', 200)->nullable();
            $table->string('nombre_min', 200);
            $table->string('url', 255);
            $table->text('alternatenames')->nullable();
            $table->decimal('centro_x', 10, 7)->nullable();
            $table->decimal('centro_y', 10, 7)->nullable();
            $table->decimal('limite_sw_x', 10, 7);
            $table->decimal('limite_sw_y', 10, 7);
            $table->decimal('limite_ne_x', 10, 7);
            $table->decimal('limite_ne_y', 10, 7);
            $table->integer('zoom');
            $table->char('fclass', 1)->nullable();
            $table->string('fcode', 10)->nullable();
            $table->string('pais_id', 2)->nullable();
            $table->string('cc2', 200)->nullable();
            $table->string('admin1', 20)->nullable();
            $table->string('admin2', 80)->nullable();
            $table->string('admin3', 20)->nullable();
            $table->string('admin4', 20)->nullable();
            $table->string('clase', 15);
            $table->string('provincia_id', 25);
            $table->string('departamento_id', 105);
            $table->integer('population')->nullable();
            $table->integer('elevation')->nullable();
            $table->integer('gtopo30')->nullable();
            $table->string('timezone', 40)->nullable();
            $table->date('moddate')->nullable();
            $table->integer('visitas')->default(0);
            $table->timestamp('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));

            $table->primary('id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ciudades');
    }
}