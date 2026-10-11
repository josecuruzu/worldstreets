<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Declara cacheables las respuestas de las rutas públicas.
 *
 * Laravel manda `Cache-Control: no-cache, private` por defecto, que es lo
 * correcto para una página con sesión y lo peor posible para una que es igual
 * para todo el mundo: con ese header, ni LiteSpeed ni un CDN guardan nada y
 * cada visita levanta PHP y golpea MySQL.
 *
 * Es la mitad del problema que el handoff marca del sitio viejo: responde
 * `no-store, no-cache, must-revalidate` y setea una cookie de sesión en cada
 * visita, así que su caché no sirve para nada y tarda 2.400 ms. La otra mitad
 * —la cookie— la resuelve el grupo 'publico', que no incluye StartSession.
 *
 * `s-maxage` alto y `max-age` bajo a propósito: el que tiene que guardar la
 * copia es el servidor, no el navegador de cada visitante. Si el contenido
 * cambia, una purga del borde alcanza; una copia en el navegador de alguien no
 * se purga.
 */
class RespuestaCacheable
{
    /** Una hora en el borde. */
    private const SEGUNDOS_EN_EL_BORDE = 3600;

    /** Cinco minutos en el navegador. */
    private const SEGUNDOS_EN_EL_CLIENTE = 300;

    public function handle(Request $peticion, Closure $siguiente): Response
    {
        /** @var Response $respuesta */
        $respuesta = $siguiente($peticion);

        /*
         * Solo lecturas. En el grupo público hay un POST —el que geocodifica
         * una dirección y guarda la calle—, y declararlo `public, s-maxage`
         * haría que el borde le sirviera a un visitante la dirección que buscó
         * otro.
         */
        if (! $peticion->isMethodCacheable()) {
            return $respuesta;
        }

        /*
         * Solo lo que salió bien o es una redirección permanente. Un error
         * cacheado una hora es un error que no se puede arreglar en una hora, y
         * los 410 conviene que Google los vuelva a pedir: son el mecanismo con
         * el que se está limpiando el índice.
         */
        if (! in_array($respuesta->getStatusCode(), [200, 301], true)) {
            return $respuesta;
        }

        /*
         * Una respuesta con Set-Cookie no se puede compartir entre visitantes:
         * el borde le serviría la cookie de uno a otro. Si alguna ruta pública
         * llegara a abrir sesión, es preferible no cachearla que servir una
         * sesión ajena.
         */
        if ($respuesta->headers->getCookies() !== []) {
            return $respuesta;
        }

        $respuesta->headers->set(
            'Cache-Control',
            sprintf('public, max-age=%d, s-maxage=%d', self::SEGUNDOS_EN_EL_CLIENTE, self::SEGUNDOS_EN_EL_BORDE)
        );

        return $respuesta;
    }
}
