@extends('layouts.app')

@use('App\Support\Idioma')

{{-- La misma frase. El Cake usa "in the State of {x}" acá
     (`ProvinciasController.php`, línea 39); se deja "in {x}, {país}" porque
     "State of Alberta" es incorrecto —es una provincia— y el país en el título
     es lo que desambigua un "Ontario" de otro. La frase que rankea queda
     intacta.

     En español el título es otro y no una traducción: ver el encabezado de
     `lang/es/lugares.php`. Lo que la gente escribe es "calles de {lugar}" y
     "direcciones de {lugar}", no el equivalente de "reverse address lookup". --}}
@section('title', __('lugares.provincia.titulo', ['provincia' => $nombreProvincia, 'pais' => $pais->nombre]))

@section('description', $ciudades->isNotEmpty()
    ? __('lugares.provincia.descripcion_con_ciudades', ['provincia' => $nombreProvincia, 'pais' => $pais->nombre])
    : __('lugares.provincia.descripcion_sin_ciudades', ['provincia' => $nombreProvincia, 'pais' => $pais->nombre]))

@section('content')
    <div class="contenedor">
    <nav class="flex flex-wrap items-center gap-2 text-cap text-ash" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">{{ __('lugares.inicio') }}</a>
        <span class="text-lavender" aria-hidden="true">/</span>
        <a href="{{ Idioma::url('pais', $pais->url) }}">{{ $pais->nombre }}</a>
        <span class="text-lavender" aria-hidden="true">/</span>
        <span class="text-ink" aria-current="page">{{ $nombreProvincia }}</span>
    </nav>

    <header class="mt-5">
        <h1 class="font-display text-[34px] leading-[1.08] sm:text-head">{{ $nombreProvincia }}</h1>
        <p class="mt-3.5 text-sub">
            @if ($provincia)
                {{ $pais->nombre }}
                <span class="text-lavender" aria-hidden="true">·</span>
                {{ __('lugares.provincia.sub') }}
            @else
                {{-- El cajón de las 56.761 ciudades cuyo admin1 es el centinela
                     `XX.00` de GeoNames, que no existe como fila en provincias. --}}
                {{ __('lugares.provincia.sub_sin_region', ['pais' => $pais->nombre]) }}
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
                        <a href="{{ Idioma::url('ciudad', [$pais->url, $slugProvincia, $ciudad->segmento]) }}"
                           class="-mx-3 flex items-baseline justify-between gap-3 rounded-card px-3 py-2
                                  text-ink no-underline transition hover:bg-cream hover:no-underline">
                            <span>{{ $ciudad->display_name }}</span>
                            @if ($ciudad->population)
                                <span class="shrink-0 text-cap tabular-nums">{{ Idioma::numero($ciudad->population) }}</span>
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
                {{ __('lugares.provincia.vacio', ['provincia' => $nombreProvincia]) }}
            </p>
            <a href="{{ route('home') }}" class="btn-primary mt-5 no-underline hover:no-underline">
                {{ __('lugares.buscar_una_ciudad') }}
            </a>
        @endif
    </div>
    </div>
@endsection
