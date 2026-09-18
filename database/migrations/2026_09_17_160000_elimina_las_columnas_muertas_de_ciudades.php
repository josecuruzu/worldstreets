<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Columnas de `ciudades` que llegaron con el volcado de GeoNames y no las lee
 * nadie.
 *
 * Verificado nombre por nombre sobre el Laravel y sobre el CakePHP: fuera de
 * `ImportarArgentina`, que les escribe null, solo aparecen en el $fillable y
 * los $casts del modelo y en los fixtures de los tests, que las rellenan
 * porque son NOT NULL sin default. Ninguna vista, ningún controlador y ninguna
 * consulta las mira.
 *
 * `ciudades` pesa 1.107 MB de datos más 773 MB de índices sobre un hosting
 * compartido cuyo buffer pool es de 1.008 MB y no se controla: todo lo que se
 * saca de la tabla es espacio que queda para lo que sí se consulta.
 *
 * ── Las que NO se tocan y por qué ─────────────────────────────────────────
 *
 * `limite_sw_x`, `limite_sw_y`, `limite_ne_x`, `limite_ne_y` quedan afuera de
 * esta migración aunque el Laravel tampoco las use: el CakePHP SÍ las lee —son
 * 16 usos en src/, y de ahí salen los límites del mapa de cada ficha—. Mientras
 * el sitio viejo siga en pie contra esta misma base, borrarlas lo deja sin
 * mapa. Son 90 MB y se pueden sacar en cuanto el Cake se apague; hay una
 * migración pendiente para eso en el HANDOFF.
 */
return new class extends Migration
{
    /**
     * Las columnas y lo que ocupan, medido sobre las 4.697.141 filas.
     *
     * @var list<string>
     */
    private const COLUMNAS = [
        // 81 MB entre las seis: códigos administrativos de GeoNames que el
        // producto no usa — la jerarquía sale de pais_id y provincia_id.
        'cc2',
        'admin1',
        'admin3',
        'admin4',
        'timezone',
        'fclass',

        // 49 MB. Altitud y fecha de modificación del registro en GeoNames.
        'elevation',
        'gtopo30',
        'moddate',
    ];

    public function up(): void
    {
        $existentes = $this->existentes();

        if ($existentes === []) {
            return;
        }

        Schema::table('ciudades', function (Blueprint $tabla) use ($existentes) {
            $tabla->dropColumn($existentes);
        });
    }

    /**
     * No se reponen los datos: se reponen las columnas, vacías.
     *
     * Lo que había eran valores de GeoNames que nadie leía; si alguna vez hacen
     * falta, salen de reimportar el dataset, no de un down().
     */
    public function down(): void
    {
        Schema::table('ciudades', function (Blueprint $tabla) {
            $tabla->string('cc2', 200)->nullable();
            $tabla->string('admin1', 20)->nullable();
            $tabla->string('admin3', 20)->nullable();
            $tabla->string('admin4', 20)->nullable();
            $tabla->string('timezone', 40)->nullable();
            $tabla->string('fclass', 1)->nullable();
            $tabla->integer('elevation')->nullable();
            $tabla->integer('gtopo30')->nullable();
            $tabla->date('moddate')->nullable();
        });
    }

    /**
     * Solo las que están. La migración tiene que poder correrse sobre una base
     * que ya pasó por ella, o sobre una recién creada donde el esquema salió de
     * las migraciones y no del volcado.
     *
     * @return list<string>
     */
    private function existentes(): array
    {
        return array_values(array_filter(
            self::COLUMNAS,
            fn (string $columna) => Schema::hasColumn('ciudades', $columna)
        ));
    }
};
