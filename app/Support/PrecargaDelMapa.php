<?php

namespace App\Support;

/**
 * Los enlaces de precarga del mapa.
 *
 * Leaflet se carga con `import()` dinámico, o sea que el navegador no se
 * entera de que existe hasta que `app.js` corre y lo pide. Para la mayoría de
 * las páginas eso es justo lo que se quiere: no bajan el mapa en absoluto.
 *
 * Para la ficha de ciudad no. Ahí el mapa **es el LCP** —es el elemento más
 * grande de la pantalla— y la cadena hasta el primer tile es:
 *
 *     HTML → app.js → import() de Leaflet → se crea el mapa → se pide el tile
 *
 * Lighthouse lo midió sobre la ficha de Marianao: de los 5,1 s de LCP, **3,7 s
 * son "load delay"**, que es exactamente ese encadenamiento. El
 * `modulepreload` corta un eslabón: el navegador empieza a bajar el chunk de
 * Leaflet mientras todavía parsea el HTML, en paralelo con `app.js` en vez de
 * después.
 *
 * El nombre del archivo lleva hash, así que sale del manifiesto de Vite. Si
 * el manifiesto no está —en desarrollo, con el servidor de Vite— no se emite
 * nada: ahí no hay chunks que precargar.
 */
final class PrecargaDelMapa
{
    /**
     * La URL del tile que va en el centro del mapa.
     *
     * Lighthouse lo pide explícitamente —"Preload Largest Contentful Paint
     * image"— y en esta página el LCP es literalmente un PNG de
     * `tile.openstreetmap.org`. Su problema no es el tamaño sino **cuándo se
     * descubre**: el navegador no sabe que existe hasta que Leaflet se cargó,
     * creó el mapa y calculó qué tiles le tocan. Medido: 2,5 s de "load
     * delay" sobre 3,7 s de LCP.
     *
     * Este cálculo es el mismo que hace Leaflet, hecho en PHP: la proyección
     * Web Mercator de la que salen las coordenadas de tile de cualquier mapa
     * "slippy". Con la URL en un `<link rel="preload" as="image">`, el tile se
     * pide mientras se parsea el HTML, y cuando Leaflet lo pida ya está.
     *
     * Solo el del centro. Precargar los seis que cubren la pantalla sería
     * pedirle al navegador que compita consigo mismo por el ancho de banda en
     * el momento en que está bajando el JavaScript.
     */
    public static function tileCentral(float $lat, float $lon, int $zoom): ?string
    {
        // Web Mercator no existe más allá de estas latitudes, y el dataset
        // tiene ciudades en la Antártida.
        if ($lat >= 85.05112878 || $lat <= -85.05112878) {
            return null;
        }

        $zoom = max(0, min(19, $zoom));
        $n = 2 ** $zoom;

        $x = (int) floor(($lon + 180) / 360 * $n);
        $radianes = deg2rad($lat);
        $y = (int) floor((1 - log(tan($radianes) + 1 / cos($radianes)) / M_PI) / 2 * $n);

        if ($x < 0 || $y < 0 || $x >= $n || $y >= $n) {
            return null;
        }

        return "https://tile.openstreetmap.org/{$zoom}/{$x}/{$y}.png";
    }

    /** El manifiesto se lee una vez por petición. */
    private static ?array $enlaces = null;

    /**
     * @return array{modulos: list<string>, estilos: list<string>}
     */
    public static function enlaces(): array
    {
        if (self::$enlaces !== null) {
            return self::$enlaces;
        }

        $manifiesto = public_path('build/manifest.json');

        if (! is_file($manifiesto)) {
            return self::$enlaces = ['modulos' => [], 'estilos' => []];
        }

        $datos = json_decode((string) file_get_contents($manifiesto), true);

        if (! is_array($datos)) {
            return self::$enlaces = ['modulos' => [], 'estilos' => []];
        }

        $modulos = [];
        $estilos = [];

        foreach ($datos as $clave => $entrada) {
            $archivo = $entrada['file'] ?? null;

            if (! is_string($archivo)) {
                continue;
            }

            // El chunk del propio Leaflet: `_leaflet-src-7ah4FPQk.js`.
            if (str_contains($clave, 'leaflet-src') && str_ends_with($archivo, '.js')) {
                $modulos[] = asset('build/'.$archivo);
            }

            if ($clave === 'node_modules/leaflet/dist/leaflet.css') {
                $estilos[] = asset('build/'.$archivo);
            }
        }

        return self::$enlaces = ['modulos' => $modulos, 'estilos' => $estilos];
    }
}
