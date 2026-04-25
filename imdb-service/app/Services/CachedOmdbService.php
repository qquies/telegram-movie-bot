<?php

namespace App\Services;

use Illuminate\Contracts\Cache\Repository;

class CachedOmdbService
{
    public function __construct(
        protected OmdbClient $client,
        protected Repository $cache,
    ) {}

    /**
     * @param  array{type?: string, year?: string, page?: int}  $options
     * @return array{Search?: array, totalResults?: string, Response: string, Error?: string}
     */
    public function searchMovies(string $title, array $options = []): array
    {
        $key = 'omdb:search:'.hash('sha256', json_encode(['t' => $title, 'o' => $options], JSON_THROW_ON_ERROR));

        return $this->cache->remember(
            $key,
            (int) config('services.omdb.cache_ttl.search', 3600),
            fn () => $this->client->searchMovies($title, $options)
        );
    }

    /**
     * @param  array{plot?: 'short'|'full'}  $options
     * @return array<string, mixed>
     */
    public function getMovieDetails(string $imdbId, array $options = []): array
    {
        $plot = $options['plot'] ?? 'short';
        $key = 'omdb:movie:'.preg_replace('/[^a-zA-Z0-9_-]/', '', $imdbId).':'.$plot;

        return $this->cache->remember(
            $key,
            (int) config('services.omdb.cache_ttl.movie', 86400),
            fn () => $this->client->getMovieDetails($imdbId, $options) 
        );
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function getTopMovies(?array $imdbIds = null): array
    {
        $ids = $imdbIds ?? config('services.omdb.top_movies_ids', []);
        sort($ids); // сортируем массив ID чтобы избежать дубликатов в кеше
        $key = 'omdb:tops:'.hash('sha256', implode(',', $ids));

        return $this->cache->remember(
            $key,
            (int) config('services.omdb.cache_ttl.tops', 3600),
            fn () => $this->client->getTopMovies($imdbIds)
        );
    }
}
