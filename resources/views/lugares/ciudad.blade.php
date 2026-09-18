@extends('layouts.app')

@section('title', $ciudad->display_name.', '.($ciudad->provincia?->nombre ? $ciudad->provincia->nombre.', ' : '').$ciudad->pais?->nombre.' — streets and address lookup')
@section('alineacion', 'items-start')

@section('content')
    <div class="w-full max-w-3xl my-8 space-y-6">

        <nav class="text-sm text-gray-500" aria-label="Breadcrumb">
            <a href="{{ route('home') }}" class="hover:underline">Worldwide</a>
            <span class="mx-1" aria-hidden="true">&rsaquo;</span>
            <a href="{{ route('pais', $ciudad->pais->url) }}" class="hover:underline">{{ $ciudad->pais->nombre }}</a>
            <span class="mx-1" aria-hidden="true">&rsaquo;</span>
            <a href="{{ route('provincia', [$ciudad->pais->url, $ciudad->provincia_slug]) }}" class="hover:underline">
                {{ $ciudad->provincia?->nombre ?: 'Unknown region' }}
            </a>
        </nav>

        <div class="bg-white rounded-2xl shadow-xl p-8 md:p-10">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900">{{ $ciudad->display_name }}</h1>
                @if (in_array($ciudad->fcode, ['PPLC', 'PPLA'], true))
                    <span class="text-xs bg-blue-100 text-blue-700 px-3 py-1 rounded-full shrink-0 mt-2">
                        {{ $ciudad->fcode === 'PPLC' ? 'Capital city' : 'Regional capital' }}
                    </span>
                @endif
            </div>

            <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6 text-sm">
                <div>
                    <dt class="text-gray-500">Country</dt>
                    <dd class="font-medium text-gray-800">{{ $ciudad->pais->nombre }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Region</dt>
                    <dd class="font-medium text-gray-800">{{ $ciudad->provincia?->nombre ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Population</dt>
                    <dd class="font-medium text-gray-800">{{ $ciudad->population ? number_format($ciudad->population) : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Coordinates</dt>
                    <dd class="font-medium text-gray-800">{{ number_format((float) $ciudad->centro_x, 3) }}, {{ number_format((float) $ciudad->centro_y, 3) }}</dd>
                </div>
            </dl>

            @if ($nombresLocales)
                {{-- Los nombres locales son la razón por la que alguien que no habla
                     inglés puede encontrar esta ciudad desde el buscador. --}}
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <h2 class="text-sm text-gray-500 mb-2">Also known as</h2>
                    <p class="text-gray-700">{{ implode(' · ', $nombresLocales) }}</p>
                </div>
            @endif

            <div class="w-full h-64 rounded-xl overflow-hidden border border-gray-200 mt-6">
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
                        class="w-full h-full" style="border:0" loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade" title="Map of {{ $ciudad->display_name }}"></iframe>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-xl p-8 md:p-10">
            <h2 class="text-2xl font-bold text-gray-900 mb-1">Streets in {{ $ciudad->display_name }}</h2>

                {{--
                    "Every street visitors have looked up here", no "most
                    searched": 1.017.913 de las 1.059.464 filas tienen el
                    contador en 0, así que hablar de popularidad sería describir
                    ruido. Por lo mismo no se muestra ningún contador.
                --}}
                <p class="text-gray-600 mb-6">
                    Every street visitors have looked up here, A to Z.
                    <span class="text-gray-400">({{ number_format($totalCalles) }})</span>
                </p>

                {{--
                    La barra de letras son anclas dentro de esta misma página, no
                    enlaces a otras URLs. Un `?letter=a` por ciudad serían 26 URLs
                    × 18.361 ciudades, justo lo que el sitio está tratando de
                    sacarse de encima.
                --}}
                <nav class="flex flex-wrap gap-1 mb-8 pb-6 border-b border-gray-200" aria-label="Jump to a letter">
                    @foreach (array_merge(['#'], range('A', 'Z')) as $letra)
                        @if ($indice->has($letra))
                            <a href="#letra-{{ $letra === '#' ? 'num' : Str::lower($letra) }}"
                               class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-semibold
                                      bg-blue-50 text-blue-700 hover:bg-blue-100 transition">{{ $letra }}</a>
                        @else
                            {{-- La letra sin calles queda a la vista pero apagada: dice
                                 que no hay nada, en vez de dejar el hueco sin explicar. --}}
                            <span class="w-8 h-8 flex items-center justify-center rounded-lg text-sm
                                         text-gray-300 select-none" aria-disabled="true">{{ $letra }}</span>
                        @endif
                    @endforeach
                </nav>

                <div class="space-y-8">
                    @foreach ($indice as $letra => $grupo)
                        <section id="letra-{{ $letra === '#' ? 'num' : Str::lower($letra) }}" class="scroll-mt-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-3 pb-1 border-b border-gray-100">
                                {{ $letra }}
                                <span class="text-sm font-normal text-gray-400">
                                    @if ($grupo['total'] > $grupo['calles']->count())
                                        {{ number_format($grupo['calles']->count()) }} of {{ number_format($grupo['total']) }}
                                    @else
                                        {{ number_format($grupo['total']) }}
                                    @endif
                                </span>
                            </h3>
                            {{-- Las clases de cada <li> van en el <ul>: repetidas por calle
                                 eran 73 KB de los 328 que pesaba la ficha de Waterloo. --}}
                            <ul class="columns-1 sm:columns-2 lg:columns-3 gap-6 text-gray-700
                                       [&>li]:break-inside-avoid [&>li]:py-0.5">
                                @foreach ($grupo['calles'] as $calle)
                                    <li>{{ $calle }}</li>
                                @endforeach
                            </ul>
                        </section>
                    @endforeach
                </div>

                @php($recortadas = $indice->contains(fn (array $g) => $g['total'] > $g['calles']->count()))
                @if ($recortadas)
                    <p class="text-sm text-gray-500 mt-8 pt-6 border-t border-gray-200">
                        Long letters show the first {{ number_format(\App\Models\Busqueda::CALLES_POR_LETRA) }}.
                        Use the search to find any other street in {{ $ciudad->display_name }}.
                    </p>                @endif
            <a href="{{ route('home') }}"
               class="inline-block mt-6 bg-blue-600 text-white font-semibold px-5 py-3 rounded-xl hover:bg-blue-700 transition duration-200">
                Look up a street in {{ $ciudad->display_name }}
            </a>
        </div>

    </div>
@endsection
