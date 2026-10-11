@extends('layouts.app')

@use('App\Support\Idioma')

{{-- La frase que rankea, primero. Formato del Cake:
     `Reverse Address Lookup Free in {ciudad}, {provincia}, | WSG`
     (`CiudadesController.php`, líneas 63 y 72). --}}
@section('title', __('lugares.ciudad.titulo', [
    'ciudad' => $ciudad->display_name,
    'region' => $ciudad->provincia?->nombre ?: $ciudad->pais?->nombre,
]))

@section('description', __('lugares.ciudad.descripcion', [
    'lugar' => $ciudad->display_name.', '
        .($ciudad->provincia?->nombre ? $ciudad->provincia->nombre.', ' : '').$ciudad->pais?->nombre,
    'calles' => Idioma::numero($totalCalles),
    'palabra' => trans_choice('lugares.calles', $totalCalles),
]))

{{-- El mapa es el elemento más grande de la pantalla, o sea el LCP, y sus
     tiles vienen de otro dominio. Abrir la conexión —DNS, TCP y TLS— mientras
     todavía se está parseando el HTML le saca ese tiempo al camino crítico.
     Es lo único que se puede hacer del lado nuestro con un recurso de OSM. --}}
@push('cabeza')
    <link rel="preconnect" href="https://tile.openstreetmap.org" crossorigin>
    <link rel="dns-prefetch" href="https://tile.openstreetmap.org">

    {{-- Y el tile del centro, que es el LCP de esta página. Se calcula en el
         servidor con la misma proyección que usa Leaflet, así que el
         navegador lo pide mientras parsea el HTML en vez de esperar a que el
         mapa se monte. Ver `PrecargaDelMapa::tileCentral`. --}}
    @if ($tilePrecargado)
        <link rel="preload" as="image" fetchpriority="high" href="{{ $tilePrecargado }}" crossorigin>
    @endif

    {{-- Y el chunk de Leaflet, que se pide con import() dinámico y por lo
         tanto el navegador no lo descubre hasta que corre app.js. Acá el mapa
         es el LCP, así que ese eslabón de más cuesta caro; ver
         `PrecargaDelMapa`. En las demás páginas no se emite nada y el mapa no
         se baja. --}}
    @foreach (\App\Support\PrecargaDelMapa::enlaces()['modulos'] as $modulo)
        <link rel="modulepreload" href="{{ $modulo }}">
    @endforeach
    @foreach (\App\Support\PrecargaDelMapa::enlaces()['estilos'] as $estilo)
        <link rel="preload" as="style" href="{{ $estilo }}">
    @endforeach
@endpush

