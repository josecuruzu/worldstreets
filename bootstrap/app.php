<?php

use App\Http\Middleware\BloqueaScrapers;
use App\Http\Middleware\HostCanonico;
use App\Http\Middleware\IdiomaPorDefecto;
use App\Http\Middleware\RespuestaCacheable;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            /*
             * Las rutas públicas van en su propio grupo, sin sesión ni CSRF, y
             * por lo tanto sin Set-Cookie. Es lo que permite que LiteSpeed las
             * sirva desde su caché: con una cookie de sesión por visita —que es
             * lo que hace el sitio viejo— ningún caché compartido guarda nada.
             *
             * Se cargan después de web.php porque `/{pais}` captura cualquier
             * segmento suelto y taparía /login, /dashboard y compañía.
             */
            Route::middleware('publico')->group(base_path('routes/public.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * Antes que nada: un scraper bloqueado no tiene que llegar a abrir
         * una sesión ni a tocar la base. Son el 59% de las páginas vistas y
         * dejaron $0,73 en un año.
         */
        $middleware->prepend(BloqueaScrapers::class);

        /*
         * El noindex del dominio de pruebas. Global y no en el grupo
         * 'publico': tiene que cubrir cualquier ruta, incluidas las que
         * aparezcan después, sin que nadie se acuerde de agregarlo.
         */
        $middleware->append(HostCanonico::class);

        /*
         * Y cada petición arranca en el idioma del sitio. El prefijo `/es` lo
         * cambia después, desde el grupo de rutas. Ver `IdiomaPorDefecto`: el
         * idioma sobrevive entre peticiones en cualquier runtime que no mate
         * el proceso.
         */
        $middleware->append(IdiomaPorDefecto::class);

        $middleware->group('publico', [
            AddQueuedCookiesToResponse::class,
            SubstituteBindings::class,
            RespuestaCacheable::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
