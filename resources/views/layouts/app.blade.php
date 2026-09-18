<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    {{-- Solo donde hay sesión. Las rutas públicas corren en un grupo sin
         StartSession para poder salir sin cookie y ser cacheables, y ahí
         csrf_token() lanza. El token lo necesita el POST que registra una
         búsqueda, que se dispara desde la home. --}}
    @if (request()->hasSession())
        <meta name="csrf-token" content="{{ csrf_token() }}">
    @endif
    @yield('meta')
    <title>@yield('title') - worldstreets.global</title>
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 font-sans antialiased text-gray-800">

    <div class="min-h-screen flex @yield('alineacion', 'items-center') justify-center p-4">
        @yield('content')
    </div>

</body>
</html>
