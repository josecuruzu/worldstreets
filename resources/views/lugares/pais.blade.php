@extends('layouts.app')

@section('title', $pais->nombre.' — regions, cities and streets')

@section('content')
    <div class="contenedor">
    <nav class="flex flex-wrap items-center gap-2 text-cap text-ash" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">Worldwide</a>
        <span class="text-lavender" aria-hidden="true">/</span>
        <span class="text-ink" aria-current="page">{{ $pais->nombre }}</span>
    </nav>

    <header class="mt-5">
        <h1 class="font-display text-[34px] leading-[1.08] sm:text-head">{{ $pais->nombre }}</h1>
        <p class="mt-4 max-w-[720px] text-sub [text-wrap:pretty]">
            Pick a region, or search a city directly. Every city with a street list has its own page,
            with the map and the streets people have looked up there.
        </p>
    </header>

    {{--
        Tres cifras como banda editorial, no como tarjetas de estadística: el
        sistema carga la jerarquía con color y peso, no con superficies.

        Y son las cifras que importan —ciudades CON lista de calles, no las del
        dataset—: decir "13.247 ciudades" cuando 48 tienen calles promete lo que
        no hay.
    --}}
    <div class="mt-9 flex flex-wrap items-baseline gap-x-14 gap-y-7 border-y border-mist py-7">
        <div>
            <p class="font-display text-[34px] leading-[1.08] tabular-nums sm:text-head">{{ number_format($ciudadesConCalles) }}</p>
            <p class="mt-2 text-cap">{{ Str::plural('City', $ciudadesConCalles) }} with a street list</p>
        </div>
        <div>
            <p class="font-display text-[34px] leading-[1.08] tabular-nums sm:text-head">{{ number_format($callesDelPais) }}</p>
            <p class="mt-2 text-cap">{{ Str::plural('Street', $callesDelPais) }} on record</p>
        </div>
        <div>
            <p class="font-display text-[34px] leading-[1.08] tabular-nums sm:text-head">{{ number_format(count($provincias)) }}</p>
            <p class="mt-2 text-cap">{{ Str::plural('Region', count($provincias)) }}</p>
        </div>
    </div>

    <div class="mt-10 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">

        <div class="flex flex-col gap-6">
            @if ($provincias)
                <section class="card">
                    <h2 class="font-display text-head-sm">Regions</h2>
                    <ul class="mt-4 columns-1 gap-5 sm:columns-2 lg:columns-3 [&>li]:break-inside-avoid">
                        @foreach ($provincias as $provincia)
                            <li>
                                <a href="{{ route('provincia', [$pais->url, $provincia['slug']]) }}"
                                   class="-mx-3 flex items-baseline justify-between gap-3 rounded-card px-3 py-2
                                          text-ink no-underline transition hover:bg-cream hover:no-underline">
                                    <span>{{ $provincia['nombre'] }}</span>
                                    @if ($provincia['ciudades'])
                                        <span class="shrink-0 text-cap tabular-nums">{{ number_format($provincia['ciudades']) }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($destacadas->isNotEmpty())
                <section class="card">
                    <h2 class="font-display text-head-sm">Largest cities</h2>
                    <ul class="mt-4 flex flex-col">
                        @foreach ($destacadas as $ciudad)
                            <li>
                                <a href="{{ route('ciudad', [$pais->url, $ciudad->provincia_slug, $ciudad->segmento]) }}"
                                   class="-mx-3 flex items-baseline justify-between gap-3 rounded-card px-3 py-2
                                          text-ink no-underline transition hover:bg-cream hover:no-underline">
                                    <span>
                                        {{ $ciudad->display_name }}
                                        @if (in_array($ciudad->fcode, ['PPLC', 'PPLA'], true))
                                            <span class="ml-1 text-cap text-ash">{{ $ciudad->fcode === 'PPLC' ? 'capital' : 'regional capital' }}</span>
                                        @endif
                                    </span>
                                    @if ($ciudad->population)
                                        <span class="shrink-0 text-cap tabular-nums">{{ number_format($ciudad->population) }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        <aside>
            @if ($mejorCubierta?->ciudad)
                {{-- La única acción periwinkle de la pantalla, y apunta a lo mejor
                     que el país tiene para ofrecer. --}}
                <div class="card">
                    <h2 class="eyebrow">Deepest coverage here</h2>
                    <p class="mt-3.5 font-display text-head-sm">{{ $mejorCubierta->ciudad->display_name }}</p>
                    <p class="mt-2.5 text-body">
                        {{ number_format($mejorCubierta->calles) }}
                        {{ Str::plural('street', $mejorCubierta->calles) }} on record.
                    </p>
                    <a href="{{ route('ciudad', [$pais->url, $mejorCubierta->ciudad->provincia_slug, $mejorCubierta->ciudad->segmento]) }}"
                       class="btn-primary mt-5 no-underline hover:no-underline">
                        Open {{ $mejorCubierta->ciudad->display_name }}
                    </a>
                </div>
            @else
                <div class="card">
                    <h2 class="eyebrow">No streets yet</h2>
                    <p class="mt-3.5 text-body text-ash">
                        No city in {{ $pais->nombre }} has a street list yet. The lists are written by
                        the people who look streets up.
                    </p>
                    <a href="{{ route('home') }}" class="btn-primary mt-5 no-underline hover:no-underline">
                        Search a city
                    </a>
                </div>
            @endif
        </aside>
    </div>
    </div>
@endsection
