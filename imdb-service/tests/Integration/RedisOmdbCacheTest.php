<?php

namespace Tests\Integration;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class RedisOmdbCacheTest extends TestCase
{
    // настройка теста
    protected function setUp(): void
    {
        parent::setUp();

        try {
            Redis::connection('cache')->ping();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Redis недоступен: '.$e->getMessage());
        }

        config([
            'database.redis.client' => 'predis',
            'cache.default' => 'redis',
            'services.omdb.cache_store' => 'redis',
        ]);

        Redis::purge('cache');

        Cache::store('redis')->clear();
    }

    // тест на использование кеша при втором запросе фильма
    public function test_second_movie_request_uses_cache_single_http_call(): void
    {
        Http::fake([
            'www.omdbapi.com/*' => Http::response([
                'Title' => 'Cached Title',
                'imdbID' => 'tt1375666',
                'Response' => 'True',
            ], 200),
        ]);

        $this->getJson('/api/movies/tt1375666')->assertOk();
        $this->getJson('/api/movies/tt1375666')->assertOk();

        $recorded = Http::recorded(); // получаем записанные запросы
        $this->assertCount(1, $recorded, 'Второй запрос должен взять данные из Redis без повторного вызова OMDb');
    }

    // тест на использование кеша при втором запросе поиска фильмов
    public function test_search_results_are_cached(): void
    {
        Http::fake([
            'www.omdbapi.com/*' => Http::response([
                'Search' => [
                    ['Title' => 'One', 'Year' => '2000', 'imdbID' => 'tt1000000', 'Type' => 'movie', 'Poster' => 'N/A'],
                ],
                'totalResults' => '1',
                'Response' => 'True',
            ], 200),
        ]);

        $this->getJson('/api/search?q=One')->assertOk();
        $this->getJson('/api/search?q=One')->assertOk();

        $this->assertCount(1, Http::recorded());
    }
}
