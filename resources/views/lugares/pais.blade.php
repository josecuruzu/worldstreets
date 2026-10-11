@extends('layouts.app')

@use('App\Support\Idioma')

{{--
    El <title> arranca con la frase exacta del sitio viejo. No es nostalgia: es
    el 73% de los clics y el 100% de lo que rankea en las fichas de país
    —`reverse address lookup canada` trae 24 clics de EE.UU. sobre 1.393
    impresiones—. El rediseño la había reemplazado por
    "Canada — regions, cities and streets", que no la contiene.

    Formato del Cake: `Reverse Address Lookup Free in {país} | WSG`
    (`PaisesController.php`, línea 27).
--}}
@section('title', __('lugares.pais.titulo', ['pais' => $pais->nombre]))

{{--
    La descripción es la del Cake más las cifras de cobertura, que es lo único
    que el sitio nuevo tiene y el viejo no. Importa por una razón medida: las
    fichas de país reúnen 100.837 impresiones y en escritorio el CTR es 0,36%
    en posición 21,5 — a esa profundidad el snippet es lo único que decide.

    Cuando el país no tiene calles no se promete cobertura: se dice qué hay.
--}}
@section('description', $callesDelPais
    ? __('lugares.pais.descripcion_con_calles', [
        'pais' => $pais->nombre,
        'calles' => Idioma::numero($callesDelPais),
        'palabra_calles' => trans_choice('lugares.calles', $callesDelPais),
        'ciudades' => Idioma::numero($ciudadesConCalles),
        'palabra_ciudades' => trans_choice('lugares.ciudades', $ciudadesConCalles),
    ])
    : __('lugares.pais.descripcion_sin_calles', [
        'pais' => $pais->nombre,
        'regiones' => Idioma::numero(count($provincias)),
        'palabra_regiones' => trans_choice('lugares.regiones', count($provincias)),
    ]))

