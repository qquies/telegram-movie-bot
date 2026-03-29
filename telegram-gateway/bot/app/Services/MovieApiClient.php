<?php

namespace App\Services;

use Illuminate\Support\Facades\Http; 
use Illuminate\Support\Facades\Log;

class MovieApiClient
{
    public function searchInImdb(string $title)
    {
        $baseUrl = env('IMDB_SERVICE_URL');

        try {
            $response = Http::timeout(3)->get("{$baseUrl}/api/search", [
                'movie' => $title
            ]);

            if ($response->successful()) {
                return $response->json();
            }
            return null;
        } catch (\Exception $e) {
            Log::error("Ошибка связи с микросервисом IMDb:".$e->getMessage());
            return null;
        }
    }
}