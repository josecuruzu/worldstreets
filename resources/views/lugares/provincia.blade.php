@extends('layouts.app')

@section('title', $nombreProvincia.', '.$pais->nombre.' — cities and streets')
@section('alineacion', 'items-start')

@section('content')
    <div class="w-full max-w-4xl my-8 space-y-6">

        <nav class="text-sm text-gray-500" aria-label="Breadcrumb">
            <a href="{{ route('home') }}" class="hover:underline">Worldwide</a>
            <span class="mx-1" aria-hidden="true">&rsaquo;</span>
            <a href="{{ route('pais', $pais->url) }}" class="hover:underline">{{ $pais->nombre }}</a>
            <span class="mx-1" aria-hidden="true">&rsaquo;</span>
            <span class="text-gray-800">{{ $nombreProvincia }}</span>
        </nav>

        <div class="bg-white rounded-2xl shadow-xl p-8 md:p-10">
            <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900">{{ $nombreProvincia }}</h1>
            <p class="text-gray-600 mt-2">
                @if ($provincia)
                    Cities in {{ $nombreProvincia }}, {{ $pais->nombre }}, largest first.
                @else
                    {{-- El cajón de las ciudades cuyo admin1 es el centinela `XX.00`. --}}
                    Cities in {{ $pais->nombre }} that are not assigned to any region, largest first.
                @endif
            </p>
        </div>

        <div class="bg-white rounded-2xl shadow-xl p-8 md:p-10">
            @if ($ciudades->isNotEmpty())
                <ul class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach ($ciudades as $ciudad)
                        <li>
                            <a href="{{ route('ciudad', [$pais->url, $slugProvincia, $ciudad->segmento]) }}"
                               class="flex justify-between items-baseline gap-3 py-2 px-3 rounded-lg hover:bg-gray-100 transition duration-200">
                                <span class="text-gray-800">{{ $ciudad->display_name }}</span>
                                <span class="text-xs text-gray-500 shrink-0">
                                    {{ $ciudad->population ? number_format($ciudad->population) : '' }}
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-8">
                    {{ $ciudades->links() }}
                </div>
            @else
                <p class="text-gray-600">No cities on record here.</p>
            @endif
        </div>

    </div>
@endsection
