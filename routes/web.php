<?php

use App\Http\Controllers\BuscadorController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BuscadorController::class, 'home'])->name('home');

/*
| Los GET del buscador se fueron a routes/public.php: no dependen de sesión y
| ahí salen sin cookie y cacheables. Este POST se queda porque muta y porque
| deduplica por sesión.
*/
/*
| Este sí muta: suma uno al contador de la calle buscada. Va por POST, con el
| CSRF y la sesión del grupo `web` (la sesión es lo que evita contar dos veces
| la misma búsqueda). Throttle más corto que los de lectura porque no lo
| dispara el tecleo sino la elección de una calle.
*/
Route::post('/api/busquedas', [BuscadorController::class, 'registrar'])
    ->name('api.busquedas.registrar')
    ->middleware('throttle:30,1');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

/*
| URLs del sitio viejo en CakePHP.
|
| Van antes de las fichas de lugar porque comparten espacio de nombres: sin
| esto, `/esp` lo tomaría `/{pais}` y `/country` también. Por cantidad de
| segmentos no chocarían igual, pero el orden lo deja explícito.
|
| El prefijo de idioma se acepta en las cuatro variantes que publicaba el
| Cake y se descarta: el destino es siempre la versión en inglés, que es la
| decisión de producto (el mercado anglo es el 83,1% de los ingresos).
*/
