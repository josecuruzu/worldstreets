@extends('layouts.app')

@section('title', $pais->nombre.' — regions, cities and streets')
@section('alineacion', 'items-start')

@section('content')
    <div class="w-full max-w-4xl my-8 space-y-6">

        <nav class="text-sm text-gray-500" aria-label="Breadcrumb">
            <a href="{{ route('home') }}" class="hover:underline">Worldwide</a>
            <span class="mx-1" aria-hidden="true">&rsaquo;</span>
            <span class="text-gray-800">{{ $pais->nombre }}</span>
        </nav>

        <div class="bg-white rounded-2xl shadow-xl p-8 md:p-10">
            <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900">{{ $pais->nombre }}</h1>

            <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6 text-sm">
                <div>
                    <dt class="text-gray-500">Capital</dt>
                    <dd class="font-medium text-gray-800">{{ $pais->capital ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Population</dt>
                    <dd class="font-medium text-gray-800">{{ $pais->population ? number_format($pais->population) : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Continent</dt>
                    <dd class="font-medium text-gray-800">{{ $pais->continente?->nombre ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Regions</dt>
                    <dd class="font-medium text-gray-800">{{ number_format(count($provincias)) }}</dd>
                </div>
            </dl>
        </div>

        @if ($destacadas->isNotEmpty())
            <div class="bg-white rounded-2xl shadow-xl p-8 md:p-10">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">Largest cities</h2>
                <ul class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach ($destacadas as $ciudad)
                        <li>
                            <a href="{{ route('ciudad', [$pais->url, $ciudad->provincia_slug, $ciudad->segmento]) }}"
                               class="flex justify-between items-baseline gap-3 p-3 rounded-xl border border-gray-200 hover:bg-gray-50 transition duration-200">
                                <span class="font-medium text-gray-800">{{ $ciudad->display_name }}</span>
                                <span class="text-xs text-gray-500 shrink-0">
                                    {{ $ciudad->population ? number_format($ciudad->population) : '—' }}
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-xl p-8 md:p-10">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Regions of {{ $pais->nombre }}</h2>

            @if ($provincias)
                <ul class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                    @foreach ($provincias as $provincia)
                        <li>
                            <a href="{{ route('provincia', [$pais->url, $provincia['slug']]) }}"
                               class="flex justify-between items-baseline gap-2 py-2 px-3 rounded-lg hover:bg-gray-100 transition duration-200">
                                <span class="text-gray-800">{{ $provincia['nombre'] }}</span>
                                <span class="text-xs text-gray-500 shrink-0">{{ number_format($provincia['ciudades']) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-gray-600">This country has no regions on record.</p>
            @endif
        </div>

    </div>
@endsection
