<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * busquedas.id se creó como `integer primary key` sin auto_increment
 * (2025_09_09_111447), porque los ids venían del import de calles-argentinas.
 * Con eso la tabla es de solo lectura desde la app: no se puede registrar una
 * búsqueda nueva sin inventar el id a mano.
 *
 * No se edita aquella migración porque ya está corrida y la tabla tiene
 * 405.972 filas que no se pueden perder. Tampoco se puede recrear la tabla en
 * MySQL, así que se hace un ALTER in place; MySQL deja el contador en MAX(id)+1
 * solo. En SQLite (los tests) no existe el ALTER equivalente, así que ahí sí se
 * reconstruye la tabla, que en ese contexto está vacía o es descartable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if ($this->esSqlite()) {
            $this->reconstruirSqlite(autoincremental: true);

            return;
        }

        DB::statement('ALTER TABLE `busquedas` MODIFY `id` INT NOT NULL AUTO_INCREMENT');
    }

    public function down(): void
    {
        if ($this->esSqlite()) {
            $this->reconstruirSqlite(autoincremental: false);

            return;
        }

        DB::statement('ALTER TABLE `busquedas` MODIFY `id` INT NOT NULL');
    }

    private function esSqlite(): bool
    {
        return Schema::getConnection()->getDriverName() === 'sqlite';
    }

    /**
     * SQLite no permite cambiar la definición de una columna: hay que crear la
     * tabla nueva, copiar y renombrar.
     */
    private function reconstruirSqlite(bool $autoincremental): void
    {
        Schema::create('busquedas_tmp', function (Blueprint $table) use ($autoincremental) {
            if ($autoincremental) {
                $table->increments('id');
            } else {
                $table->integer('id')->primary();
            }

            $table->integer('ciudad_id');
            $table->string('nombre_calle', 255);
            $table->string('nombre_calle_compacto', 255);
            $table->integer('cantidad_busquedas');
            $table->timestamps();
        });

        DB::statement('INSERT INTO busquedas_tmp (id, ciudad_id, nombre_calle, nombre_calle_compacto, cantidad_busquedas, created_at, updated_at)
                       SELECT id, ciudad_id, nombre_calle, nombre_calle_compacto, cantidad_busquedas, created_at, updated_at FROM busquedas');

        Schema::drop('busquedas');
        Schema::rename('busquedas_tmp', 'busquedas');

        // El índice se recrea acá porque el rename no lo trae desde la tabla vieja.
        Schema::table('busquedas', function (Blueprint $table) {
            $table->index('ciudad_id', 'ciudad_busqueda');
        });
    }
};
