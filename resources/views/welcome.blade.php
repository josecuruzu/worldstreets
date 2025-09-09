@extends('layouts.app')

@section('title', 'Free Address Finder & Reverse Address Lookup')

@section('content')
    <div x-data="addressFinderApp()" class="bg-white rounded-2xl shadow-xl w-full max-w-2xl p-8 md:p-12 text-center my-8">

        <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900 mb-2">
            Address Finder for the United States
        </h1>
        <p class="text-gray-600 text-lg mb-8">
            Instantly find any U.S. address. Our tool is the fastest, free, and <strong class="font-bold text-gray-800">completely anonymous</strong> way to perform a reverse address lookup.
        </p>

        <div class="ad-placeholder">
            Anuncio adaptable (970x250, 728x90, 320x100)
        </div>

        <div class="w-full relative">
            <input x-model="searchQuery" x-on:keydown.enter="search()" x-on:click.outside="showSuggestions = false" type="text" placeholder="Enter an address or a street..."
                   class="w-full p-4 md:p-5 text-gray-700 bg-gray-100 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 transition duration-300">
            
            <div x-show="showSuggestions" class="absolute w-full mt-2 bg-white border border-gray-300 rounded-xl shadow-lg z-10" x-transition>
                <template x-for="addr in filteredSuggestions" :key="addr">
                    <div x-on:click="selectSuggestion(addr)" class="p-3 cursor-pointer hover:bg-gray-100 rounded-xl transition duration-200" x-text="addr"></div>
                </template>
            </div>
            
            <button x-on:click="search()" class="w-full mt-4 p-4 md:p-5 bg-blue-600 text-white font-semibold rounded-xl hover:bg-blue-700 transition duration-300 shadow-md hover:shadow-lg">
                Find Address
            </button>
        </div>

        <div x-show="showResults" class="w-full mt-12 text-left" x-transition>
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Search Results</h2>
            <div class="bg-gray-50 p-6 rounded-xl border border-gray-200">
                <div x-show="isLoading" class="flex flex-col items-center justify-center py-8">
                    <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="text-lg text-gray-600 mt-4">Searching for address...</p>
                </div>

                <template x-if="searchResults && searchResults.found">
                    <div class="flex flex-col items-center text-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-green-500 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="text-xl font-bold text-gray-800 mb-2">Address Found!</p>
                        <p class="text-lg text-gray-600 mb-4" x-text="searchResults.address"></p>
                        <div class="w-full h-64 rounded-xl bg-gray-300 flex items-center justify-center overflow-hidden">
                            <p class="text-gray-500 font-bold">Map Placeholder</p>
                        </div>
                        <p class="text-sm text-gray-500 mt-4">Note: This is a simulated result. The real service would show an interactive map.</p>
                    </div>
                </template>

                <template x-if="searchResults && !searchResults.found">
                    <div class="flex flex-col items-center text-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-red-500 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="text-xl font-bold text-gray-800 mb-2">Address Not Found</p>
                        <p class="text-lg text-gray-600">We could not find a match for "<span x-text="searchQuery"></span>". Please check the spelling or try a different address.</p>
                    </div>
                </template>
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