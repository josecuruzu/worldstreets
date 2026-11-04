<?php

namespace App\Support;

/**
 * Los rangos de Cloudflare, para confiar en X-Forwarded-For solo cuando la
 * petición viene de ellos.
 *
 * Sin esto, detrás de Cloudflare `$request->ip()` devuelve la IP del proxy: el
 * candado de direcciones repetidas del buscador, el límite del login y el de
 * Nominatim pasarían a contar a todos los visitantes como unos pocos. Y
 * confiar en la cabecera de cualquiera sería peor: un bot la fabrica y cambia
 * de IP en cada petición.
 *
 * Copiados de https://www.cloudflare.com/ips-v4 y /ips-v6 el 2026-11-04.
 * Cambian muy rara vez; si Cloudflare publica rangos nuevos hay que
 * actualizarlos acá.
 */
final class CloudflareIps
{
    /** @var list<string> */
    public const RANGOS = [
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ];
}
