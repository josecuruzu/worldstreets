<?php

// database/migrations/xxxx_xx_xx_create_paises_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaisesTable extends Migration
{
    public function up()
    {
        Schema::create('paises', function (Blueprint $table) {
            $table->char('id', 2);
            $table->char('iso_alpha3', 3)->nullable();
            $table->integer('iso_numeric')->nullable();
            $table->string('fips_code', 3)->nullable();
            $table->string('nombre', 200)->nullable();
            $table->string('url', 255);
            $table->string('capital', 200)->nullable();
            $table->double('areainsqkm')->nullable();
            $table->integer('population')->nullable();
            $table->char('continente_id', 2)->nullable();
            $table->char('tld', 3)->nullable();
            $table->char('currency', 3)->nullable();
            $table->char('currencyName', 20)->nullable();
            $table->char('Phone', 10)->nullable();
            $table->string('postalCodeFormat', 100)->nullable();
            $table->string('postalCodeRegex', 255)->nullable();
            $table->integer('geonameId')->nullable();
            $table->string('languages', 200)->nullable();
            $table->char('neighbours', 100)->nullable();
            $table->char('equivalentFipsCode', 10)->nullable();
            $table->integer('visitas')->default(0);
            $table->decimal('centro_x', 10, 5);
            $table->decimal('centro_y', 10, 5);
            $table->integer('zoom');

            $table->primary('id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('paises');
    }
}