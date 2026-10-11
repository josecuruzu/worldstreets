<?php

namespace App\Support;

use App\Models\Ciudad;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Geocodificación de direcciones contra Nominatim, el buscador de
 * OpenStreetMap.
 *
 * Es la función principal del sitio y la que se había quedado afuera de la
 * migración: mostrar calle + altura en el mapa. Hasta ahora el buscador
 * resolvía el NOMBRE de la calle contra la tabla busquedas —el histórico de lo
 * que la gente buscó— y el mapa seguía encuadrando la ciudad entera.
 *
 * Va del lado del servidor y no en el navegador, que es como lo hacen el sitio
 * viejo en CakePHP y calles-argentinas. Tres razones:
 *
 * 1. La política de Nominatim pide un User-Agent que identifique la
 *    aplicación. User-Agent es una cabecera prohibida para fetch(): el
 *    navegador la descarta en silencio, así que las dos versiones que lo
 *    intentan están pegándole anónimas.
 * 2. Acá se puede cachear. Una dirección no se muda, y el sitio repite las
 *    mismas consultas: geocodificar dos veces la misma sale gratis.
 * 3. El requisito es que la calle se guarde SOLO si la dirección existe. El
 *    Cake dispara el POST que guarda y la consulta a Nominatim en paralelo, así
 *    que guarda igual cuando no encuentra nada: por eso la lista de Calgary
 *    tiene entradas como "st", "ave", "Calgary" y "2". Resolviendo las dos
 *    cosas en la misma petición, el guardado depende del resultado.
 */
class Nominatim
{
    /** Un mes. Las direcciones no se mudan, y lo que se guarda son dos números. */
    private const HORAS_DE_CACHE = 720;

    /**
     * Las que no se encontraron también se cachean, más corto: un error de
     * tipeo se reintenta tal cual varias veces seguidas, y OSM puede sumar la
     * calle mañana.
     */
    private const HORAS_DE_CACHE_VACIA = 12;

    /**
     * El techo que pide la política de uso de la instancia pública: una
     * petición por segundo para toda la aplicación, no por visitante.
     */
    private const POR_SEGUNDO = 1;

    /**
     * Busca "calle altura" dentro de una ciudad.
     *
     * @return array{lat: float, lon: float, etiqueta: string}|null
     *                                                              null si no se encontró, o si Nominatim no contestó.
     */
    public function direccion(Ciudad $ciudad, string $calle, string $altura): ?array
    {
        $calle = trim($calle);
        $altura = trim($altura);

        if ($calle === '' || $altura === '') {
            return null;
        }

        $clave = 'nominatim.'.$ciudad->id.'.'.md5(Str::lower($calle.'|'.$altura));

        $guardado = Cache::get($clave);

        // El negativo se guarda como false para poder distinguirlo de "no está
        // en caché", que es null.
        if ($guardado !== null) {
            return $guardado === false ? null : $guardado;
        }

        $encontrada = $this->consultar($ciudad, $calle, $altura);

        Cache::put(
            $clave,
            $encontrada ?? false,
            now()->addHours($encontrada ? self::HORAS_DE_CACHE : self::HORAS_DE_CACHE_VACIA)
        );

        return $encontrada;
    }

    /**
     * @return array{lat: float, lon: float, etiqueta: string}|null
     */
    private function consultar(Ciudad $ciudad, string $calle, string $altura): ?array
    {
        /*
         * El techo de una por segundo es de la aplicación entera, así que el
         * throttle de la ruta —que es por IP— no alcanza. Si justo se pisan dos
         * visitantes, el segundo recibe null y ve "probá de nuevo": es
         * preferible a que la instancia pública nos bloquee el servidor y el
         * buscador deje de funcionar para todos.
         */
        $turno = RateLimiter::attempt('nominatim.global', self::POR_SEGUNDO, fn () => true, 1);

        if (! $turno) {
            return null;
        }

        /*
         * Consulta estructurada y no texto libre, igual que el sitio viejo:
         * separar la calle de la ciudad, la provincia y el país es lo que evita
         * que "14 Ave SW" se resuelva en otro continente.
         *
         * viewbox con el recuadro de la ciudad y bounded=1 encima: sin eso,
         * Nominatim devuelve el mejor resultado del mundo y el mapa se va a
         * otro país sin avisar. El Cake mandaba el viewbox pero no bounded, así
         * que el recuadro era una preferencia y no un límite.
         */
        $parametros = [
            // La altura adelante: es el formato que documenta Nominatim para
            // `street`, y además el orden en que se escribe una dirección en
            // inglés. El Cake la mandaba atrás.
            'street' => $altura.' '.$calle,
            'city' => $ciudad->display_name,
            'country' => $ciudad->pais?->nombre,
            'countrycodes' => Str::lower((string) $ciudad->pais_id),
            'format' => 'jsonv2',
            'addressdetails' => 1,
            'limit' => 1,
        ];

        if ($provincia = $ciudad->provincia?->nombre) {
            $parametros['state'] = $provincia;
        }

        if ($recuadro = $this->recuadro($ciudad)) {
            $parametros['viewbox'] = $recuadro;
            $parametros['bounded'] = 1;
        }

        try {
            $respuesta = Http::withHeaders(['User-Agent' => (string) config('services.nominatim.agente')])
                ->accept('application/json')
                ->timeout((int) config('services.nominatim.timeout'))
                ->get(config('services.nominatim.url').'/search', $parametros);
        } catch (\Throwable $e) {
            // Que Nominatim no conteste no es un error de la aplicación: el
            // visitante ve "probá de nuevo" y el resto del sitio sigue.
            Log::warning('Nominatim no respondió', ['ciudad' => $ciudad->id, 'error' => $e->getMessage()]);

            return null;
        }

        if (! $respuesta->successful()) {
            Log::warning('Nominatim respondió con error', ['ciudad' => $ciudad->id, 'estado' => $respuesta->status()]);

            return null;
        }

        $primera = $respuesta->json('0');

        if (! is_array($primera) || ! isset($primera['lat'], $primera['lon'])) {
            return null;
        }

        return [
            'lat' => (float) $primera['lat'],
            'lon' => (float) $primera['lon'],
            // display_name es la dirección como la escribe OSM. Se muestra esa
            // y no la que se tipeó: es la que confirma qué se encontró.
            'etiqueta' => (string) ($primera['display_name'] ?? $calle.' '.$altura),
        ];
    }

    /**
     * El recuadro de la ciudad en el orden que espera Nominatim:
     * lon_oeste, lat_sur, lon_este, lat_norte.
     *
     * En esta base x es la latitud y y la longitud —viene así del import de
     * GeoNames—, de ahí el cruce.
     */
    private function recuadro(Ciudad $ciudad): ?string
    {
        $limites = [
            $ciudad->limite_sw_y, $ciudad->limite_sw_x,
            $ciudad->limite_ne_y, $ciudad->limite_ne_x,
        ];

        foreach ($limites as $limite) {
            if ($limite === null) {
                return null;
            }
        }

        return implode(',', array_map(fn ($limite) => (float) $limite, $limites));
    }
}
