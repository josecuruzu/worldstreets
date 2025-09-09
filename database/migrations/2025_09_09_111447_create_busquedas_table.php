<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBusquedasTable extends Migration
{
    public function up()
    {
        Schema::create('busquedas', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->integer('ciudad_id');
            $table->string('nombre_calle', 255);
            $table->string('nombre_calle_compacto', 255);
            $table->integer('cantidad_busquedas');
            $table->timestamps();

            $table->index('ciudad_id', 'ciudad_busqueda');
        });
    }

    public function down()
    {
        Schema::dropIfExists('busquedas');
    }
}
