<?php

use App\Http\Controllers\BuscadorController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

/*
| Endpoints del buscador de la home. Son GET y no mutan nada, así que no
| necesitan token CSRF; van con throttle porque los dispara el autocomplete
| mientras el usuario tipea.
*/
Route::get('/api/ciudades', [BuscadorController::class, 'ciudades'])
    ->name('api.ciudades')
    ->middleware('throttle:120,1');

Route::get('/api/ciudades/{ciudad}/calles', [BuscadorController::class, 'calles'])
    ->name('api.ciudades.calles')
    ->whereNumber('ciudad')
    ->middleware('throttle:120,1');

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
