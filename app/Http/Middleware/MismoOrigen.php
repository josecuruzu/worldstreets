<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * El reemplazo del token CSRF para los POST públicos que escriben.
 *
 * Las páginas que disparan estos POST salen sin sesión —es lo que las hace
 * cacheables en el borde, y la ficha de ciudad es la que recibe el tráfico de
 * Google—, así que no hay token que incrustar. A cambio se exige que la
 * petición venga del propio sitio, comparando `Origin` (o `Referer` como
 * respaldo) contra el host que la sirvió.
 *
 * Es más débil que un token y por eso no se aplica a cualquier cosa: vale para
 * operaciones que no tocan datos de nadie —no hay usuarios— pero que sí
 * escriben una fila, que es lo que las hace abusables. Portado de
 * calles-argentinas-v2, donde protege los mismos dos casos.
 *
 * Se aplica a `POST /api/compartir`, que crea una fila por dirección
 * compartida. `POST /api/direcciones` todavía no lo lleva: es anterior y su
 * escritura está deduplicada por IP, pero es el candidato obvio a sumarlo.
 */
class MismoOrigen
{
    public function handle(Request $peticion, Closure $siguiente): Response
    {
        if ($peticion->isMethodSafe()) {
            return $siguiente($peticion);
        }

        $origen = $peticion->headers->get('Origin') ?: $peticion->headers->get('Referer');

        if (! $origen) {
            abort(403, 'Origen no verificable.');
        }

        if (! $this->coincide($origen, $peticion->getHost())) {
            abort(403, 'Origen no permitido.');
        }

        return $siguiente($peticion);
    }

    private function coincide(string $origen, string $host): bool
    {
        $hostDelOrigen = parse_url($origen, PHP_URL_HOST);

        if (! is_string($hostDelOrigen) || $hostDelOrigen === '') {
            return false;
        }

        /*
         * Se aceptan el host que sirvió la página y el de APP_URL. El primero
         * es lo que hace que esto siga funcionando en el dominio de pruebas
         * del corte, donde APP_URL y el host real no coinciden.
         */
        $permitidos = array_filter([
            $host,
            parse_url((string) config('app.url'), PHP_URL_HOST),
            \App\Support\Canonico::host(),
        ]);

        return in_array(
            strtolower($hostDelOrigen),
            array_map('strtolower', $permitidos),
            true
        );
    }
}
