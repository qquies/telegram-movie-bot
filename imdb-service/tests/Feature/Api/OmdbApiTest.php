<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OmdbApiTest extends TestCase
{
    // тест на поиск фильмов по названию
    public function test_search_returns_json_and_requires_query(): void
    {
        $this->getJson('/api/search')->assertUnprocessable();

        Http::fake([
            'www.omdbapi.com/*' => Http::response([
                'Search' => [
                    ['Title' => 'Test', 'Year' => '2020', 'imdbID' => 'tt1234567', 'Type' => 'movie', 'Poster' => 'N/A'],
                ],
                'totalResults' => '1',
                'Response' => 'True',
            ], 200),
        ]);

        $this->getJson('/api/search?q=Test') // выполняем запрос
            ->assertOk() // проверяем, что запрос успешен
            ->assertJsonPath('Response', 'True') // проверяем, что в ответе есть поле Response со значением True
            ->assertJsonPath('Search.0.Title', 'Test'); // проверяем, что в ответе есть фильм с названием Test
    }

    // тест на возврат 404 при ошибке OMDb API
    public function test_search_returns_404_when_omdb_fails(): void
    {
        Http::fake([
            'www.omdbapi.com/*' => Http::response([
                'Response' => 'False',
                'Error' => 'Movie not found!',
            ], 200),
        ]);

        $this->getJson('/api/search?q=xyznone')
            ->assertNotFound()
            ->assertJsonPath('message', 'Movie not found!');
    }

    // тест на получение детальной информации о фильме по IMDb ID
    public function test_movie_returns_details(): void
    {
        Http::fake([
            'www.omdbapi.com/*' => Http::response([
                'Title' => 'Inception',
                'imdbID' => 'tt1375666',
                'Response' => 'True',
            ], 200),
        ]);

        $this->getJson('/api/movies/tt1375666')
            ->assertOk()
            ->assertJsonPath('Title', 'Inception');
    }

    // тест на получение детальной информации о фильме по IMDb ID с параметром plot
    public function test_movie_accepts_plot_parameter(): void
    {
        Http::fake([
            'www.omdbapi.com/*' => Http::response([
                'Title' => 'Inception',
                'Plot' => 'long plot',
                'Response' => 'True',
            ], 200),
        ]);

        $this->getJson('/api/movies/tt1375666?plot=full')
            ->assertOk()
            ->assertJsonPath('Plot', 'long plot');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'plot=full');
        });
    }

    // тест на возврат 404 при неверном IMDb ID
    public function test_movie_returns_404_for_invalid_id(): void
    {
        Http::fake([
            'www.omdbapi.com/*' => Http::response([
                'Response' => 'False',
                'Error' => 'Incorrect IMDb ID.',
            ], 200),
        ]);

        $this->getJson('/api/movies/tt9999999')
            ->assertNotFound();
    }

    // тест на получение топа фильмов по списку IMDb ID из конфига
    public function test_tops_returns_list(): void
    {
        config(['services.omdb.top_movies_ids' => ['tt0111161', 'tt0068646']]);

        Http::fake([
            'www.omdbapi.com/*' => Http::sequence()
                ->push(['Title' => 'A', 'imdbID' => 'tt0111161', 'Response' => 'True'], 200)
                ->push(['Title' => 'B', 'imdbID' => 'tt0068646', 'Response' => 'True'], 200),
        ]);

        $this->getJson('/api/tops')
            ->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonPath('movies.0.Title', 'A')
            ->assertJsonPath('movies.1.Title', 'B');
    }

    // тест на возврат 404 при неверном IMDb ID в маршруте
    public function test_invalid_imdb_id_route_not_matched(): void
    {
        $this->getJson('/api/movies/not-an-id')->assertNotFound();
    }
}
