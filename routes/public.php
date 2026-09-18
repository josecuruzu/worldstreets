<?php

/*
|--------------------------------------------------------------------------
| RUTAS PÚBLICAS — SIN SESIÓN, CACHEABLES EN EL BORDE
|--------------------------------------------------------------------------
|
| Estas páginas son idénticas para cualquier visitante: no dependen de sesión,
| cookies ni usuario. Corren bajo el grupo 'publico' (ver bootstrap/app.php),
| que NO incluye StartSession ni CSRF, así que la respuesta sale sin
| Set-Cookie y con Cache-Control público. Eso es lo que permite que LiteSpeed
| las sirva desde su caché en vez de levantar PHP y golpear MySQL.
|
| El handoff lo anota como una de las cosas que rompió al sitio viejo: la home
| en CakePHP responde `no-store, no-cache, must-revalidate` y setea una cookie
| de sesión en cada visita, así que el caché del servidor no sirve para nada y
| cada petición cuesta 2.400 ms. Con 240 rastreos diarios y 18,28 M de URLs
| conocidas, la velocidad es lo que decide cuánto tarda Google en reevaluar.
|
| Lo que NO está acá y por qué:
|
| · La home. Su formulario registra búsquedas con POST y necesita el token
|   CSRF, que sale de la sesión. Son 3 URLs y 154 de los 6.930 clics; el
|   rastreo se lo llevan las fichas.
| · POST /api/busquedas. Muta y deduplica por sesión.
| · Las rutas de Breeze.
|
*/

use App\Http\Controllers\BuscadorController;
use App\Http\Controllers\LegadoController;
use App\Http\Controllers\LugaresController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

/*
| Sitemaps. Van antes de las fichas porque `/sitemap.xml` tiene forma de slug
| y `/{pais}` lo tomaría.
*/
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/sitemaps/lugares.xml', [SitemapController::class, 'lugares'])->name('sitemap.lugares');
Route::get('/sitemaps/{pais}.xml', [SitemapController::class, 'pais'])
    ->where('pais', '[a-z0-9-]+')
    ->name('sitemap.pais');

// El sitio viejo publicaba `sitemaps.xml`; Google ya lo conoce.
Route::get('/sitemaps.xml', fn () => redirect()->route('sitemap', [], 301));
Route::get('/sitemap', fn () => redirect()->route('sitemap', [], 301));

/*
| Los endpoints de lectura del buscador. Son GET, no mutan nada y devuelven lo
| mismo para todos, así que no necesitan sesión ni token. El throttle sigue
| porque los dispara el autocomplete mientras el usuario tipea.
*/
Route::get('/api/ciudades', [BuscadorController::class, 'ciudades'])
    ->name('api.ciudades')
    ->middleware('throttle:120,1');

Route::get('/api/ciudades/{ciudad}/calles', [BuscadorController::class, 'calles'])
    ->name('api.ciudades.calles')
    ->whereNumber('ciudad')
    ->middleware('throttle:120,1');

/*
| Las URLs del sitio viejo en CakePHP. Un 301 o un 410 son la respuesta más
| cacheable que hay: no dependen de nada más que del slug.
*/
$legado = function () {
    Route::get('view/find/maps/address/{slug}', [LegadoController::class, 'ciudad']);
    Route::get('country/maps/address/{slug}', [LegadoController::class, 'pais']);
    Route::get('state/maps/address/{slug}', [LegadoController::class, 'provincia']);
    Route::get('county/maps/address/{slug}', [LegadoController::class, 'eliminado']);
    Route::get('continent/maps/address/{slug}', [LegadoController::class, 'eliminado']);
};

Route::where(['slug' => '[a-zA-Z0-9_-]+'])->group($legado);

Route::prefix('{idioma}')
    ->where(['idioma' => LegadoController::IDIOMAS, 'slug' => '[a-zA-Z0-9_-]+'])
    ->group($legado);

Route::get('/{idioma}', fn () => redirect('/', 301))
    ->where('idioma', LegadoController::IDIOMAS);

/*
| Fichas de lugar: /japan, /japan/tokyo, /japan/tokyo/tokyo-1850147.
|
| Van al final de todo a propósito: `/{pais}` captura cualquier segmento
| suelto, así que cualquier ruta declarada después nunca se alcanzaría.
*/
Route::get('/{pais}', [LugaresController::class, 'pais'])
    ->where('pais', '[a-z0-9-]+')
    ->name('pais');

Route::get('/{pais}/{provincia}', [LugaresController::class, 'provincia'])
    ->where(['pais' => '[a-z0-9-]+', 'provincia' => '[a-z0-9-]+'])
    ->name('provincia');

Route::get('/{pais}/{provincia}/{ciudad}', [LugaresController::class, 'ciudad'])
    ->where(['pais' => '[a-z0-9-]+', 'provincia' => '[a-z0-9-]+', 'ciudad' => '[a-z0-9-]+'])
    ->name('ciudad');
