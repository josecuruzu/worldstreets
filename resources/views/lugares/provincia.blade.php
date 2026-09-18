@extends('layouts.app')

@section('title', $nombreProvincia.', '.$pais->nombre.' — cities and streets')

@section('content')
    <div class="contenedor">
    <nav class="flex flex-wrap items-center gap-2 text-cap text-ash" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">Worldwide</a>
        <span class="text-lavender" aria-hidden="true">/</span>
        <a href="{{ route('pais', $pais->url) }}">{{ $pais->nombre }}</a>
        <span class="text-lavender" aria-hidden="true">/</span>
        <span class="text-ink" aria-current="page">{{ $nombreProvincia }}</span>
    </nav>

    <header class="mt-5">
        <h1 class="font-display text-[34px] leading-[1.08] sm:text-head">{{ $nombreProvincia }}</h1>
        <p class="mt-3.5 text-sub">
            @if ($provincia)
                {{ $pais->nombre }}
                <span class="text-lavender" aria-hidden="true">·</span>
                cities with a street list, largest first
            @else
                {{-- El cajón de las 56.761 ciudades cuyo admin1 es el centinela
                     `XX.00` de GeoNames, que no existe como fila en provincias. --}}
                Cities in {{ $pais->nombre }} that GeoNames does not assign to any region
            @endif
        </p>
    </header>

    <div class="card mt-7">
        @if ($ciudades->isNotEmpty())
            {{--
                Tres columnas con la población a la derecha de cada fila. La
                población va en tinta y no en Ash: es un dato, y Ash es el color
                de las pistas.
            --}}
            <ul class="columns-1 gap-5 sm:columns-2 lg:columns-3 [&>li]:break-inside-avoid">
                @foreach ($ciudades as $ciudad)
                    <li>
                        <a href="{{ route('ciudad', [$pais->url, $slugProvincia, $ciudad->segmento]) }}"
                           class="-mx-3 flex items-baseline justify-between gap-3 rounded-card px-3 py-2
                                  text-ink no-underline transition hover:bg-cream hover:no-underline">
                            <span>{{ $ciudad->display_name }}</span>
                            @if ($ciudad->population)
                                <span class="shrink-0 text-cap tabular-nums">{{ number_format($ciudad->population) }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>

            <div class="mt-7 border-t border-mist pt-6">
                {{ $ciudades->links() }}
            </div>
        @else
            {{--
                El caso normal, no la excepción: una región puede tener miles de
                ciudades en el dataset y ninguna con calles todavía. La página
                sigue existiendo porque es parte de la jerarquía navegable.
            --}}
            <p class="text-body text-ash">
                No city in {{ $nombreProvincia }} has a street list yet. The lists are written by the
                people who look streets up &mdash; search a city and you start one.
            </p>
            <a href="{{ route('home') }}" class="btn-primary mt-5 no-underline hover:no-underline">
                Search a city
            </a>
        @endif
    </div>
    </div>
@endsection
