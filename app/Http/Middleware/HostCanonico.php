<?php

namespace App\Http\Middleware;

use App\Support\Canonico;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Saca del índice cualquier host que no sea el canónico.
 *
 * El caso concreto: durante el corte del CakePHP al Laravel, el sitio nuevo
 * vive un tiempo en un dominio de pruebas con el corpus entero adentro. Si
 * Google lo encuentra —y lo encuentra: basta un enlace, una barra de
 * herramientas o el propio sitemap— quedan dos copias completas compitiendo
 * entre ellas, en un dominio que ya viene de una penalización algorítmica.
 *
 * `X-Robots-Tag` y no una <meta>, por dos razones: vale para todo lo que sale
 * del servidor y no solo para el HTML —los sitemaps y el robots.txt también
 * son URLs que se indexan— y no depende de que la plantilla se haya acordado
 * de emitir nada.
 *
 * Va en el stack global, no en el grupo 'publico': si aparece una ruta nueva,
 * la protección tiene que cubrirla sin que nadie se acuerde de agregarla.
 *
 * Sin `CANONICAL_HOST` configurado esto no hace nada, que es lo que se quiere
 * en local: `Canonico::esElHost()` devuelve true cuando no hay host declarado.
 */
class HostCanonico
{
    public function handle(Request $peticion, Closure $siguiente): Response
    {
        /** @var Response $respuesta */
        $respuesta = $siguiente($peticion);

        if (Canonico::esElHost($peticion)) {
            return $respuesta;
        }

        /*
         * `follow` a propósito. La copia no tiene que indexarse, pero sí
         * dejarse recorrer: es la forma de que el rastreador llegue a los
         * canonical de adentro, que apuntan todos al host real. Un `nofollow`
         * la convertiría en un callejón sin salida.
         */
        $respuesta->headers->set('X-Robots-Tag', 'noindex, follow');

        return $respuesta;
    }
}
