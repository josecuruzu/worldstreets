<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * El host canónico del sitio.
 *
 * Existe por la restricción del despliegue: el Laravel reemplaza al CakePHP en
 * otra carpeta y sobre OTRO dominio para probar, y recién después pasa al
 * dominio original. Mientras tanto hay dos servidores publicando el mismo
 * corpus, y eso en un sitio que viene de una caída algorítmica es lo peor que
 * puede pasar: una segunda copia indexable de las 22.581 URLs.
 *
 * Lo que lo hace caro es que no produce ningún síntoma visible. El dominio de
 * pruebas responde bien, se ve bien, y con `APP_URL` apuntando ahí los
 * canonical y los <loc> de los sitemaps salen apuntando ahí también, o sea que
 * el sitio de pruebas se declara a sí mismo como el original.
 *
 * La defensa son dos cosas, y las dos hacen falta:
 *
 * 1. **El canonical y los <loc> se escriben siempre contra este host**, no
 *    contra el de la petición ni contra `APP_URL`. Los enlaces normales de la
 *    navegación NO: siguen siendo relativos al host que sirvió la página, para
 *    que el dominio de pruebas se pueda recorrer.
 * 2. **`X-Robots-Tag: noindex` cuando el host de la petición no coincide**
 *    (lo pone `HostCanonico`). El canonical es una sugerencia; el noindex no.
 *
 * Lo que NO se hace es bloquear el dominio de pruebas por robots.txt: lo que
 * se bloquea no se rastrea, y lo que no se rastrea nunca llega a leer el
 * noindex. Quedaría indexado con el título sacado de los enlaces y sin forma
 * de sacarlo.
 *
 * Sin `CANONICAL_HOST` configurado todo esto queda inerte y las URLs salen
 * como salían: es el caso del desarrollo local.
 */
class Canonico
{
    /**
     * El host configurado, en minúsculas y sin esquema ni barra final.
     * `null` si no hay ninguno, que es lo que apaga el mecanismo entero.
     */
    public static function host(): ?string
    {
        $valor = trim((string) config('app.canonical_host'));

        if ($valor === '') {
            return null;
        }

        // Se acepta tanto `worldstreets.global` como `https://worldstreets.global/`.
        $host = parse_url($valor, PHP_URL_HOST) ?: preg_replace('#^.*?//#', '', $valor);
        $puerto = parse_url($valor, PHP_URL_PORT);

        $host = strtolower(trim(rtrim((string) $host, '/')));

        return $puerto !== null ? "{$host}:{$puerto}" : $host;
    }

    /**
     * La raíz canónica, con esquema: `https://worldstreets.global`.
     *
     * El esquema es https salvo que el valor configurado diga otra cosa, que en
     * la práctica solo pasa apuntando a un host local con puerto.
     */
    public static function raiz(): ?string
    {
        $host = self::host();

        if ($host === null) {
            return null;
        }

        $esquema = parse_url((string) config('app.canonical_host'), PHP_URL_SCHEME) ?: 'https';

        return "{$esquema}://{$host}";
    }

    /**
     * ¿La petición llegó por el host canónico?
     *
     * Sin host configurado la respuesta es que sí: no hay dominio de pruebas
     * declarado, así que no hay copia que desindexar.
     */
    public static function esElHost(Request $peticion): bool
    {
        $host = self::host();

        if ($host === null) {
            return true;
        }

        return strtolower($peticion->getHttpHost()) === $host;
    }

    /**
     * Reescribe una URL absoluta contra el host canónico, dejando intactos el
     * camino y la query. Es lo que usan los <loc> de los sitemaps, que se
     * generan con `route()` y por lo tanto salen con el host de `APP_URL`.
     */
    public static function url(string $absoluta): string
    {
        $raiz = self::raiz();

        if ($raiz === null) {
            return $absoluta;
        }

        $partes = parse_url($absoluta);

        if ($partes === false || ! isset($partes['host'])) {
            return $absoluta;
        }

        $camino = $partes['path'] ?? '';
        $query = isset($partes['query']) ? '?'.$partes['query'] : '';

        return $raiz.$camino.$query;
    }

    /**
     * La URL canónica de la petición en curso.
     *
     * De la query string sobrevive solo `page`, y solo si es mayor que 1. El
     * resto —utm_*, gclid, lo que pegue cualquiera— tiene que colapsar en la
     * misma URL o cada campaña se convierte en una página duplicada. La
     * paginación sí, porque la página 3 de una provincia lista ciudades
     * distintas de la 1: canonicalizarla a la 1 le dice a Google que esas
     * ciudades no existen.
     */
    public static function deLaPeticion(Request $peticion): string
    {
        $raiz = self::raiz() ?? rtrim($peticion->getSchemeAndHttpHost(), '/');

        $camino = '/'.ltrim($peticion->getPathInfo(), '/');
        $camino = $camino === '/' ? '/' : rtrim($camino, '/');

        $pagina = (int) $peticion->query('page', 1);

        return $raiz.$camino.($pagina > 1 ? '?page='.$pagina : '');
    }
}
