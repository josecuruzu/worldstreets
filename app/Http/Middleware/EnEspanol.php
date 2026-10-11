<?php

namespace App\Http\Middleware;

use App\Support\Idioma;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pone la petición en español.
 *
 * Es todo lo que hace: el idioma sale del prefijo de la URL y de ningún otro
 * lado. Ni cabecera `Accept-Language`, ni cookie, ni IP.
 *
 * La razón es la caché. Estas páginas se sirven desde el borde sin cookie y
 * sin `Vary`; negociar el idioma por cabecera obligaría a un `Vary:
 * Accept-Language` y partiría la caché por navegador, o —peor— serviría la
 * copia guardada de un idioma a quien pidió el otro. Con el prefijo, cada
 * idioma es una URL distinta y cada una se cachea sola.
 *
 * Lo segundo: un rastreador que recibe contenido distinto según su cabecera
 * ve una página que el visitante no ve. Con el prefijo, lo que Google indexa
 * en `/es/cuba` es exactamente lo que abre cualquiera que entre a `/es/cuba`.
 */
class EnEspanol
{
    public function handle(Request $peticion, Closure $siguiente): Response
    {
        App::setLocale(Idioma::ESPANOL);

        return $siguiente($peticion);
    }
}
