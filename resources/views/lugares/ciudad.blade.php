@extends('layouts.app')

@section('title', $ciudad->display_name.', '.($ciudad->provincia?->nombre ? $ciudad->provincia->nombre.', ' : '').$ciudad->pais?->nombre.' — streets and address lookup')

@section('content')
    <div class="contenedor">
    <nav class="flex flex-wrap items-center gap-2 text-cap text-ash" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">Worldwide</a>
        <span class="text-lavender" aria-hidden="true">/</span>
        <a href="{{ route('pais', $ciudad->pais->url) }}">{{ $ciudad->pais->nombre }}</a>
        <span class="text-lavender" aria-hidden="true">/</span>
        <a href="{{ route('provincia', [$ciudad->pais->url, $ciudad->provincia_slug]) }}">
            {{ $ciudad->provincia?->nombre ?: 'Unknown region' }}
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
                {{ $ciudad->provincia?->nombre ?: 'Unknown region' }}, {{ $ciudad->pais->nombre }}
                @if ($ciudad->population)
                    {{-- La población va en tinta y no en Ash: es un dato, no una pista. --}}
                    <span class="text-lavender" aria-hidden="true">·</span> {{ number_format($ciudad->population) }} people
                @endif
            </p>
        </div>
        <p class="shrink-0 text-body text-ash">{{ number_format($totalCalles) }} streets on record</p>
    </div>

    {{--
        El mapa es la página, no una miniatura.

        Y el buscador va adentro de la misma tarjeta: hasta ahora la ficha solo
        tenía un botón que mandaba a la home a volver a tipear la ciudad, y es
        la página que recibe el tráfico de Google. Era el agujero más caro del
        sitio.
    --}}
    <section class="mt-7 overflow-hidden rounded-card border border-mist bg-paper">
        <form action="{{ route('home') }}" method="get"
              class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
            <label for="calle" class="sr-only">Find a street in {{ $ciudad->display_name }}</label>
            <input type="text" id="calle" name="street"
                   class="field grow bg-cream"
                   placeholder="Find a street in {{ $ciudad->display_name }}"
                   autocomplete="off">

            <label for="altura" class="sr-only">House number</label>
            <input type="text" id="altura" name="number"
                   class="field bg-cream sm:w-28"
                   placeholder="No." inputmode="numeric" autocomplete="off">

            <input type="hidden" name="city" value="{{ $ciudad->id }}">
            <button type="submit" class="btn-dark shrink-0">Search</button>
        </form>

        <div class="h-[320px] border-t border-mist sm:h-[520px]">
            @php
                $d = 0.06;
                $bbox = implode(',', [
                    round((float) $ciudad->centro_y - $d, 5),
                    round((float) $ciudad->centro_x - $d, 5),
                    round((float) $ciudad->centro_y + $d, 5),
                    round((float) $ciudad->centro_x + $d, 5),
                ]);
            @endphp
            <iframe src="https://www.openstreetmap.org/export/embed.html?bbox={{ $bbox }}&layer=mapnik&marker={{ (float) $ciudad->centro_x }},{{ (float) $ciudad->centro_y }}"
                    class="h-full w-full" style="border:0" loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    title="Map of {{ $ciudad->display_name }}"></iframe>
        </div>
    </section>

    <div class="mt-6 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">

        <section class="card">
            <h2 class="font-display text-head-sm">Streets in {{ $ciudad->display_name }}</h2>
            {{--
                "Every street visitors have looked up here", no "most searched":
                1.017.913 de las 1.059.464 filas tienen el contador en 0, así que
                hablar de popularidad sería describir ruido. Por lo mismo no se
                muestra ningún contador.
            --}}
            <p class="mt-2.5 text-body text-ash">Every street visitors have looked up here, A to Z.</p>

            {{--
                La barra de letras son anclas dentro de esta misma página, no
                enlaces a otras URLs. Un `?letter=a` por ciudad serían 26 URLs ×
                18.361 ciudades, justo lo que el sitio está sacándose de encima.
            --}}
            <nav class="mt-5 flex flex-wrap gap-1.5" aria-label="Jump to a letter">
                @foreach (array_merge(['#'], range('A', 'Z')) as $letra)
                    @php($ancla = $letra === '#' ? 'num' : Str::lower($letra))
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
                    @php($ancla = $letra === '#' ? 'num' : Str::lower($letra))
                    <section id="letra-{{ $ancla }}" class="scroll-mt-4">
                        <h3 class="flex items-baseline gap-2 border-b border-mist pb-1.5 font-display text-[22px] leading-[1.08]">
                            {{ $letra }}
                            <span class="font-sans text-cap font-normal text-ash">
                                @if ($grupo['total'] > $grupo['calles']->count())
                                    {{ number_format($grupo['calles']->count()) }} of {{ number_format($grupo['total']) }}
                                @else
                                    {{ number_format($grupo['total']) }}
                                @endif
                            </span>
                        </h3>
                        {{-- Las clases de cada <li> van en el <ul>: repetidas por
                             calle eran 73 KB de los 328 que pesaba Waterloo. --}}
                        <ul class="mt-2 columns-1 gap-6 text-body sm:columns-2 lg:columns-3
                                   [&>li]:break-inside-avoid [&>li]:py-1">
                            @foreach ($grupo['calles'] as $calle)
                                <li>{{ $calle }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>

            @php($recortadas = $indice->contains(fn (array $g) => $g['total'] > $g['calles']->count()))
            @if ($recortadas)
                <p class="mt-7 border-t border-mist pt-5 text-cap text-ash">
                    Long letters show the first {{ number_format(\App\Models\Busqueda::CALLES_POR_LETRA) }}.
                    Use the search above to find any other street in {{ $ciudad->display_name }}.
                </p>
            @endif
        </section>

        <aside>
            <div class="card">
                <h2 class="eyebrow">About this place</h2>
                <dl class="mt-4 flex flex-col gap-4">
                    <div>
                        <dt class="text-cap text-ash">Country</dt>
                        <dd class="mt-0.5 font-label">
                            <a href="{{ route('pais', $ciudad->pais->url) }}">{{ $ciudad->pais->nombre }}</a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-cap text-ash">Region</dt>
                        <dd class="mt-0.5 font-label">
                            <a href="{{ route('provincia', [$ciudad->pais->url, $ciudad->provincia_slug]) }}">
                                {{ $ciudad->provincia?->nombre ?: 'Unknown region' }}
                            </a>
                        </dd>
                    </div>
                    @if ($ciudad->population)
                        <div>
                            <dt class="text-cap text-ash">Population</dt>
                            <dd class="mt-0.5 font-label tabular-nums">{{ number_format($ciudad->population) }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-cap text-ash">Coordinates</dt>
                        <dd class="mt-0.5 font-label tabular-nums">
                            {{ number_format((float) $ciudad->centro_x, 3) }}, {{ number_format((float) $ciudad->centro_y, 3) }}
                        </dd>
                    </div>
                </dl>

                @if ($nombresLocales)
                    {{-- Los nombres locales son la razón por la que alguien que no
                         habla inglés puede encontrar esta ciudad desde el buscador. --}}
                    <div class="mt-5 border-t border-mist pt-5">
                        <h3 class="eyebrow">Also known as</h3>
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
            <h2 class="eyebrow">More in {{ $ciudad->provincia?->nombre ?: 'this region' }}</h2>
            <div class="mt-4 flex flex-wrap items-center gap-2">
                @foreach ($vecinas as $vecina)
                    <a href="{{ route('ciudad', [$ciudad->pais->url, $ciudad->provincia_slug, $vecina->segmento]) }}"
                       class="az w-auto border-lavender px-4 no-underline hover:no-underline">{{ $vecina->display_name }}</a>
                @endforeach

                {{-- La única acción periwinkle de la pantalla. --}}
                <a href="{{ route('provincia', [$ciudad->pais->url, $ciudad->provincia_slug]) }}"
                   class="btn-primary px-5 py-3 no-underline hover:no-underline">
                    All cities in {{ $ciudad->provincia?->nombre ?: 'this region' }}
                </a>
            </div>
        </section>
    @endif
    </div>
@endsection
