<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * El dataset nombra a las ciudades con su exónimo inglés — Tokyo, Moscow,
 * Beijing — así que hasta acá alguien en Tokio que escribiera 東京 no encontraba
 * nada, y un español que escribiera "Tokio" tampoco.
 *
 * Los nombres locales están: la columna `alternatenames` los trae en 2.720.301
 * ciudades, separados por comas y en todos los alfabetos ("Edo,TYO,Tokio,
 * Tòquio,東京…"). Lo único que faltaba era indexarlos.
 *
 * El índice pasa a cubrir las dos columnas en vez de solo `nombre_min`, porque
 * un MATCH solo puede consultar las columnas de un mismo índice fulltext: con
 * dos índices separados habría que hacer dos MATCH unidos por OR, que no
 * aprovecha ninguno de los dos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->soportaFulltext()) {
            return;
        }

        Schema::table('ciudades', function (Blueprint $table) {
            $table->dropFullText('ciudades_nombre_min_fulltext');
            $table->fullText(['nombre_min', 'alternatenames'], 'ciudades_nombres_fulltext');
        });
    }

    public function down(): void
    {
        if (! $this->soportaFulltext()) {
            return;
        }

        Schema::table('ciudades', function (Blueprint $table) {
            $table->dropFullText('ciudades_nombres_fulltext');
            $table->fullText('nombre_min', 'ciudades_nombre_min_fulltext');
        });
    }

    private function soportaFulltext(): bool
    {
        return Schema::getConnection()->getDriverName() === 'mysql';
    }
};
