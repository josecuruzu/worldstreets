<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- Solo donde hay sesión. Las rutas públicas corren en un grupo sin
         StartSession para poder salir sin cookie y ser cacheables, y ahí
         csrf_token() lanza. El token lo necesita el POST que registra una
         búsqueda, que se dispara desde la home. --}}
    @if (request()->hasSession())
        <meta name="csrf-token" content="{{ csrf_token() }}">
    @endif
    @yield('meta')
    <title>@yield('title') - worldstreets.global</title>

    {{-- DM Serif Display es el sustituto documentado de Nineties, que es una
         fuente propia del sistema. De Inter hacen falta el 400 y el 550. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Inter:wght@400;500;600&display=swap">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-cream font-sans text-body text-ink">

    {{--
        La barra superior. Transparente a propósito: deja ver el canvas crema,
        que es lo que define el tono del sistema. Sin fondo propio, sin sombra
        y sin sticky — el papel no flota.
    --}}
    <header class="contenedor flex flex-wrap items-center gap-4 py-4">
        <a href="{{ route('home') }}"
           class="flex h-[34px] w-14 shrink-0 items-center justify-center rounded-[17px] bg-ink
                  text-[13px] font-label tracking-[0.1em] text-cream no-underline hover:no-underline"
           aria-label="worldstreets — home">WS</a>

        @yield('nav_extra')

        <nav class="ml-auto flex shrink-0 items-center gap-6">
            <a href="{{ route('home') }}" class="text-ink no-underline hover:underline">Search</a>
        </nav>
    </header>

    <main class="pb-16">
        @yield('content')
    </main>

    <footer class="contenedor pb-10">
        <p class="border-t border-mist pt-6 text-cap text-ash">
            Street data comes from what visitors look up here. Place data from
            <a href="https://www.geonames.org/" rel="noopener">GeoNames</a>; map tiles
            &copy; <a href="https://www.openstreetmap.org/copyright" rel="noopener">OpenStreetMap</a>
            contributors.
        </p>
    </footer>

</body>
</html>
