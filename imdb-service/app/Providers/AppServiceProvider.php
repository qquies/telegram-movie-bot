<?php

namespace App\Providers;

use App\Services\CachedOmdbService;
use App\Services\OmdbClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider; // класс настройки приложения Laravel

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OmdbClient::class, function ($app) {
            return new OmdbClient(
                apiKey: config('services.omdb.key', ''),
                baseUrl: config('services.omdb.base_url', 'https://www.omdbapi.com/')
            );
        });

        $this->app->singleton(CachedOmdbService::class, function ($app) {
            $store = config('services.omdb.cache_store');
            if (! is_string($store) || $store === '') {
                $store = config('cache.default');
            }

            return new CachedOmdbService(
                $app->make(OmdbClient::class), // получаем экземпляр OmdbClient
                Cache::store($store), // получаем экземпляр Cache
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
