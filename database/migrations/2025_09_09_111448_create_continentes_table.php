<?php

// database/migrations/xxxx_xx_xx_create_continentes_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContinentesTable extends Migration
{
    public function up()
    {
        Schema::create('continentes', function (Blueprint $table) {
            $table->char('id', 2);
            $table->string('nombre', 20)->nullable();
            $table->integer('geonameid')->nullable();
            $table->string('url', 60);
            $table->decimal('centro_x', 10, 5);
            $table->decimal('centro_y', 10, 5);
            $table->integer('zoom');
            $table->text('descripcion');

            $table->primary('id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('continentes');
    }
}
