<?php

namespace App\Providers;

use App\Services\OmdbClient;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
