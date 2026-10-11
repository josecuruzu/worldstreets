<?php

namespace App\Support;

use Illuminate\Support\Facades\App;

/**
 * Qué se publica en español y qué no.
 *
 * El sitio se publica en inglés. El español es la excepción, y la excepción
 * está medida: **el 35,8% de los clics de los últimos 16 meses entran por una
 * URL `/esp/`** — 2.479 de 6.930, contra 3.941 del inglés. Cuba es el segundo
 * país del sitio por clics (832) después de Estados Unidos (1.026), con España
 * cuarto (422) y Venezuela séptimo (284).
 *
 * El HANDOFF había decidido no volver a los cinco idiomas del CakePHP, y esa
 * decisión sigue en pie: eran 18,25 M de URLs. Pero se tomó mirando 18
 * consultas en español desde EE.UU., que es la rodaja estadounidense del
 * top-1000, y el español entero es un tercio del sitio. Lo que se hace acá es
 * separar una cosa de la otra: un idioma más, no cinco, y solo donde hay
 * activo que publicar.
 *
 * ── Qué entra ─────────────────────────────────────────────────────────────
 *
 * Los 21 países hispanohablantes, y solo sus lugares con contenido:
 *
 *     1.990 ciudades con calles
 *       367 provincias
 *        21 países
 *     ─────────────────────────
 *     ~2.381 URLs, un +10,5% sobre las 22.581 del sitio
 *
 * Fuera de esa lista **no hay versión en español**: `/es/japan` responde 404,
 * no una traducción vacía. Es lo que impide que esto vuelva a ser un espejo
 * completo del corpus, que es la forma en que el sitio se ganó la penalización.
 *
 * Los Ángeles (10.721 calles, el tercer corpus urbano del sitio) y Miami (450)
 * son el caso que más pide una versión en español desde EE.UU. y **no están
 * acá**: meterlos implicaría abrir `US`, o sea 440 ciudades. Van aparte cuando
 * se decida, con una lista explícita de ciudades.
 *
 * ── Por qué prefijo de ruta y no cabecera ────────────────────────────────
 *
 * `/es/cuba/...` es otra URL, así que la respuesta sigue saliendo sin cookie y
 * sin `Vary: Accept-Language`, que es lo que permite que LiteSpeed la sirva
 * desde su caché. Negociar el idioma por cabecera o por IP rompería eso, y
 * además le serviría al rastreador una página distinta según por dónde entre.
 */
final class Idioma
{
    /** El idioma del sitio. */
    public const INGLES = 'en';

    /** El idioma de la excepción. */
    public const ESPANOL = 'es';

    /** El prefijo de ruta, que es igual al código: `/es/cuba/havana/...`. */
    public const PREFIJO = 'es';

    /**
     * Los países que tienen versión en español, por su id de GeoNames.
     *
     * Están los 20 de América y España, más Guinea Ecuatorial (GQ), que es
     * hispanohablante oficial aunque no tenga una sola calle cargada: dejarla
     * afuera de la lista sería una decisión sobre el idioma tomada por el
     * tamaño del dataset, y la regla de contenido ya la resuelve sola —sin
     * ciudades con calles no se publica ninguna URL suya.
     */
    public const PAISES = [
        'AR', // Argentina
        'BO', // Bolivia
        'CL', // Chile
        'CO', // Colombia
        'CR', // Costa Rica
        'CU', // Cuba
        'DO', // República Dominicana
        'EC', // Ecuador
        'ES', // España
        'GQ', // Guinea Ecuatorial
        'GT', // Guatemala
        'HN', // Honduras
        'MX', // México
        'NI', // Nicaragua
        'PA', // Panamá
        'PE', // Perú
        'PR', // Puerto Rico
        'PY', // Paraguay
        'SV', // El Salvador
        'UY', // Uruguay
        'VE', // Venezuela
    ];

    /** ¿Este país se publica también en español? */
    public static function cubre(?string $paisId): bool
    {
        return $paisId !== null && in_array($paisId, self::PAISES, true);
    }

    /** ¿La petición en curso es la versión en español? */
    public static function esEspanol(): bool
    {
        return App::getLocale() === self::ESPANOL;
    }

    /** El idioma de la petición en curso, siempre uno de los dos. */
    public static function actual(): string
    {
        return self::esEspanol() ? self::ESPANOL : self::INGLES;
    }

    /**
     * El nombre de la ruta de lugar en el idioma en curso.
     *
     * Las rutas en español se llaman igual con el prefijo `es.`, así que una
     * vista sirve para las dos versiones sin un solo `@if`. Es lo que evita
     * que una página en español enlace a las fichas en inglés, que es la forma
     * más fácil de que Google decida que la versión en español no existe.
     */
    public static function ruta(string $nombre): string
    {
        return self::esEspanol() ? self::PREFIJO.'.'.$nombre : $nombre;
    }

    /**
     * Un número escrito como lo escribe el idioma en curso.
     *
     * `number_format()` a secas escribe 2,065 y en español eso se lee como
     * dos con sesenta y cinco. El separador de miles del español es el punto
     * y el decimal es la coma, al revés que en inglés. No es un detalle
     * tipográfico: las cifras de cobertura son el argumento de la página
     * —"2.065 calles registradas"— y escritas al revés dicen otra cosa.
     */
    public static function numero(int|float $n): string
    {
        return self::esEspanol()
            ? number_format($n, 0, ',', '.')
            : number_format($n);
    }

    /** La URL de un lugar, en el idioma en curso. */
    public static function url(string $nombre, mixed $parametros = []): string
    {
        return route(self::ruta($nombre), $parametros);
    }

    /**
     * Los enlaces `hreflang` de una página de lugar.
     *
     * Devuelve las dos versiones cuando el país está cubierto y solo la
     * inglesa cuando no. Van siempre las dos en las dos páginas —recíprocos—
     * porque un hreflang que no se devuelve es un hreflang que Google ignora.
     *
     * `x-default` apunta al inglés: es la versión que sirve a cualquiera que
     * no sea hispanohablante, que es la mayoría del tráfico.
     *
     * @param  string  $ruta  el nombre de la ruta inglesa: pais, provincia o ciudad
     * @param  array<int, string>  $parametros
     * @return array<string, string>
     */
    public static function alternos(string $ruta, array $parametros, ?string $paisId): array
    {
        $ingles = route($ruta, $parametros);

        if (! self::cubre($paisId)) {
            return [];
        }

        return [
            self::INGLES => $ingles,
            self::ESPANOL => route(self::PREFIJO.'.'.$ruta, $parametros),
            'x-default' => $ingles,
        ];
    }
}