@section('content')
    <div class="contenedor">
    <nav class="flex flex-wrap items-center gap-2 text-cap text-ash" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">{{ __('lugares.inicio') }}</a>
        <span class="text-lavender" aria-hidden="true">/</span>
        <a href="{{ Idioma::url('pais', $ciudad->pais->url) }}">{{ $ciudad->pais->nombre }}</a>
        <span class="text-lavender" aria-hidden="true">/</span>
        <a href="{{ Idioma::url('provincia', [$ciudad->pais->url, $ciudad->provincia_slug]) }}">
            {{ $ciudad->provincia?->nombre ?: __('lugares.region_desconocida') }}
        </a>
        <span class="text-lavender" aria-hidden="true">/</span>
        <span class="text-ink" aria-current="page">{{ $ciudad->display_name }}</span>
    </nav>

    <div class="mt-5 flex flex-wrap items-end justify-between gap-6">
        <div>
            <div class="flex flex-wrap items-center gap-4">
                <h1 class="font-display text-[34px] leading-[1.08] sm:text-head">{{ $ciudad->display_name }}</h1>
                @if (in_array($ciudad->fcode, ['PPLC', 'PPLA'], true))
                    <span class="tag">{{ $ciudad->fcode === 'PPLC' ? 'Capital city' : 'Regional capital' }}</span>
                @endif
            </div>
            <p class="mt-3 text-sub">
                {{ $ciudad->provincia?->nombre ?: __('lugares.region_desconocida') }}, {{ $ciudad->pais->nombre }}
                @if ($ciudad->population)
                    {{-- La población va en tinta y no en Ash: es un dato, no una pista. --}}
                    <span class="text-lavender" aria-hidden="true">·</span> {{ __('lugares.ciudad.personas', ['n' => Idioma::numero($ciudad->population)]) }}
                @endif
            </p>
        </div>
        <p class="shrink-0 text-body text-ash">{{ __('lugares.ciudad.calles_registradas', ['calles' => Idioma::numero($totalCalles)]) }}</p>
    </div>

    {{--
        Anuncio 1 de 4: arriba del mapa.

        Acá el alto no es una preferencia: con `auto` AdSense sirve un 336×280
        en este hueco y empuja el buscador y el mapa 280 px hacia abajo en un
        teléfono. El buscador es la función del sitio; no puede quedar fuera de
        la primera pantalla por un anuncio.

        Este bloque estuvo con `$fijo` y **no alcanzó**: el tamaño lo manda la
        unidad del panel de AdSense, y si se creó como responsive —lo que
        AdSense ofrece por defecto— el `height:50px` del marcado no la frena.
        Pasó primero en calles-argentinas y se repitió acá.

        Por eso va `horizontal` con full-width-responsive apagado: limita el
        inventario a 320×50, 320×100 y 728×90, así que el alto máximo es 100 px
        en vez de 280, y funciona esté la unidad configurada como esté. El
        detalle completo, en el docblock de `partials/ad-slot`.

        Se reservan 100 px, que es el mayor de los tres formatos: reservar 50
        dejaría saltar la página los 50 de diferencia cuando llega un 320×100.
    --}}
    @include('partials.ad-slot', [
        'slot' => config('services.adsense.slots.ciudad_superior'),
        'formato' => 'horizontal',
        'anchoCompleto' => false,
        'clase' => 'mt-6',
        'estilo' => 'display:block;min-height:100px',
    ])

    {{--
        El mapa es la página, no una miniatura. Y es nuestro: un Leaflet sobre
        los tiles de OSM, igual que el sitio viejo. Antes acá había un iframe de
        `openstreetmap.org/export`, que es la página de ellos metida en la
        nuestra: con su barra de atribución, sus controles, y un marcador que no
        se puede mover sin recargar el iframe entero.

        El buscador va adentro de la misma tarjeta: hasta ahora la ficha solo
        tenía un botón que mandaba a la home a volver a tipear la ciudad, y es
        la página que recibe el tráfico de Google. Era el agujero más caro del
        sitio.

        Después el botón pasó a ser un GET a la home con `street`, `number` y
        `city` colgados de la query string — parámetros que `home()` no lee, así
        que buscar acá devolvía la home vacía. Ahora la ficha busca en su propia
        página, con el mismo componente y el mismo endpoint que la home: se le
        entrega la ciudad ya resuelta y el paso 1 no hace falta.
    --}}
    <section class="mt-7 overflow-hidden rounded-card border border-mist bg-paper"
             x-data="addressFinderApp(@js($direccionInicial ?? null))"
             x-init="ciudad = @js([
                 'id' => $ciudad->id,
                 'nombre' => $ciudad->display_name,
                 'provincia' => $ciudad->provincia?->nombre,
                 'pais' => $ciudad->pais?->nombre,
                 'lat' => (float) $ciudad->centro_x,
                 'lon' => (float) $ciudad->centro_y,
                 'zoom' => $ciudad->zoom,
             ])">
        {{-- El orden de los dos campos lo decide el idioma, no el diseño.

             En inglés la dirección se escribe "6455 Macleod Trail SE" y el
             número va primero; en español es "Calle 23 #456" y va después. El
             sitio viejo ya hacía exactamente esta vuelta —número primero para
             `eng` y `fra`, calle primero para `esp`, `por` e `ita`
             (`Ciudades/plano.ctp`, líneas 86-101)— y la migración se había
             quedado con un solo orden.

             No es cosmético: el visitante completa los campos en el orden en
             que escribiría la dirección, y con el orden cambiado tipea el
             número donde va la calle. Lo mismo hace `direccionCompleta` en
             `app.js`, que lee el idioma de `<html lang>`. --}}
        <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
            @if (Idioma::esEspanol())
                @include('partials.campo-calle')
                @include('partials.campo-numero')
            @else
                @include('partials.campo-numero')
                @include('partials.campo-calle')
            @endif

            <button type="button" x-on:click="buscar()" class="btn-dark shrink-0"
                    x-bind:disabled="buscandoDireccion"
                    x-text="buscandoDireccion ? @js(__('lugares.ciudad.localizando')) : @js(__('lugares.buscar'))">{{ __('lugares.buscar') }}</button>
        </div>

        {{-- La dirección encontrada. Antes del mapa: es la respuesta a lo que
             se acaba de tipear, y abajo está el marcador. --}}
        <template x-if="resultado">
            <div class="border-t border-mist bg-cream px-4 py-5">
                <p class="font-display text-head-sm" x-text="direccionCompleta"></p>
                <p class="mt-1.5 text-cap text-ash" x-text="resultado.etiqueta"></p>

                {{--
                    Compartir la dirección.

                    Es lo primero que quiere hacer alguien que acaba de
                    encontrar una: mandarla por WhatsApp. Y hasta ahora no
                    había nada que pegar, porque una dirección no tiene URL en
                    este sitio —la altura es infinita y una página por altura
                    es lo que trajo la penalización—. El botón fabrica una
                    URL corta y opaca; ver `CompartirController`.

                    En el teléfono abre la hoja de compartir del sistema
                    (`navigator.share`), que es donde está WhatsApp. En el
                    escritorio, donde esa API casi no existe, copia el enlace
                    al portapapeles y lo dice.
                --}}
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <button type="button" x-on:click="compartir()"
                            x-bind:disabled="compartiendo"
                            class="az w-auto border-lavender px-4 no-underline hover:no-underline">
                        <span x-show="!enlaceCopiado">{{ __('lugares.ciudad.compartir') }}</span>
                        <span x-show="enlaceCopiado" x-cloak>{{ __('lugares.ciudad.compartido') }}</span>
                    </button>

                    <button type="button" x-on:click="copiarDireccion()"
                            class="az w-auto border-lavender px-4 no-underline hover:no-underline">
                        <span x-show="!direccionCopiada">{{ __('lugares.ciudad.copiar') }}</span>
                        <span x-show="direccionCopiada" x-cloak>{{ __('lugares.ciudad.copiado') }}</span>
                    </button>

                    <a :href="osmUrl" target="_blank" rel="noopener noreferrer" class="text-cap">
                        {{ __('lugares.ciudad.ver_en_osm') }} &rarr;
                    </a>
                </div>

                {{-- El respaldo del respaldo: sin `navigator.share` y sin
                     portapapeles —un navegador viejo, o la página servida por
                     http—, el enlace se muestra para copiarlo a mano. Es raro,
                     pero la alternativa es un botón que no hace nada. --}}
                <p x-show="enlaceALaVista" x-cloak class="mt-3 break-all text-cap text-ash">
                    {{ __('lugares.ciudad.no_se_pudo_copiar') }}<span x-text="enlaceALaVista"></span>
                </p>
            </div>
        </template>

        {{-- Y la que no existe. El sitio viejo acá tiraba un alert() del
             navegador. --}}
        <template x-if="sinResultado">
            <p class="border-t border-mist bg-cream px-4 py-4 text-body text-ash">
                {{ __('lugares.ciudad.no_encontrada', ['ciudad' => $ciudad->display_name]) }}
            </p>
        </template>

        {{-- Los resultados por nombre de calle. Solo aparecen cuando se buscó
             algo: el listado completo de la ciudad ya está más abajo, servido
             por el servidor y en la página que Google indexa. --}}
        <template x-if="cargando">
            <p class="border-t border-mist px-4 py-4 text-body text-ash">{{ __('lugares.buscando') }}</p>
        </template>

        <template x-if="!cargando && calleQueryAplicada && calles.length">
            <div class="border-t border-mist px-4 py-4">
                <h3 class="eyebrow">{{ __('lugares.ciudad.coincidentes') }}</h3>
                <ul class="mt-2 divide-y divide-mist">
                    <template x-for="c in calles" :key="c.nombre">
                        <li>
                            <button type="button" x-on:click="elegirCalle(c.nombre)"
                                    class="-mx-3 flex w-[calc(100%+1.5rem)] items-center rounded-card px-3 py-2.5 text-left transition hover:bg-cream">
                                <span x-text="c.nombre"></span>
                            </button>
                        </li>
                    </template>
                </ul>
            </div>
        </template>

        <template x-if="!cargando && calleQueryAplicada && !calles.length">
            <p class="border-t border-mist px-4 py-4 text-body text-ash">
                {{ __('lugares.ciudad.sin_coincidencias_1') }}<span x-text="calleQueryAplicada"></span>{{ __('lugares.ciudad.sin_coincidencias_2', ['ciudad' => $ciudad->display_name]) }}
            </p>
        </template>

        <p x-show="error" x-cloak x-text="error"
           class="border-t border-mist px-4 py-4 text-cap text-ink"></p>

        {{-- x-effect y no una llamada suelta: Alpine lo vuelve a correr cuando
             aparece una dirección, así que el marcador no depende de que
             alguien se acuerde de invocarlo. --}}
        {{-- `data-mapa` no es decorativo: `app.js` lo busca al arrancar para
             empezar a bajar Leaflet en paralelo con Alpine, en vez de esperar
             a que el x-effect lo pida. Ver el comentario de `cargarLeaflet`. --}}
        <div data-mapa
             x-effect="pintarMapa($el, ciudad, resultado)"
             class="z-0 h-[320px] border-t border-mist sm:h-[520px]"></div>

        {{-- Sin JavaScript el Leaflet no se monta, y esta es una página que
             Google recorre. El iframe de OSM no necesita nada. --}}
        <noscript>
            @php
                $d = 0.06;
                $bbox = implode(',', [
                    round((float) $ciudad->centro_y - $d, 5),
                    round((float) $ciudad->centro_x - $d, 5),
                    round((float) $ciudad->centro_y + $d, 5),
                    round((float) $ciudad->centro_x + $d, 5),
                ]);
            @endphp
            <div class="h-[320px] border-t border-mist sm:h-[520px]">
                <iframe src="https://www.openstreetmap.org/export/embed.html?bbox={{ $bbox }}&layer=mapnik&marker={{ (float) $ciudad->centro_x }},{{ (float) $ciudad->centro_y }}"
                        class="h-full w-full" style="border:0" loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        title="{{ __('lugares.ciudad.mapa_de', ['ciudad' => $ciudad->display_name]) }}"></iframe>
            </div>
        </noscript>
    </section>

    {{--
        Anuncio 2 de 3: entre la herramienta y el índice de calles.

        Es el corte natural de la página —arriba se busca, abajo se navega— y
        el único lugar intermedio donde un anuncio no se mete entre dos partes
        de la misma tarea. Acá sí va `auto`: lo que hay debajo es una lista,
        así que un formato más alto no tapa ninguna función.
    --}}
    @include('partials.ad-slot', [
        'slot' => config('services.adsense.slots.ciudad_medio'),
        'clase' => 'mt-6',
    ])

    <div class="mt-6 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">

        <section class="card">
            <h2 class="font-display text-head-sm">{{ __('lugares.ciudad.indice_titulo', ['ciudad' => $ciudad->display_name]) }}</h2>
            {{--
                "Every street visitors have looked up here", no "most searched":
                1.017.913 de las 1.059.464 filas tienen el contador en 0, así que
                hablar de popularidad sería describir ruido. Por lo mismo no se
                muestra ningún contador.
            --}}
            <p class="mt-2.5 text-body text-ash">{{ __('lugares.ciudad.indice_sub') }}</p>

            {{--
                La barra de letras son anclas dentro de esta misma página, no
                enlaces a otras URLs. Un `?letter=a` por ciudad serían 26 URLs ×
                18.361 ciudades, justo lo que el sitio está sacándose de encima.
            --}}
            {{--
                La barra de grupos.

                En los países hispanohablantes el índice agrupa por TIPO DE VÍA
                y no por letra inicial, así que arriba de las letras va una fila
                de tipos: Calle, Carrera, Avenida. Es por donde el visitante
                entra —una dirección colombiana ES «Calle 45»— y además es lo
                que arregla que el 81% de las calles del país cayeran bajo la
                «C». Ver el comentario de `Busqueda::indiceDeCiudad`.

                En inglés no hay tipos que mostrar y la barra queda igual que
                antes: el tipo va al final del nombre y la letra inicial es la
                del nombre real.
            --}}
            {{-- ⚠️ En esta vista TODOS los bloques de PHP son de la forma
                 larga, con apertura y cierre, y tiene que seguir siendo así.

                 Blade extrae los bloques de PHP con una expresión perezosa
                 que busca el cierre más cercano. Con la forma de una línea
                 mezclada, el primer cierre del archivo le sirve de cierre a
                 una apertura que estaba MUCHO más arriba, y todo lo del medio
                 desaparece del HTML. Sin error, sin aviso: la sección
                 simplemente no está. Así se perdió acá la barra de letras
                 entera.

                 Por lo mismo, no se nombra la directiva dentro de un
                 comentario: el nombre escrito abre un bloque de verdad.

                 Y OJO con nombrar la directiva dentro de un comentario:
                 Blade extrae los bloques de PHP ANTES de quitar los
                 comentarios, así que el nombre escrito acá abre un bloque de
                 verdad y se traga la vista hasta el cierre siguiente. Sin dar
                 error: la sección simplemente desaparece del HTML. --}}
            @php
                $tipos = $indice->filter(fn (array $g) => $g['tipo']);
            @endphp

            @if ($tipos->isNotEmpty())
                <nav class="mt-5 flex flex-wrap gap-1.5" aria-label="{{ __('lugares.ciudad.saltar_tipo') }}">
                    @foreach ($tipos as $tipo => $grupo)
                        <a href="#letra-{{ Str::slug($tipo) }}"
                           class="az w-auto px-3 no-underline hover:no-underline">{{ Str::title($tipo) }}</a>
                    @endforeach
                </nav>
            @endif

            <nav class="{{ $tipos->isNotEmpty() ? 'mt-2' : 'mt-5' }} flex flex-wrap gap-1.5"
                 aria-label="{{ __('lugares.ciudad.saltar_letra') }}">
                @foreach (array_merge(['#'], range('A', 'Z')) as $letra)
                    @php
                        $ancla = $letra === '#' ? 'num' : Str::lower($letra);
                    @endphp
                    @if ($indice->has($letra))
                        <a href="#letra-{{ $ancla }}" class="az no-underline hover:no-underline">{{ $letra }}</a>
                    @else
                        {{-- La letra sin calles queda a la vista pero apagada: dice
                             que no hay nada, en vez de dejar el hueco sin explicar. --}}
                        <span class="az-vacia select-none" aria-disabled="true">{{ $letra }}</span>
                    @endif
                @endforeach
            </nav>

            <div class="mt-6 space-y-7">
                @foreach ($indice as $letra => $grupo)
                    @php
                        $ancla = $grupo['tipo']
                            ? Str::slug($letra)
                            : ($letra === '#' ? 'num' : Str::lower($letra));
                    @endphp
                    <section id="letra-{{ $ancla }}" class="scroll-mt-4">
                        <h3 class="flex items-baseline gap-2 border-b border-mist pb-1.5 font-display text-[22px] leading-[1.08]">
                            {{-- El tipo de vía se escribe entero: el grupo se
                                 llama «Calle» y adentro las filas dicen solo
                                 «45», porque repetir la palabra 1.280 veces es
                                 ruido que en un teléfono ocupa media pantalla. --}}
                            {{ $grupo['tipo'] ? Str::title($letra) : $letra }}
                            <span class="font-sans text-cap font-normal text-ash">
                                @if ($grupo['total'] > $grupo['calles']->count())
                                    {{ __('lugares.ciudad.de_total', [
                                        'mostradas' => Idioma::numero($grupo['calles']->count()),
                                        'total' => Idioma::numero($grupo['total']),
                                    ]) }}
                                @else
                                    {{ Idioma::numero($grupo['total']) }}
                                @endif
                            </span>
                        </h3>
                        {{-- Las clases de cada <li> van en el <ul>: repetidas por
                             calle eran 73 KB de los 328 que pesaba Waterloo.

                             El corte a dos columnas es a 390 px y no en `sm`
                             (640). Medido en Calgary, 2.378 calles:

                                 320 px, 1 col   80.196 px   95 pantallas
                                 320 px, 2 col   66.700 px   pero el 81% de los
                                                             nombres se parte
                                 390 px, 2 col   48.636 px   58 pantallas, 22%
                                 430 px, 2 col   43.638 px   52 pantallas, 5%

                             O sea: a 320 la segunda columna deja 106 px por
                             nombre y casi todo cae en dos líneas, que cuesta
                             más de lo que ahorra. De 390 para arriba conviene.
                             `min-[390px]` en vez de `sm` justamente porque el
                             breakpoint por defecto se pasa 250 px de largo. --}}
                        <ul class="mt-2 columns-1 gap-6 text-body min-[390px]:columns-2 lg:columns-3
                                   [&>li]:break-inside-avoid [&>li]:py-1">
                            @foreach ($grupo['calles'] as $calle)
                                <li>{{ $calle }}</li>
                            @endforeach
                        </ul>
                    </section>

                    {{--
                        La unidad intercalada, cada `indice_cada` grupos.

                        Es la corrección del reparto anterior, que tenía las
                        tres unidades en la primera pantalla y en la última.
                        Medido en Calgary: la ficha son 48.636 px a 390 px de
                        ancho, 58 pantallas de teléfono, y 56 de esas 58 no
                        tenían un solo anuncio. La de abajo de todo, que es la
                        posición que más rinde, está por debajo de las 58: en
                        móvil no la ve prácticamente nadie.

                        Va entre dos grupos de letra y nunca partiendo uno, que
                        es lo que hace que se lea como una pausa de la lista y
                        no como algo metido en el medio de la «C».

                        No se emite después del último grupo: ahí ya está la
                        unidad final, y dos seguidas es exactamente la densidad
                        que conviene no tener.

                        `min-height` reserva el alto antes de que el anuncio
                        llegue. Sin eso, cada unidad que carga empuja el resto
                        de la lista hacia abajo y el CLS de la página se va al
                        demonio mientras el visitante scrollea — que es
                        justamente cuando estas unidades aparecen.
                    --}}
                    @if ($loop->iteration % config('services.adsense.indice_cada') === 0 && ! $loop->last)
                        @include('partials.ad-slot', [
                            'slot' => config('services.adsense.slots.ciudad_indice'),
                            'clase' => 'mt-7',
                            'estilo' => 'display:block;min-height:280px',
                        ])
                    @endif
                @endforeach
            </div>

            @php
                $recortadas = $indice->contains(fn (array $g) => $g['total'] > $g['calles']->count());
            @endphp
            @if ($recortadas)
                <p class="mt-7 border-t border-mist pt-5 text-cap text-ash">
                    {{ __('lugares.ciudad.recortadas', [
                        'tope' => Idioma::numero(\App\Models\Busqueda::CALLES_POR_LETRA),
                        'ciudad' => $ciudad->display_name,
                    ]) }}
                </p>
            @endif
        </section>

        <aside>
            <div class="card">
                <h2 class="eyebrow">{{ __('lugares.ciudad.sobre_el_lugar') }}</h2>
                <dl class="mt-4 flex flex-col gap-4">
                    <div>
                        <dt class="text-cap text-ash">{{ __('lugares.ciudad.campo_pais') }}</dt>
                        <dd class="mt-0.5 font-label">
                            <a href="{{ Idioma::url('pais', $ciudad->pais->url) }}">{{ $ciudad->pais->nombre }}</a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-cap text-ash">{{ __('lugares.ciudad.campo_region') }}</dt>
                        <dd class="mt-0.5 font-label">
                            <a href="{{ Idioma::url('provincia', [$ciudad->pais->url, $ciudad->provincia_slug]) }}">
                                {{ $ciudad->provincia?->nombre ?: __('lugares.region_desconocida') }}
                            </a>
                        </dd>
                    </div>
                    @if ($ciudad->population)
                        <div>
                            <dt class="text-cap text-ash">{{ __('lugares.ciudad.campo_poblacion') }}</dt>
                            <dd class="mt-0.5 font-label tabular-nums">{{ Idioma::numero($ciudad->population) }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-cap text-ash">{{ __('lugares.ciudad.campo_coordenadas') }}</dt>
                        <dd class="mt-0.5 font-label tabular-nums">
                            {{ number_format((float) $ciudad->centro_x, 3) }}, {{ number_format((float) $ciudad->centro_y, 3) }}
                        </dd>
                    </div>
                </dl>

                @if ($nombresLocales)
                    {{-- Los nombres locales son la razón por la que alguien que no
                         habla inglés puede encontrar esta ciudad desde el buscador. --}}
                    <div class="mt-5 border-t border-mist pt-5">
                        <h3 class="eyebrow">{{ __('lugares.ciudad.tambien_conocida') }}</h3>
                        <p class="mt-2.5 text-body">{{ implode(' · ', $nombresLocales) }}</p>
                    </div>
                @endif
            </div>
        </aside>
    </div>

    {{--
        Tarjeta crema: una agrupación, no una superficie. El sistema la usa
        cuando el bloque tiene que pertenecer a la página en vez de despegarse
        de ella, y el indicio es el espaciado y el radio, no el color.
    --}}
    @if ($vecinas->isNotEmpty())
        <section class="mt-6 rounded-card bg-cream p-6">
            <h2 class="eyebrow">{{ __('lugares.ciudad.mas_en', ['region' => $ciudad->provincia?->nombre ?: __('lugares.esta_region')]) }}</h2>
            <div class="mt-4 flex flex-wrap items-center gap-2">
                @foreach ($vecinas as $vecina)
                    <a href="{{ Idioma::url('ciudad', [$ciudad->pais->url, $ciudad->provincia_slug, $vecina->segmento]) }}"
                       class="az w-auto border-lavender px-4 no-underline hover:no-underline">{{ $vecina->display_name }}</a>
                @endforeach

                {{-- La única acción periwinkle de la pantalla. --}}
                <a href="{{ Idioma::url('provincia', [$ciudad->pais->url, $ciudad->provincia_slug]) }}"
                   class="btn-primary px-5 py-3 no-underline hover:no-underline">
                    {{ __('lugares.ciudad.todas_las_ciudades', ['region' => $ciudad->provincia?->nombre ?: __('lugares.esta_region')]) }}
                </a>
            </div>
        </section>
    @endif

    {{--
        Anuncio 3 de 3: al final del contenido, después de las ciudades
        vecinas. Es la posición que más rinde de las tres y la que menos
        molesta, y por eso va con `auto`: a esta altura el visitante ya hizo lo
        que vino a hacer.

        ⚠️ Ojo con el índice A–Z: la ficha de una ciudad grande mide 48.636 px
        en un teléfono, 58 pantallas. Este bloque está por debajo de todo eso,
        así que en móvil casi nadie va a llegar. Si alguna vez se pliega el
        índice, esta unidad pasa a valer de verdad.
    --}}
    @include('partials.ad-slot', [
        'slot' => config('services.adsense.slots.ciudad_final'),
        'clase' => 'mt-8',
    ])
    </div>
@endsection
