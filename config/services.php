<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    | Nominatim, el geocodificador de OpenStreetMap: es lo que convierte
    | "Macleod Tr SE 1234, Calgary" en un par de coordenadas, y por lo tanto la
    | función principal del sitio.
    |
    | La URL es configurable porque la instancia pública tiene una política de
    | uso —1 petición por segundo, sin geocodificación masiva, y un User-Agent
    | que identifique la aplicación—. Si el tráfico crece, esto se apunta a una
    | instancia propia sin tocar código.
    */
    'nominatim' => [
        'url' => rtrim((string) env('NOMINATIM_URL', 'https://nominatim.openstreetmap.org'), '/'),
        'agente' => env('NOMINATIM_AGENTE', 'worldstreets.global (+https://worldstreets.global)'),
        'timeout' => (int) env('NOMINATIM_TIMEOUT', 6),
    ],

    /*
    |--------------------------------------------------------------------------
    | AdSense
    |--------------------------------------------------------------------------
    |
    | El sitio se monetiza con AdSense y es su única fuente de ingresos: $213,16
    | en doce meses, el 83,1% del mercado anglo. Acá va solo el ID de editor; los
    | data-ad-slot de cada unidad van en las vistas, que es donde se decide la
    | ubicación.
    |
    | Vacío apaga los anuncios enteros —el partial `ad-slot` y el cargador del
    | layout no emiten nada—, que es lo que se quiere en local y en los tests.
    |
    */
    /*
     * La propiedad de Google Analytics 4.
     *
     * Es la misma que medía el CakePHP. La migración no portó la etiqueta y
     * el sitio nuevo estuvo en producción midiendo cero sin que nada fallara;
     * queda acá, en la configuración, para que se vea que existe.
     *
     * Vacío apaga la medición entera, que es lo que se quiere en local y en
     * los tests. Ver `partials/medicion`.
     */
    'analytics' => [
        'id' => env('ANALYTICS_ID'),
    ],

    'adsense' => [
        'client' => env('ADSENSE_CLIENT'),

        /*
         * Los data-ad-slot de cada unidad, que salen del panel de AdSense.
         * Están acá y no en las vistas porque son de la cuenta, no del diseño:
         * el día que cambie la cuenta cambia el .env y nada más.
         *
         * Un slot vacío apaga ese bloque y deja los otros andando, que es lo
         * que permite montarlos de a uno y medir cada posición por separado.
         */
        'slots' => [
            'ciudad_superior' => env('ADSENSE_SLOT_CIUDAD_SUPERIOR'),
            'ciudad_medio' => env('ADSENSE_SLOT_CIUDAD_MEDIO'),

            /*
             * La unidad que va DENTRO del índice A–Z, repetida cada
             * `indice_cada` grupos de letra.
             *
             * Es la corrección del error más caro que tenía el reparto
             * anterior: la ficha de una ciudad grande mide 58 pantallas de
             * teléfono, y las tres unidades estaban todas en la primera y la
             * última. Las 56 del medio —que es donde el visitante pasa el
             * tiempo— no tenían ninguna.
             *
             * Reusar el mismo data-ad-slot varias veces en una página es algo
             * que AdSense admite; todas reportan juntas bajo la misma unidad.
             */
            'ciudad_indice' => env('ADSENSE_SLOT_CIUDAD_INDICE'),

            'ciudad_final' => env('ADSENSE_SLOT_CIUDAD_FINAL'),

            /*
             * La ficha de país no tenía ninguna unidad y es la página más
             * eficiente del sitio: 246 URLs y 2.951 clics, o sea 12 clics por
             * URL contra los 5,2 de la ficha de ciudad. El CakePHP sí la
             * monetizaba; la migración la dejó afuera.
             */
            'pais_superior' => env('ADSENSE_SLOT_PAIS_SUPERIOR'),
            'pais_medio' => env('ADSENSE_SLOT_PAIS_MEDIO'),
        ],

        /*
         * Cada cuántos grupos de letra se intercala una unidad en el índice
         * A–Z. Con 27 grupos —una ciudad grande, A–Z más el «#»— da tres
         * unidades intercaladas, una cada ~19 pantallas de teléfono.
         *
         * Es deliberadamente poco denso. El sitio viene de una penalización
         * algorítmica y una página cargada de anuncios es justo la clase de
         * señal que conviene no dar mientras Google la reevalúa. Bajarlo a 4
         * duplica las unidades; es un número, no una reescritura.
         */
        'indice_cada' => (int) env('ADSENSE_INDICE_CADA', 8),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
