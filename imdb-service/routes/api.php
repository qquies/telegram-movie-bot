<?php

use App\Http\Controllers\Api\OmdbController;
use Illuminate\Support\Facades\Route;

Route::get('/search', [OmdbController::class, 'search']); // маршрут для поиска фильмов по названию
Route::get('/movies/{imdbId}', [OmdbController::class, 'movie'])->where('imdbId', 'tt[0-9]+'); // маршрут для получения детальной информации о фильме по IMDb ID
Route::get('/tops', [OmdbController::class, 'tops']); // маршрут для получения топа фильмов по списку IMDb ID из конфига