@section('content')
    <div class="contenedor">
    <nav class="flex flex-wrap items-center gap-2 text-cap text-ash" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">{{ __('lugares.inicio') }}</a>
        <span class="text-lavender" aria-hidden="true">/</span>
        <span class="text-ink" aria-current="page">{{ $pais->nombre }}</span>
    </nav>

    <header class="mt-5">
        {{--
            El h1 lleva la frase que rankea. El Cake pone
            "Reverse address lookup in cities of {país}" como título visible
            (`PaisesController.php`, línea 28) y el rediseño lo había dejado en
            el nombre del país a secas.
        --}}
        <h1 class="font-display text-[34px] leading-[1.08] sm:text-head">
            {{ __('lugares.pais.h1', ['pais' => $pais->nombre]) }}
        </h1>
        <p class="mt-4 max-w-[720px] text-sub [text-wrap:pretty]">
            {{ __('lugares.pais.intro') }}
        </p>
    </header>

    {{--
        El buscador. Hasta acá esta página no tenía ninguno: el visitante que
        llegaba de `reverse address lookup canada` —24 clics de EE.UU. sobre
        1.393 impresiones, la consulta número uno del país— tenía que elegir
        región, después ciudad, y recién ahí podía buscar una dirección. Tres
        clics hasta lo que vino a hacer.
    --}}
    <section class="relative mt-7" x-data="buscadorDePais(@js($pais->url))">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <label for="ciudad-del-pais" class="sr-only">{{ __('lugares.pais.buscador_label', ['pais' => $pais->nombre]) }}</label>
            <div class="relative grow">
                <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2" viewBox="0 0 24 24"
                     fill="none" stroke="#8f8f8f" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"></circle><path d="M16.2 16.2 21 21"></path>
                </svg>
                <input id="ciudad-del-pais" type="text" autocomplete="off"
                       x-model="query" x-on:input="buscar()"
                       x-on:keydown.enter.prevent="irALaPrimera()"
                       x-on:click.outside="mostrar = false"
                       placeholder="{{ __('lugares.pais.buscador_placeholder', ['pais' => $pais->nombre]) }}"
                       class="field bg-paper py-3.5 pl-12">
            </div>
            <button type="button" x-on:click="irALaPrimera()" class="btn-dark shrink-0"
                    x-bind:disabled="cargando"
                    x-text="cargando ? @js(__('lugares.buscando')) : @js(__('lugares.buscar'))">{{ __('lugares.buscar') }}</button>
        </div>

        <div x-show="mostrar && ciudades.length" x-transition x-cloak
             class="absolute z-10 mt-2 w-full overflow-hidden rounded-card border border-mist bg-paper shadow-card">
            <template x-for="c in ciudades" :key="c.id">
                <a :href="c.ruta || '#'" x-bind:aria-disabled="c.ruta ? null : 'true'"
                   class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left text-ink
                          no-underline transition hover:bg-cream hover:no-underline">
                    <span>
                        <span class="font-label" x-text="c.nombre"></span>
                        <span class="text-cap text-ash" x-text="c.provincia"></span>
                    </span>
                    {{-- Sin calles no hay ficha: la fila queda, dice por qué, y
                         no promete una página que responde 410. --}}
                    <span x-show="!c.ruta" class="shrink-0 text-cap text-ash">{{ __('lugares.pais.sin_calles_todavia') }}</span>
                </a>
            </template>
        </div>

        <p x-show="sinResultados && !cargando" x-cloak class="mt-2 text-cap text-ash">
            {{ __('lugares.pais.sin_resultados', ['pais' => $pais->nombre]) }}
        </p>
        <p x-show="error" x-cloak x-text="error" class="mt-2 text-cap text-ink"></p>
    </section>

    {{--
        Tres cifras como banda editorial, no como tarjetas de estadística: el
        sistema carga la jerarquía con color y peso, no con superficies.

        Y son las cifras que importan —ciudades CON lista de calles, no las del
        dataset—: decir "13.247 ciudades" cuando 48 tienen calles promete lo que
        no hay.
    --}}
    <div class="mt-9 flex flex-wrap items-baseline gap-x-14 gap-y-7 border-y border-mist py-7">
        <div>
            <p class="font-display text-[34px] leading-[1.08] tabular-nums sm:text-head">{{ Idioma::numero($ciudadesConCalles) }}</p>
            <p class="mt-2 text-cap">{{ __('lugares.pais.cifra_ciudades', ['palabra' => trans_choice('lugares.ciudades_may', $ciudadesConCalles)]) }}</p>
        </div>
        <div>
            <p class="font-display text-[34px] leading-[1.08] tabular-nums sm:text-head">{{ Idioma::numero($callesDelPais) }}</p>
            <p class="mt-2 text-cap">{{ __('lugares.pais.cifra_calles', ['palabra' => trans_choice('lugares.calles_may', $callesDelPais)]) }}</p>
        </div>
        <div>
            <p class="font-display text-[34px] leading-[1.08] tabular-nums sm:text-head">{{ Idioma::numero(count($provincias)) }}</p>
            <p class="mt-2 text-cap">{{ trans_choice('lugares.regiones_may', count($provincias)) }}</p>
        </div>
    </div>

    {{--
        La primera unidad de la ficha de país.

        Esta página no tenía ninguna y es la más eficiente del sitio: 246 URLs
        y 2.951 clics, o sea 12 clics por URL contra los 5,2 de la ficha de
        ciudad. El CakePHP la monetizaba con dos unidades —una arriba y una
        abajo del mapa, en `Paises/index.ctp`— y la migración se las llevó
        puestas sin que nadie lo notara.

        Va DESPUÉS del buscador y de las cifras de cobertura, no antes: el que
        llega de `reverse address lookup canada` viene a buscar una dirección,
        y el buscador es lo primero que tiene que ver.

        `horizontal` con full-width-responsive apagado, por la misma razón que
        el de arriba de la ficha de ciudad: con `auto` AdSense sirve un 336×280
        acá y empuja el contenido del país fuera de la pantalla del teléfono, y
        el marcado de tamaño fijo no lo frena si la unidad del panel es
        responsive. Ver el docblock de `partials/ad-slot`.
    --}}
    @include('partials.ad-slot', [
        'slot' => config('services.adsense.slots.pais_superior'),
        'formato' => 'horizontal',
        'anchoCompleto' => false,
        'clase' => 'mt-8',
        'estilo' => 'display:block;min-height:100px',
    ])

    {{--
        Cómo se escribe una dirección en este país.

        Es el contenido que contesta la intención con la que llega el visitante
        de EE.UU. —verificar o completar una dirección de otro país— y el que
        atiende sus 41 consultas de código postal con 528 impresiones y cero
        clics. Tiene además la propiedad que necesitaba la ficha de país: vale
        igual con 380 calles que con 471.503, y la cobertura del dataset no
        coincide con la demanda.

        Solo aparece donde el dato es firme (`FormatoDeDireccion`, 21 países).
    --}}
    @php($formato = \App\Support\FormatoDeDireccion::de($pais->id))

    @if ($formato)
        <section class="mt-6 rounded-card bg-cream p-6 sm:p-8">
            <h2 class="font-display text-head-sm">{{ __('lugares.direccion.titulo', ['pais' => $pais->nombre]) }}</h2>
            <p class="mt-2.5 max-w-[720px] text-body text-ash [text-wrap:pretty]">
                {{ __('lugares.direccion.intro', ['pais' => $pais->nombre]) }}
            </p>

            <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                <div class="card">
                    <h3 class="eyebrow">{{ __('lugares.direccion.lineas') }}</h3>
                    {{-- Numerada: el orden ES el contenido. En Japón y China va
                         al revés que en EE.UU., y en México y Alemania el número
                         va después del nombre de la calle. --}}
                    <ol class="mt-4 flex flex-col gap-2 text-body">
                        @foreach ($formato['lineas'] as $i => $linea)
                            <li class="flex gap-3">
                                <span class="w-5 shrink-0 text-cap tabular-nums text-ash">{{ $i + 1 }}</span>
                                <span>{{ $linea }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>

                <div class="flex flex-col gap-6">
                    <div class="card">
                        <h3 class="eyebrow">{{ $formato['codigo']['nombre'] }}</h3>
                        {{-- El ejemplo en grande y en display: es el dato que se
                             copia, no un adorno. --}}
                        <p class="mt-3.5 font-display text-head-sm tabular-nums">{{ $formato['codigo']['ejemplo'] }}</p>
                        <p class="mt-2.5 text-body">{{ $formato['codigo']['formato'] }}</p>
                    </div>

                    <div class="card">
                        <h3 class="eyebrow">{{ __('lugares.direccion.trampas') }}</h3>
                        <p class="mt-3.5 text-body [text-wrap:pretty]">{{ $formato['nota'] }}</p>
                    </div>
                </div>
            </div>

            <p class="mt-6 max-w-[720px] text-cap text-ash [text-wrap:pretty]">
                {{ __('lugares.direccion.aviso') }}
            </p>
        </section>
    @endif

    {{--
        La segunda unidad: entre el bloque de formato de dirección y las listas
        de regiones y ciudades.

        En un teléfono esta página es una sola columna, así que el orden de
        lectura es buscador → cifras → cómo se escribe una dirección → regiones
        → ciudades. Este es el corte natural del medio: termina un bloque de
        contenido y empieza otro, y el anuncio no se mete entre dos partes de
        la misma tarea.

        Acá sí va `auto`: lo que sigue es una lista de enlaces, así que un
        formato más alto no tapa ninguna función. Con `min-height` reservado
        para que no empuje las listas al cargar.
    --}}
    @include('partials.ad-slot', [
        'slot' => config('services.adsense.slots.pais_medio'),
        'clase' => 'mt-8',
        'estilo' => 'display:block;min-height:280px',
    ])

    <div class="mt-10 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">

        <div class="flex flex-col gap-6">
            @if ($provincias)
                <section class="card">
                    <h2 class="font-display text-head-sm">{{ __('lugares.pais.regiones_titulo') }}</h2>
                    {{-- El número de cada región son CALLES y no ciudades.
                         Contesta la pregunta con la que el visitante llega
                         —"¿va a estar mi dirección acá?"— que es lo que
                         "134 cities" no contesta. --}}
                    <p class="mt-2.5 text-body text-ash">{{ __('lugares.pais.regiones_sub') }}</p>
                    <ul class="mt-4 columns-1 gap-5 sm:columns-2 lg:columns-3 [&>li]:break-inside-avoid">
                        @foreach ($provincias as $provincia)
                            <li>
                                <a href="{{ Idioma::url('provincia', [$pais->url, $provincia['slug']]) }}"
                                   class="-mx-3 flex items-baseline justify-between gap-3 rounded-card px-3 py-2
                                          text-ink no-underline transition hover:bg-cream hover:no-underline">
                                    <span>{{ $provincia['nombre'] }}</span>
                                    @if ($provincia['calles'])
                                        <span class="shrink-0 text-cap tabular-nums">{{ Idioma::numero($provincia['calles']) }}</span>
                                    @elseif ($provincia['ciudades'])
                                        <span class="shrink-0 text-cap tabular-nums text-ash">{{ Idioma::numero($provincia['ciudades']) }} {{ trans_choice('lugares.ciudades', $provincia['ciudades']) }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($destacadas->isNotEmpty())
                <section class="card">
                    {{--
                        "Best covered", no "Largest cities".

                        Iba ordenado por población, y para alguien que vino a
                        encontrar una dirección eso es el dato equivocado: una
                        ciudad con 8.014 calles le sirve y una más poblada con
                        3 no. Toronto tiene más gente que Calgary; Calgary
                        tiene la lista.
                    --}}
                    <h2 class="font-display text-head-sm">{{ __('lugares.pais.mejores_titulo') }}</h2>
                    <p class="mt-2.5 text-body text-ash">{{ __('lugares.pais.mejores_sub', ['pais' => $pais->nombre]) }}</p>
                    <ul class="mt-4 flex flex-col">
                        @foreach ($destacadas as $fila)
                            <li>
                                <a href="{{ Idioma::url('ciudad', [$pais->url, $fila->ciudad->provincia_slug, $fila->ciudad->segmento]) }}"
                                   class="-mx-3 flex items-baseline justify-between gap-3 rounded-card px-3 py-2
                                          text-ink no-underline transition hover:bg-cream hover:no-underline">
                                    <span>
                                        {{ $fila->ciudad->display_name }}
                                        @if ($fila->ciudad->provincia?->nombre)
                                            <span class="ml-1 text-cap text-ash">{{ $fila->ciudad->provincia->nombre }}</span>
                                        @endif
                                    </span>
                                    <span class="shrink-0 text-cap tabular-nums">
                                        {{ Idioma::numero($fila->calles) }} {{ trans_choice('lugares.calles', $fila->calles) }}
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        <aside>
            @if ($destacadas->isNotEmpty())
                {{-- La única acción periwinkle de la pantalla, y apunta a lo mejor
                     que el país tiene para ofrecer. --}}
                <div class="card">
                    <h2 class="eyebrow">{{ __('lugares.pais.cobertura_titulo') }}</h2>
                    <p class="mt-3.5 font-display text-head-sm">{{ $destacadas->first()->ciudad->display_name }}</p>
                    <p class="mt-2.5 text-body">
                        {{ __('lugares.pais.cobertura_calles', [
                            'calles' => Idioma::numero($destacadas->first()->calles),
                            'palabra' => trans_choice('lugares.calles', $destacadas->first()->calles),
                        ]) }}
                    </p>
                    <a href="{{ Idioma::url('ciudad', [$pais->url, $destacadas->first()->ciudad->provincia_slug, $destacadas->first()->ciudad->segmento]) }}"
                       class="btn-primary mt-5 no-underline hover:no-underline">
                        {{ __('lugares.pais.cobertura_abrir', ['ciudad' => $destacadas->first()->ciudad->display_name]) }}
                    </a>
                </div>
            @else
                <div class="card">
                    <h2 class="eyebrow">{{ __('lugares.pais.vacio_titulo') }}</h2>
                    <p class="mt-3.5 text-body text-ash">
                        {{ __('lugares.pais.vacio_texto', ['pais' => $pais->nombre]) }}
                    </p>
                    <a href="{{ route('home') }}" class="btn-primary mt-5 no-underline hover:no-underline">
                        {{ __('lugares.buscar_una_ciudad') }}
                    </a>
                </div>
            @endif
        </aside>
    </div>
    </div>
@endsection
