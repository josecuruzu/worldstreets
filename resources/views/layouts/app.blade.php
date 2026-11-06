<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- Solo donde hay sesión. Las rutas públicas corren en un grupo sin
         StartSession para poder salir sin cookie y ser cacheables, y ahí
         csrf_token() lanza. El token lo necesita el POST que registra una
         búsqueda, que se dispara desde la home. --}}
    @if (request()->hasSession())
        <meta name="csrf-token" content="{{ csrf_token() }}">
    @endif
    {{-- El mapa con el pin, el mismo dibujo que la marca del header. El .ico
         queda para los navegadores que no toman el SVG. --}}
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="alternate icon" href="/favicon.ico" sizes="any">

    {{-- El canonical se escribe contra el host canónico, no contra el que
         sirvió la página: durante el corte del CakePHP el sitio vive un tiempo
         en un dominio de pruebas con el corpus entero adentro, y lo que no se
         quiere es que esa copia se declare a sí misma como el original. De la
         query string sobrevive solo `page`; ver App\Support\Canonico. --}}
    <link rel="canonical" href="{{ $canonical ?? \App\Support\Canonico::deLaPeticion(request()) }}">

    {{-- Una página abierta desde un enlace compartido (`/r/{codigo}`) no es
         una URL del sitio: es un mensaje de WhatsApp que resultó ser una
         página. No se indexa, y su canonical apunta a la ficha de la ciudad,
         que sí existe. `follow` para que el rastreador que llegue por un
         enlace pegado en un foro siga hacia la ficha en vez de frenar. --}}
    @if (! empty($compartido))
        <meta name="robots" content="noindex,follow">
    @endif

    {{-- Las dos versiones de esta página, en los dos idiomas.

         Cada versión declara las dos, incluida la suya: un hreflang que la
         otra página no devuelve es un hreflang que Google ignora, y el sitio
         quedaría con dos páginas compitiendo en vez de una pareja.

         Van contra el host canónico por la misma razón que el canonical: en el
         dominio de pruebas, un hreflang que apunte ahí declara esa copia como
         la versión buena de un idioma.

         Solo aparecen en las fichas de los 21 países hispanohablantes, que son
         las únicas que tienen versión en español; ver `App\Support\Idioma`. --}}
    @isset($alternos)
        @foreach ($alternos as $idioma => $enlace)
            <link rel="alternate" hreflang="{{ $idioma }}"
                  href="{{ \App\Support\Canonico::url($enlace) }}">
        @endforeach
    @endisset

    @yield('meta')
    {{--
        El sufijo de marca es corto a propósito. Google corta el <title> cerca
        de los 60 caracteres, y la parte que tiene que sobrevivir al corte es la
        frase del principio: `Reverse Address Lookup Free in {lugar}` es el 73%
        de los clics del sitio. Con ` - worldstreets.global` se comía 21
        caracteres del presupuesto; el Cake usaba ` | WSG`, que son 6 pero no
        dice nada. Seis más, y el nombre real.
    --}}
    <title>@yield('title') | worldstreets</title>

    {{-- La descripción no posiciona, decide el clic. Y es donde el sitio nuevo
         tiene algo que el viejo no tenía: cifras de cobertura reales. --}}
    @hasSection('description')
        <meta name="description" content="{{ \Illuminate\Support\Str::squish($__env->yieldContent('description')) }}">
    @endif

    {{-- DM Serif Display es el sustituto documentado de Nineties, que es una
         fuente propia del sistema. De Inter hacen falta el 400 y el 550. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    {{-- La hoja de fuentes NO bloquea el render.

         Medido con Lighthouse sobre la ficha de ciudad: era el único recurso
         que bloqueaba, y costaba 833 ms del First Contentful Paint. Un
         <link rel="stylesheet"> normal frena el pintado hasta que la hoja
         llega, y esta viene de otro dominio.

         El truco es el de siempre: se pide como `media="print"` —que el
         navegador baja sin bloquear, porque no aplica a la pantalla— y al
         cargar se pasa a `all`. La URL ya lleva `display=swap`, así que el
         texto se ve con la fuente de sistema y cambia cuando llega la buena.
         El <noscript> cubre al que no tiene JavaScript. --}}
    <link rel="preload" as="style"
          href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Inter:wght@400;500;600&display=swap">
    <link rel="stylesheet" media="print" onload="this.media='all';this.onload=null"
          href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Inter:wght@400;500;600&display=swap">
    <noscript>
        <link rel="stylesheet"
              href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Inter:wght@400;500;600&display=swap">
    </noscript>

    {{-- Lo que cada página necesita en la cabecera y las demás no: hoy, el
         preconnect al servidor de tiles desde la ficha de ciudad. --}}
    @stack('cabeza')

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Google Analytics. Va acá y no en el <body> porque gtag mide el tiempo
         de carga desde que se ejecuta, y abajo de todo mediría de menos.
         Sin ANALYTICS_ID no emite nada. --}}
    @include('partials.medicion')

    {{-- El cargador de AdSense, UNA sola vez en todo el sitio.
         En el sitio hermano hubo dos en la misma página —uno del layout y uno
         que el dueño pegó a mano— y el resultado fue `adsbygoogle.length === 4`
         con la cola sin procesar y todos los <ins> en 0×0: ningún anuncio y
         ningún error visible. Los bloques se ponen con `partials/ad-slot`, que
         nunca vuelve a cargar el script.
         Sin ADSENSE_CLIENT no se emite nada. --}}
    @if (config('services.adsense.client'))
        <link rel="preconnect" href="https://pagead2.googlesyndication.com" crossorigin>
        <script async
                src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={{ config('services.adsense.client') }}"
                crossorigin="anonymous"></script>
    @endif
</head>
<body class="bg-cream font-sans text-body text-ink">

    {{--
        La barra superior. Transparente a propósito: deja ver el canvas crema,
        que es lo que define el tono del sistema. Sin fondo propio, sin sombra
        y sin sticky — el papel no flota.

        La marca es el mapa con el pin más el wordmark, y al lado la línea que
        el sitio viejo usaba de brand: "Worldwide Address Lookup". Antes acá
        había un cuadradito negro con las letras "WS", que no dice nada, y a la
        derecha un enlace "Search" que llevaba a la home desde la home. La
        línea hace el trabajo que ese enlace no hacía: decirle al que llega de
        Google qué es esto.
    --}}
    <header class="contenedor flex flex-wrap items-center gap-x-4 gap-y-2 py-4">
        <a href="{{ route('home') }}"
           class="flex shrink-0 items-center gap-2.5 text-ink no-underline hover:no-underline"
           aria-label="worldstreets — worldwide address lookup">
            @include('partials.logo', ['clase' => 'h-[30px] w-[30px]'])
            <span class="font-display text-[22px] leading-none tracking-[-0.01em]">worldstreets</span>
        </a>

        {{-- En móvil se cae a la línea de abajo en vez de apretar el wordmark. --}}
        <span class="hidden text-cap text-ash sm:inline" aria-hidden="true">·</span>
        <span class="text-cap text-ash">Worldwide address lookup</span>
    </header>

    <main class="pb-16">
        @yield('content')
    </main>

    <footer class="contenedor pb-10">
        <p class="border-t border-mist pt-6 text-cap text-ash">
            Street names are those on record in our database. Place data from
            <a href="https://www.geonames.org/" rel="noopener">GeoNames</a>; map tiles
            &copy; <a href="https://www.openstreetmap.org/copyright" rel="noopener">OpenStreetMap</a>
            contributors.
            <a href="{{ route('about') }}">About</a>
        </p>
    </footer>

</body>
</html>
