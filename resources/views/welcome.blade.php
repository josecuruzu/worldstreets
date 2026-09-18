@extends('layouts.app')

{{-- "Reverse address lookup" es el 73% de los clics del sitio. La frase se
     queda en el <title> tal cual, aunque describa lo contrario de lo que el
     producto hace: lo que conviene es que la página entregue algo real para
     esa consulta, no cambiarle el nombre. --}}
@section('title', 'Free Address Finder & Reverse Address Lookup')

@section('content')
    <div x-data="addressFinderApp()">

        {{--
            El hero. El wash atmosférico va detrás del bloque centrado y se
            desvanece hacia el crema, que es lo que hace que la página arranque
            con aire en vez de con una caja.
        --}}
        {{-- Full-bleed sin trucos: el <main> ya no limita el ancho, así que el
             wash llega a los bordes solo y el contenido de adentro se alinea
             con el resto de la página por su propio .contenedor. --}}
        <section class="pb-14 pt-6 text-center"
                 style="
                    background:
                    radial-gradient(1100px 460px at 18% 6%, rgba(255,255,255,.9), rgba(255,255,255,0) 62%),
                    radial-gradient(880px 400px at 80% 20%, rgba(206,222,197,.85), rgba(206,222,197,0) 66%),
                    radial-gradient(1200px 560px at 50% 100%, #faf2ec, rgba(250,242,236,0) 72%),
                    linear-gradient(172deg, #ccdbc3 0%, #e2e8d9 40%, #faf2ec 80%);">
            <div class="contenedor">

            <h1 class="mx-auto font-display text-[44px] leading-[1.08] [text-wrap:balance] sm:text-[64px] lg:text-display">
                Find any street on earth.
            </h1>

            <p class="mx-auto mt-6 max-w-[720px] text-sub [text-wrap:pretty]">
                Start with the city, then the street. {{ number_format($ciudades) }} cities across
                {{ $paises }} countries, and a street list written by the people who look them up.
            </p>

            {{-- Un solo campo a la vista: el de la ciudad. El de la calle
                 aparece recién cuando hay una ciudad elegida, porque hasta
                 entonces no hay nada donde buscarla. --}}
            <div class="relative mx-auto mt-10 max-w-[720px] text-left">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <label for="ciudad" class="sr-only">City</label>
                    <div class="relative grow">
                        <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2" viewBox="0 0 24 24"
                             fill="none" stroke="#8f8f8f" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                            <circle cx="11" cy="11" r="7"></circle><path d="M16.2 16.2 21 21"></path>
                        </svg>
                        <input id="ciudad" x-model="ciudadQuery" x-on:input="buscarCiudad()"
                               x-on:click.outside="mostrarSugerencias = false"
                               type="text" autocomplete="off"
                               {{-- Cape Town y no una ciudad argentina: es la de mejor
                                    cobertura del dataset, con 21.910 calles limpias. --}}
                               placeholder="Start with a city — Tokyo, Córdoba, Cape Town…"
                               class="field py-3.5 pl-12">
                    </div>
                    <button type="button" x-on:click="buscarCiudad()" class="btn-dark shrink-0">Search</button>
                </div>

                <div x-show="mostrarSugerencias && sugerencias.length" x-transition x-cloak
                     class="absolute z-10 mt-2 w-full overflow-hidden rounded-card border border-mist bg-paper shadow-card">
                    <template x-for="c in sugerencias" :key="c.id">
                        <button type="button" x-on:click="elegirCiudad(c)"
                                class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition hover:bg-cream">
                            <span>
                                <span class="font-label" x-text="c.nombre"></span>
                                <span class="text-cap text-ash" x-text="ubicacion(c.provincia, c.pais)"></span>
                            </span>
                            <span x-show="c.capital" class="tag shrink-0">capital</span>
                        </button>
                    </template>
                </div>

                <p x-show="ciudadQuery.length >= 2 && !mostrarSugerencias && !hayCiudad && !cargando" x-cloak
                   class="mt-2 text-cap text-ash">No cities matched that name.</p>
            </div>

            {{-- Las tres tarjetas blancas flotando sobre el crema. --}}
            <div class="mx-auto mt-10 grid max-w-[720px] gap-4 text-left sm:grid-cols-3">
                <div class="card p-5">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="#4b72f3" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                        <path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z"></path><circle cx="12" cy="10" r="2.6"></circle>
                    </svg>
                    <p class="mt-3.5 font-label">Start from a city</p>
                    <p class="mt-1 text-cap text-ash">Type it and pick from the list</p>
                </div>
                <div class="card p-5">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="#2b3ea7" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="9"></circle><path d="M3.6 9h16.8M3.6 15h16.8"></path>
                        <path d="M12 3c-2.6 2.6-2.6 15 0 18 2.6-3 2.6-15.4 0-18z"></path>
                    </svg>
                    <p class="mt-3.5 font-label">Start from a country</p>
                    <p class="mt-1 text-cap text-ash">{{ $paises }} of them, region by region</p>
                </div>
                <div class="card p-5">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="#1b1917" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                        <path d="M4 6h10M4 12h16M4 18h7"></path>
                    </svg>
                    <p class="mt-3.5 font-label">Browse the A–Z</p>
                    <p class="mt-1 text-cap text-ash">Every street we hold, alphabetically</p>
                </div>
            </div>
            </div>
        </section>

        {{-- Todo el bloque de búsqueda: la calle, el resultado y el mapa. --}}
        <section x-show="hayCiudad" x-cloak x-transition class="contenedor mt-10">

            <div class="flex flex-wrap items-baseline justify-between gap-4">
                <h2 class="font-display text-head-sm">
                    Streets in <span x-text="ciudad.nombre"></span>
                </h2>
                {{-- Puerta de entrada a la ficha, que es la versión de esta ciudad
                     que se puede enlazar, compartir e indexar. Si la ciudad no
                     tiene calles no tiene ficha, y `ruta` llega en null. --}}
                <a x-show="ciudad && ciudad.ruta" :href="ciudad.ruta" x-cloak class="text-cap">
                    Full page for <span x-text="ciudad.nombre"></span> &rarr;
                </a>
            </div>

            <div class="mt-5 overflow-hidden rounded-card border border-mist bg-paper">
                <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                    <label for="calle" class="sr-only">Street</label>
                    <input id="calle" x-model="calleQuery" x-on:input="buscarCalle()"
                           type="text" autocomplete="off" placeholder="Street name…"
                           class="field grow bg-cream py-3.5">
                    <label for="altura" class="sr-only">House number</label>
                    <input id="altura" x-model="altura" type="text" inputmode="numeric" placeholder="No."
                           class="field bg-cream py-3.5 sm:w-24">
                    <button type="button" x-on:click="limpiar()" class="btn-ghost shrink-0">Change city</button>
                </div>

                {{-- El mapa es la página, no una miniatura de 256px. --}}
                <div class="h-[320px] border-t border-mist sm:h-[460px]">
                    <iframe :src="mapaUrl" class="h-full w-full" style="border:0" loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade" title="Map"></iframe>
                </div>
            </div>

            <p x-show="error" x-cloak x-text="error"
               class="mt-4 rounded-card border border-mist bg-paper p-4 text-cap text-ink"></p>

            <div class="mt-6 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                <div class="card">
                    <template x-if="calleElegida">
                        <div class="mb-5 border-b border-mist pb-5">
                            <p class="font-display text-head-sm" x-text="direccionCompleta"></p>
                            <a :href="osmUrl" target="_blank" rel="noopener noreferrer" class="mt-2 inline-block text-cap">
                                Open in OpenStreetMap &rarr;
                            </a>
                        </div>
                    </template>

                    <template x-if="cargando">
                        <p class="text-body text-ash">Searching…</p>
                    </template>

                    <template x-if="!cargando && calles.length">
                        <div>
                            {{-- Sin contadores: 1.017.913 de las 1.059.464 filas
                                 tienen el contador en 0, así que un "1 search" al
                                 lado de casi cada calle describe el ruido. --}}
                            <h3 class="eyebrow" x-text="calleQueryAplicada ? 'Matching streets' : 'Streets on record'"></h3>
                            <ul class="mt-3 divide-y divide-mist">
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

                    <template x-if="!cargando && !calles.length && calleQueryAplicada">
                        <p class="text-body text-ash">
                            No street matched &ldquo;<span x-text="calleQueryAplicada"></span>&rdquo; in
                            <span x-text="ciudad.nombre"></span>.
                        </p>
                    </template>

                    {{-- El estado vacío es el caso normal, no la excepción: solo
                         18.361 de 4.697.141 ciudades tienen alguna calle. --}}
                    <template x-if="!cargando && !calles.length && !calleQueryAplicada">
                        <p class="text-body text-ash">
                            No streets have been looked up in <span x-text="ciudad.nombre"></span> yet.
                            This list is built from what visitors search &mdash; type a street above and you start it.
                        </p>
                    </template>
                </div>

                <aside class="card">
                    <h3 class="eyebrow">About this search</h3>
                    <p class="mt-3 text-cap text-ash">
                        Nothing here is tied to you. We keep an anonymous count of how often each
                        street is looked up, and that count is what writes the list.
                    </p>
                </aside>
            </div>
        </section>

        {{--
            La franja de cobertura. Reemplaza al "Why Use Our Address Finder"
            genérico: decir dónde hay calles de verdad le sirve más al visitante
            que tres tarjetas explicando que el sitio es gratis.
        --}}
        @if ($cobertura->isNotEmpty())
            <section x-show="!hayCiudad" class="contenedor py-14">
                <h2 class="eyebrow text-center">Deepest coverage today</h2>
                <div class="mt-7 flex flex-wrap items-baseline justify-center gap-x-14 gap-y-6">
                    @foreach ($cobertura as $pais)
                        <a href="{{ route('pais', $pais->url) }}" class="text-center no-underline hover:no-underline">
                            <p class="font-display text-head-sm text-ink underline-offset-4 hover:underline">{{ $pais->nombre }}</p>
                            <p class="mt-1.5 text-cap tabular-nums text-ink">{{ number_format($pais->calles) }} streets</p>
                        </a>
                    @endforeach
                </div>
            </section>

            <div class="contenedor"><div class="h-px bg-mist"></div></div>
        @endif

        {{-- La única acción periwinkle de la pantalla. --}}
        <section x-show="!hayCiudad" class="contenedor grid items-center gap-14 py-14 lg:grid-cols-2">
            <div class="order-2 lg:order-1">
                {{-- El marco de navegador inclinado: "pinned to a notebook", no
                     una captura de producto en una grilla. --}}
                <div class="rotate-[-4deg] overflow-hidden rounded-card bg-paper shadow-card">
                    <div class="flex h-[34px] items-center gap-1.5 border-b border-mist px-3.5">
                        <span class="h-2.5 w-2.5 rounded-full bg-mist"></span>
                        <span class="h-2.5 w-2.5 rounded-full bg-mist"></span>
                        <span class="h-2.5 w-2.5 rounded-full bg-mist"></span>
                        <span class="ml-3 text-[12px] leading-none text-ash">worldstreets.global/canada/ontario</span>
                    </div>
                    <div class="space-y-2 bg-cream p-5">
                        @foreach (['Albert Street', 'Bridgeport Road', 'Caroline Street', 'David Street'] as $ejemplo)
                            <div class="rounded-card bg-paper px-3 py-2 text-cap">{{ $ejemplo }}</div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="order-1 lg:order-2">
                <p class="eyebrow">The directory</p>
                <h2 class="mt-4 font-display text-[34px] leading-[1.08] [text-wrap:balance] sm:text-head">
                    Country, region, city — all the way down.
                </h2>
                <p class="mt-5 text-sub [text-wrap:pretty]">
                    Every place has its own page, with the map, the local names and the streets people
                    have looked up there. {{ number_format($conCalles) }} cities already carry a street
                    list; the rest are waiting for their first search.
                </p>
                @if ($cobertura->isNotEmpty())
                    <a href="{{ route('pais', $cobertura->first()->url) }}"
                       class="btn-primary mt-7 no-underline hover:no-underline">
                        Start with {{ $cobertura->first()->nombre }}
                    </a>
                @endif
            </div>
        </section>
    </div>
@endsection
