@extends('layouts.app')

@section('title', 'Free Address Finder & Reverse Address Lookup')

@section('content')
    <div x-data="addressFinderApp()" class="bg-white rounded-2xl shadow-xl w-full max-w-2xl p-8 md:p-12 text-center my-8">

        <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900 mb-2">
            Address Finder for Argentina
        </h1>
        <p class="text-gray-600 text-lg mb-8">
            Search any street in <strong class="font-bold text-gray-800">6,921 cities</strong> across Argentina. Our tool is the fastest, free, and <strong class="font-bold text-gray-800">completely anonymous</strong> way to perform a reverse address lookup.
        </p>

        <div class="ad-placeholder">
            Anuncio adaptable (970x250, 728x90, 320x100)
        </div>

        {{-- Paso 1: ciudad --}}
        <div class="w-full relative text-left">
            <label for="ciudad" class="block text-sm font-semibold text-gray-700 mb-1">1. City</label>
            <input id="ciudad" x-model="ciudadQuery" x-on:input="buscarCiudad()"
                   x-on:click.outside="mostrarSugerencias = false"
                   type="text" autocomplete="off" placeholder="Start typing a city, e.g. Rosario…"
                   class="w-full p-4 text-gray-700 bg-gray-100 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 transition duration-300">

            <div x-show="mostrarSugerencias && sugerencias.length" x-transition x-cloak
                 class="absolute w-full mt-2 bg-white border border-gray-300 rounded-xl shadow-lg z-10 overflow-hidden">
                <template x-for="c in sugerencias" :key="c.id">
                    <div x-on:click="elegirCiudad(c)"
                         class="p-3 cursor-pointer hover:bg-gray-100 transition duration-200 flex justify-between items-center gap-3">
                        <span>
                            <span class="font-medium text-gray-800" x-text="c.nombre"></span>
                            <span class="text-sm text-gray-500" x-text="`${c.provincia}, ${c.pais}`"></span>
                        </span>
                        <span x-show="c.capital"
                              class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full shrink-0">capital</span>
                    </div>
                </template>
            </div>

            <p x-show="ciudadQuery.length >= 2 && !mostrarSugerencias && !hayCiudad && !cargando" x-cloak
               class="text-sm text-gray-500 mt-2">No cities matched that name.</p>
        </div>

        {{-- Paso 2: calle dentro de la ciudad --}}
        <div x-show="hayCiudad" x-transition x-cloak class="w-full mt-6 text-left">
            <div class="flex items-baseline justify-between mb-1">
                <label for="calle" class="block text-sm font-semibold text-gray-700">2. Street</label>
                <button x-on:click="limpiar()" class="text-sm text-blue-600 hover:underline">Change city</button>
            </div>

            <div class="flex gap-3">
                <input id="calle" x-model="calleQuery" x-on:input="buscarCalle()"
                       type="text" autocomplete="off" placeholder="Street name…"
                       class="flex-1 min-w-0 p-4 text-gray-700 bg-gray-100 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 transition duration-300">
                <input x-model="altura" type="text" inputmode="numeric" placeholder="No."
                       class="w-24 p-4 text-gray-700 bg-gray-100 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 transition duration-300">
            </div>

            <p class="text-sm text-gray-500 mt-2">
                <span x-text="totalCalles.toLocaleString()"></span> streets on record for
                <span class="font-medium" x-text="ciudad.nombre"></span>.
                <span x-show="!calleQueryAplicada">Showing the most searched ones.</span>
            </p>
        </div>

        <p x-show="error" x-cloak x-text="error"
           class="mt-4 text-left text-sm text-red-600 bg-red-50 border border-red-200 rounded-xl p-3"></p>

        {{-- Resultados --}}
        <div x-show="hayCiudad" x-cloak class="w-full mt-8 text-left" x-transition>
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Search Results</h2>

            <div class="bg-gray-50 p-6 rounded-xl border border-gray-200">
                <div x-show="cargando" class="flex flex-col items-center justify-center py-8">
                    <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="text-lg text-gray-600 mt-4">Searching…</p>
                </div>

                <div x-show="!cargando">
                    <template x-if="calleElegida">
                        <div class="mb-6 pb-6 border-b border-gray-200">
                            <div class="flex items-start gap-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-green-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <div>
                                    <p class="text-lg font-bold text-gray-800" x-text="direccionCompleta"></p>
                                    <a :href="osmUrl" target="_blank" rel="noopener noreferrer"
                                       class="text-sm text-blue-600 hover:underline">Open in OpenStreetMap →</a>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template x-if="calles.length">
                        <div>
                            <h3 class="font-semibold text-gray-800 mb-3"
                                x-text="calleQueryAplicada ? 'Matching streets' : 'Most searched streets'"></h3>
                            <ul class="divide-y divide-gray-200">
                                <template x-for="c in calles" :key="c.nombre">
                                    <li x-on:click="elegirCalle(c.nombre)"
                                        class="py-2 flex justify-between items-center gap-3 cursor-pointer hover:bg-gray-100 rounded px-2 -mx-2">
                                        <span class="text-gray-700" x-text="c.nombre"></span>
                                        <span class="text-xs text-gray-500 shrink-0"
                                              x-text="`${c.busquedas.toLocaleString()} searches`"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </template>

                    <template x-if="!calles.length && calleQueryAplicada">
                        <p class="text-gray-600">No street matched "<span x-text="calleQueryAplicada"></span>" in <span x-text="ciudad.nombre"></span>.</p>
                    </template>

                    <div class="w-full h-64 rounded-xl overflow-hidden border border-gray-200 mt-6">
                        <iframe :src="mapaUrl" class="w-full h-full" style="border:0" loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade" title="Map"></iframe>
                    </div>
                </div>
            </div>

            <div class="ad-placeholder">
                Anuncio de contenido (300x250)
            </div>
        </div>

        <div class="mt-12 text-left">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">
                Why Use Our Address Finder?
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <div class="flex flex-col items-center bg-gray-50 p-6 rounded-xl border border-gray-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-blue-600 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    <h3 class="font-semibold text-gray-800 text-lg">Your Privacy, Our Priority</h3>
                    <p class="text-sm text-gray-600 mt-1 text-center">We do not request personal data or save your search history. Your information is secure and private.</p>
                </div>

                <div class="flex flex-col items-center bg-gray-50 p-6 rounded-xl border border-gray-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-blue-600 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <h3 class="font-semibold text-gray-800 text-lg">Fast & Free for All</h3>
                    <p class="text-sm text-gray-600 mt-1 text-center">Get address information instantly, with no hidden fees or required registration.</p>
                </div>

                <div class="flex flex-col items-center bg-gray-50 p-6 rounded-xl border border-gray-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-blue-600 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <h3 class="font-semibold text-gray-800 text-lg">High-Precision Data</h3>
                    <p class="text-sm text-gray-600 mt-1 text-center">We utilize up-to-date data from OpenStreetMap for the most accurate results.</p>
                </div>
            </div>
        </div>

        <div class="mt-12 text-left">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">
                Frequently Asked Questions
            </h2>
            <div class="space-y-4">
                <div class="bg-gray-50 p-6 rounded-xl border border-gray-200">
                    <h3 class="font-semibold text-gray-800 text-lg mb-2">What is a reverse address lookup?</h3>
                    <p class="text-sm text-gray-600">A reverse address lookup is a process of using a street address to find information about a property or location. Our tool makes this process easy and anonymous, focusing on providing geographic details without revealing personal information.</p>
                </div>
                <div class="ad-placeholder">
                    Anuncio nativo
                </div>
                <div class="bg-gray-50 p-6 rounded-xl border border-gray-200">
                    <h3 class="font-semibold text-gray-800 text-lg mb-2">Is this service really free?</h3>
                    <p class="text-sm text-gray-600">Yes, our address finder and lookup tool is completely free to use, with no hidden costs or subscriptions. We are committed to providing free public access to this essential geographical information.</p>
                </div>
                <div class="bg-gray-50 p-6 rounded-xl border border-gray-200">
                    <h3 class="font-semibold text-gray-800 text-lg mb-2">How do you ensure user privacy?</h3>
                    <p class="text-sm text-gray-600">We take your privacy very seriously. We do not store your search queries, IP addresses, or any personal information. All searches are processed anonymously to ensure your data is never compromised.</p>
                </div>
            </div>
        </div>

        <div class="mt-12 text-left">
            <h2 class="text-xl font-bold text-gray-900 mb-3">Our Commitment to You</h2>
            <p class="text-gray-600">
                worldstreets.global was created with a mission to provide a simple, accessible, and free address lookup tool for everyone. We believe that geographical information should be readily available without compromising user privacy. Our tool is a reflection of this commitment, offering a direct, transparent, and secure service.
            </p>
        </div>

    </div>
@endsection
