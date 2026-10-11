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
use App\Http\Controllers\CompartirController;
use App\Http\Controllers\LegadoController;
use App\Http\Controllers\LugaresController;
use App\Http\Controllers\SitemapController;
use App\Http\Middleware\EnEspanol;
use App\Http\Middleware\MismoOrigen;
use App\Support\Idioma;
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
| La función principal del sitio: calle + altura sobre el mapa.
|
| Es un POST y muta —si la dirección existe, la calle entra en la lista de la
| ciudad—, pero va en el grupo público igual, sin sesión ni CSRF. La razón es
| de dónde se usa: la ficha de la ciudad es la página que recibe el tráfico de
| Google y tiene que salir sin cookie para ser cacheable, así que se renderiza
| sin sesión y no hay token que ponerle al formulario.
|
| Lo que protege al endpoint no es el token —no hay nada que falsificar en
| nombre de nadie: no hay usuarios— sino el throttle de acá y el deduplicado
| por IP del controlador. Y `RespuestaCacheable` deja pasar los POST sin
| tocarlos, así que la respuesta no se comparte entre visitantes.
|
| 20 por minuto: lo dispara el botón Search, no el tecleo.
*/
Route::post('/api/direcciones', [BuscadorController::class, 'direccion'])
    ->name('api.direcciones')
    ->middleware('throttle:20,1');

/*
| Compartir una dirección encontrada.
|
| `POST /api/compartir` devuelve una URL corta y `/r/{codigo}` la abre. Existen
| porque una dirección NO tiene URL propia en este sitio y no la va a tener:
| la altura es infinita y una página por altura es lo que dejó al sitio con
| 18,25 M de URLs. El enlace corto le da URL a algo que no es una página, sin
| agregar una sola URL indexable. Ver `CompartirController`.
|
| El POST crea una fila, así que además del throttle lleva `MismoOrigen`: la
| página que lo llama no tiene sesión —para poder cachearse— y por lo tanto no
| tiene token CSRF que mandar.
|
| `/r/{codigo}` va ANTES que las fichas de lugar por lo de siempre: `/{pais}`
| captura cualquier segmento suelto.
*/
Route::post('/api/compartir', [CompartirController::class, 'crear'])
    ->name('api.compartir')
    ->middleware(['throttle:20,1', MismoOrigen::class]);

Route::get('/r/{codigo}', [CompartirController::class, 'abrir'])
    ->name('compartido')
    ->where('codigo', '[A-Za-z0-9]{10}');

/*
| Las URLs del sitio viejo en CakePHP. Un 301 o un 410 son la respuesta más
| cacheable que hay: no dependen de nada más que del slug.
*/
$legado = function () {
    Route::get('view/find/maps/address/{slug}', [LegadoController::class, 'ciudad']);
    // No es una ruta del Cake: es la URL que escribía el JSON-LD de su ficha de
    // ciudad, con el slug de la CIUDAD. Ver el docblock de LegadoController.
    Route::get('county/find/maps/address/{slug}', [LegadoController::class, 'ciudad']);
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
| Las mismas fichas, en español: /es/cuba/havana/marianao-3547640.
|
| Van ANTES que las inglesas por la misma razón por la que las inglesas van
| últimas: `/{pais}` captura cualquier segmento suelto, incluido `es`, así que
| declaradas después nunca se alcanzarían.
|
| Existen porque el 35,8% de los clics del sitio entra hoy por una URL `/esp/`
| del CakePHP —2.479 de 6.930— y mandarlos todos a la versión inglesa es
| perder la coincidencia entre la consulta y el título, que es lo que los hace
| rankear en posición 6-12. Ver `App\Support\Idioma`, que también define el
| recorte: solo los 21 países hispanohablantes, ~2.381 URLs. Un país que no
| está en esa lista responde 404 en español, no una traducción vacía.
*/
Route::prefix(Idioma::PREFIJO)
    ->name(Idioma::PREFIJO.'.')
    ->middleware(EnEspanol::class)
    ->group(function () {
        Route::get('/{pais}', [LugaresController::class, 'pais'])
            ->where('pais', '[a-z0-9-]+')
            ->name('pais');

        Route::get('/{pais}/{provincia}', [LugaresController::class, 'provincia'])
            ->where(['pais' => '[a-z0-9-]+', 'provincia' => '[a-z0-9-]+'])
            ->name('provincia');

        Route::get('/{pais}/{provincia}/{ciudad}', [LugaresController::class, 'ciudad'])
            ->where(['pais' => '[a-z0-9-]+', 'provincia' => '[a-z0-9-]+', 'ciudad' => '[a-z0-9-]+'])
            ->name('ciudad');
    });

/*
| `/es` a secas todavía no es una página: la home en español está sin hacer.
| Un 301 a la home inglesa es mejor que un 404 mientras tanto — el que llega
| ahí llega desde una URL vieja o escribiéndola a mano, y la home tiene el
| buscador, que funciona igual en cualquier idioma.
*/
Route::get('/'.Idioma::PREFIJO, fn () => redirect('/', 301));

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
