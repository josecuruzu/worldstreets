<?php

// database/migrations/xxxx_xx_xx_create_provincias_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProvinciasTable extends Migration
{
    public function up()
    {
        Schema::create('provincias', function (Blueprint $table) {
            $table->char('id', 15);
            $table->text('nombre')->nullable();
            $table->text('alias')->nullable();
            $table->integer('geonameid')->nullable();
            $table->string('url', 255);
            $table->string('pais_id', 2);
            $table->decimal('centro_x', 10, 7);
            $table->decimal('centro_y', 10, 7);

            $table->primary('id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('provincias');
    }
}