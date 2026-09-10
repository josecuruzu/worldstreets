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

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
