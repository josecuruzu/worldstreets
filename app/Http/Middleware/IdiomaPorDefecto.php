<?php

namespace App\Http\Middleware;

use App\Support\Idioma;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cada petición arranca en el idioma del sitio.
 *
 * Parece redundante —`config('app.locale')` ya es el idioma por defecto— y no
 * lo es: `App::setLocale()` cambia un objeto del contenedor, y el contenedor
 * vive lo que viva el proceso. Con PHP-FPM el proceso muere en cada petición y
 * el idioma vuelve solo; con cualquier runtime persistente —Octane, un worker,
 * o los tests, que reusan la aplicación entre `$this->get()`— no vuelve.
 *
 * Lo encontró `VersionEnEspanolTest`: después de pedir `/es/japan` el mismo
 * test pedía `/japan` y recibía la ficha con los números escritos a la
 * española y los campos del buscador dados vuelta. En producción hoy no pasa,
 * pero que no pase porque el proceso se muere no es una garantía, es una
 * casualidad del despliegue.
 *
 * Va en el stack global, o sea antes que `EnEspanol`, que es de grupo: primero
 * se restablece el idioma del sitio y después el prefijo `/es` lo cambia si
 * corresponde.
 *
 * ── Y no vale leer config('app.locale') ──────────────────────────────────
 *
 * Que es lo que decía este middleware al principio, y no hacía nada. La razón
 * está adentro de Laravel: `Application::setLocale()` no solo cambia el
 * traductor, **también escribe `app.locale` en la configuración**. O sea que
 * después de una petición en español la configuración dice `es`, y
 * restablecer "el idioma de la configuración" es restablecer el español.
 *
 * Por eso el valor viene de `Idioma::INGLES`, que es una constante del código
 * y no un valor que alguien más pueda haber movido. El idioma del sitio es un
 * hecho de este proyecto, no una preferencia de entorno.
 */
class IdiomaPorDefecto
{
    public function handle(Request $peticion, Closure $siguiente): Response
    {
        App::setLocale(Idioma::INGLES);

        return $siguiente($peticion);
    }
}
