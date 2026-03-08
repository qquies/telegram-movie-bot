<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OmdbClient
{
    /** @var string */
    protected $apiKey;

    /** @var string */
    protected $baseUrl;

    public function __construct(string $apiKey, string $baseUrl = 'https://www.omdbapi.com/')
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl;
    }

    /**
     * Выполнение GET-запроса к OMDb API с таймаутом и проверкой ошибок.
     *
     * @return array<string, mixed>
     */
    protected function request(array $params): array
    {
        $response = Http::timeout(5)->get($this->baseUrl, $params);

        if ($response->failed()) {
            return [
                'Response' => 'False',
                'Error' => 'OMDb API request failed',
            ];
        }

        return $response->json() ?? ['Response' => 'False', 'Error' => 'Invalid response'];
    }

    /**
     * Поиск фильмов по названию.
     *
     * @param  array{type?: string, year?: string, page?: int}  $options
     * @return array{Search?: array, totalResults?: string, Response: string, Error?: string}
     */
    public function searchMovies(string $title, array $options = []): array
    {
        $params = array_merge([
            's' => $title,
            'apikey' => $this->apiKey,
            'type' => $options['type'] ?? null,
            'y' => $options['year'] ?? null,
            'page' => $options['page'] ?? 1,
        ]);

        $params = array_filter($params, fn ($v) => $v !== null);

        return $this->request($params);
    }

    /**
     * Получение детальной информации о фильме по IMDb ID.
     *
     * @param  array{plot?: 'short'|'full'}  $options
     * @return array<string, mixed>
     */
    public function getMovieDetails(string $imdbId, array $options = []): array
    {
        $params = [
            'i' => $imdbId,
            'apikey' => $this->apiKey,
            'plot' => $options['plot'] ?? 'short',
        ];

        return $this->request($params);
    }

    /**
     * Получение детальной информации о фильме по названию.
     *
     * @param  array{year?: string, plot?: 'short'|'full'}  $options
     * @return array<string, mixed>
     */
    public function getMovieByTitle(string $title, array $options = []): array
    {
        $params = array_merge([
            't' => $title,
            'apikey' => $this->apiKey,
            'y' => $options['year'] ?? null,
            'plot' => $options['plot'] ?? 'short',
        ]);

        $params = array_filter($params, fn ($v) => $v !== null);

        return $this->request($params);
    }

    /**
     * Получение топа фильмов по списку IMDb ID из конфига.
     *
     * @return array<array<string, mixed>>
     */
    public function getTopMovies(?array $imdbIds = null): array
    {
        $ids = $imdbIds ?? config('services.omdb.top_movies_ids', []);

        if (empty($ids)) {
            return [];
        }

        $movies = [];
        foreach ($ids as $id) {
            $result = $this->getMovieDetails(trim($id));
            if (($result['Response'] ?? '') === 'True') {
                $movies[] = $result;
            }
        }

        return $movies;
    }

    /**
     * Проверка, успешен ли ответ API.
     */
    public static function isSuccessResponse(array $response): bool
    {
        return ($response['Response'] ?? '') === 'True';
    }

    /**
     * Получение сообщения об ошибке из ответа.
     */
    public static function getErrorMessage(array $response): ?string
    {
        return $response['Error'] ?? null;
    }
}
