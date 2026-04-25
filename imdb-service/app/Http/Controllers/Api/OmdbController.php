<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CachedOmdbService;
use App\Services\OmdbClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OmdbController extends Controller
{
    public function __construct(
        protected CachedOmdbService $omdb,
    ) {}

    // поиск фильмов по названию
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:200'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'year' => ['sometimes', 'string', 'max:4'],
            'type' => ['sometimes', 'in:movie,series,episode'],
        ]);

        $options = array_filter([
            'page' => $validated['page'] ?? null,
            'year' => $validated['year'] ?? null,
            'type' => $validated['type'] ?? null,
        ], fn ($v) => $v !== null); // удаляем null значения из массива

        $result = $this->omdb->searchMovies($validated['q'], $options);

        // проверяем, успешен ли ответ API
        if (! OmdbClient::isSuccessResponse($result)) {
            return response()->json([
                'message' => OmdbClient::getErrorMessage($result) ?? 'Search failed',
                'omdb' => $result,
            ], 404);
        }

        return response()->json($result);
    }

    // получение детальной информации о фильме по IMDb ID
    public function movie(Request $request, string $imdbId): JsonResponse
    {
        $validated = $request->validate([
            'plot' => ['sometimes', 'in:short,full'],
        ]);

        $result = $this->omdb->getMovieDetails($imdbId, [
            'plot' => $validated['plot'] ?? 'short',
        ]);

        if (! OmdbClient::isSuccessResponse($result)) {
            return response()->json([
                'message' => OmdbClient::getErrorMessage($result) ?? 'Movie not found',
                'omdb' => $result,
            ], 404);
        }

        return response()->json($result);
    }

    // получение топа фильмов по списку IMDb ID из конфига
    public function tops(): JsonResponse
    {
        $movies = $this->omdb->getTopMovies();

        return response()->json([
            'count' => count($movies),
            'movies' => $movies,
        ]);
    }
}
