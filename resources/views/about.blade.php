@extends('layouts.app')

@section('title', 'About worldstreets')
@section('description', 'What worldstreets is, where its data comes from, what it stores about you and how to get in touch.')

@section('content')
    <div class="contenedor max-w-3xl">
        <nav class="flex flex-wrap items-center gap-2 text-cap text-ash" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a>
            <span class="text-lavender" aria-hidden="true">/</span>
            <span class="text-ink" aria-current="page">About</span>
        </nav>

        <header class="mt-5">
            <h1 class="font-display text-[34px] leading-[1.08] sm:text-head">About worldstreets</h1>
            <p class="mt-3.5 text-sub">
                A free tool to find an address on the map: pick a city, type a street and,
                optionally, a number.
            </p>
        </header>

        <section class="card mt-6">
            <h2 class="font-display text-head-sm">What it does</h2>
            <p class="mt-2.5 text-body text-ash">
                worldstreets takes a street and a number and shows where it is. It does not look up
                who lives at an address, and it does not offer business listings or routes. It is
                free to use and has no accounts.
            </p>
        </section>

        <section class="card mt-6">
            <h2 class="font-display text-head-sm">Where the data comes from</h2>
            <ul class="mt-2.5 list-disc space-y-2 pl-5 text-body text-ash">
                <li>
                    <strong class="text-ink">Places</strong> (countries, regions, cities, populations,
                    local names) come from
                    <a href="https://www.geonames.org/" rel="noopener">GeoNames</a>, under a
                    Creative Commons Attribution licence.
                </li>
                <li>
                    <strong class="text-ink">Map tiles and address lookups</strong> come from
                    <a href="https://www.openstreetmap.org/copyright" rel="noopener">OpenStreetMap</a>
                    contributors, through the Nominatim geocoder.
                </li>
                <li>
                    <strong class="text-ink">Street names</strong> are the ones kept in our own
                    database. They are not a complete or official register: a street that is
                    missing here may still exist, and a name listed here may be misspelled or
                    abbreviated. A street is added when someone looks up an address that the map
                    can locate.
                </li>
            </ul>
        </section>

        <section class="card mt-6">
            <h2 class="font-display text-head-sm">What we store and who sees it</h2>
            <ul class="mt-2.5 list-disc space-y-2 pl-5 text-body text-ash">
                <li>No account, no sign-up and no personal details are asked for.</li>
                <li>
                    When you look up an address we keep the street and city, with an anonymous
                    count of how often it was searched. If you use the share button, the address you
                    share is saved so the short link can open it.
                </li>
                <li>
                    The site measures traffic with Google Analytics and shows ads through Google
                    AdSense. Those services may set cookies and receive technical data such as your
                    IP address and browser, under Google's own policies. You can manage ad
                    personalisation in your Google account settings.
                </li>
                <li>Address lookups are sent from our server to Nominatim, not from your browser.</li>
            </ul>
        </section>

        <section class="card mt-6">
            <h2 class="font-display text-head-sm">Contact and corrections</h2>
            @if (config('services.contacto.email'))
                <p class="mt-2.5 text-body text-ash">
                    Found a wrong place or street name, or want something removed? Write to
                    <a href="mailto:{{ config('services.contacto.email') }}">{{ config('services.contacto.email') }}</a>.
                </p>
            @else
                <p class="mt-2.5 text-body text-ash">
                    Place data can be corrected at GeoNames, and map data at OpenStreetMap, which
                    this site reads from.
                </p>
            @endif
        </section>
    </div>
@endsection
