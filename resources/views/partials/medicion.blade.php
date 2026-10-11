{{--
    Google Analytics 4.

    ── Por qué existe este archivo ───────────────────────────────────────────

    El CakePHP llevaba esta etiqueta en su layout y la migración no la portó.
    No falló nada: el sitio nuevo salió a producción midiendo cero, y el
    síntoma fue que Analytics dejó de recibir datos sin ningún error en
    ninguna parte. Es la misma forma de fallar que el `build/` de Vite y que
    el `tiene_calles` en cero — cosas que no están y que nadie ve faltar.

    La propiedad es la misma de siempre (G-QJT8E0GNVR): cambiarla habría
    cortado el histórico de un sitio que viene de una caída algorítmica y
    necesita comparar contra sus propios meses anteriores.

    ── Por qué gtag y no Tag Manager ─────────────────────────────────────────

    El Cake cargaba las DOS cosas en el mismo <head>: el contenedor
    GTM-KXJVD2T y el gtag de GA4. Buscando en todo el proyecto viejo no hay un
    solo `dataLayer.push`, así que el contenedor nunca recibió un evento
    propio: o estaba vacío, o tenía una etiqueta de GA4 hacia esta misma
    propiedad y cada visita se contó dos veces.

    Acá va solo el gtag. Mide lo mismo, pesa una fracción, y no arriesga el
    doble conteo. Si algún día hace falta administrar etiquetas sin tocar
    código, GTM se agrega acá y se saca el gtag, no los dos juntos.

    ── Por qué no rompe el caché ─────────────────────────────────────────────

    Esto es JavaScript del cliente: la respuesta sigue saliendo sin
    `Set-Cookie` y sin `Vary`, que es lo que permite que LiteSpeed sirva las
    páginas públicas sin levantar PHP. La cookie `_ga` la escribe el navegador
    después, y al servidor no le llega nunca porque no la lee nadie.
    `RutasCacheablesTest` lo fija.

    ── Sin configurar no existe ──────────────────────────────────────────────

    Sin ANALYTICS_ID no se emite nada, que es lo que mantiene el local y los
    tests sin llamadas a Google. Misma convención que `partials/ad-slot`.
--}}
@php
    $medicion = config('services.analytics.id');
@endphp

@if ($medicion)
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $medicion }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '{{ $medicion }}');
    </script>
@endif
