<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los enlaces cortos que produce el botón de compartir.
 *
 * Existen porque una dirección encontrada NO tiene URL propia en este sitio, y
 * no la va a tener: la altura es infinita y una página por altura es lo que
 * dejó al sitio con 18,25 M de URLs. Pero compartir "Calle 23 456, Marianao"
 * por WhatsApp es la cosa más natural que puede querer hacer alguien que acaba
 * de encontrar una dirección, y sin una URL no hay nada que pegar.
 *
 * El enlace corto resuelve las dos cosas a la vez: le da una URL a algo que no
 * es una página, y como es opaca —`/r/aB3xK9pQ`— no agrega una sola URL
 * indexable al corpus. La página que sirve sale con `noindex` y su canonical
 * apunta a la ficha de la ciudad.
 *
 * Caducan a los 90 días. Un enlace que alguien mandó por mensaje se usa en
 * horas, no en años, y una tabla que solo crece es una tabla que hay que
 * limpiar alguna vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enlaces_compartidos', function (Blueprint $table) {
            $table->id();
            $table->char('codigo', 10)->unique();

            // La ciudad por id y no por slug: el slug de una ciudad puede
            // cambiar entre importaciones y hay 433.217 grupos repetidos, así
            // que un enlace guardado por slug puede terminar en otra ciudad.
            $table->unsignedBigInteger('ciudad_id');
            $table->string('calle', 255);
            $table->string('altura', 16);

            // El idioma en el que se compartió. Quien recibe un enlace de un
            // amigo cubano tiene que abrir la página en español.
            $table->string('idioma', 2)->default('en');

            $table->unsignedInteger('visitas')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();

            // Para reusar el enlace de una dirección que ya se compartió en vez
            // de crear una fila nueva por cada clic en el botón.
            $table->index(['ciudad_id', 'calle', 'altura'], 'enlace_por_direccion');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enlaces_compartidos');
    }
};
