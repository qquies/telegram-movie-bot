<?php

namespace Tests\Unit\Services;

use App\Services\OmdbClient as OmdbClientService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OmdbClientTest extends TestCase
{
    /** @var OmdbClientService */
    protected $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new OmdbClientService('test-api-key', 'https://www.omdbapi.com/');
    }

    public function test_search_movies_returns_results(): void
    {
        Http::fake([
            'www.omdbapi.com/*' => Http::response([
                'Search' => [
                    [
                        'Title' => 'The Shawshank Redemption',
                        'Year' => '1994',
                        'imdbID' => 'tt0111161',
                        'Type' => 'movie',
                        'Poster' => 'https://example.com/poster.jpg',
                    ],
                ],
                'totalResults' => '1',
                'Response' => 'True',
            ], 200),
        ]);

        $result = $this->client->searchMovies('Shawshank');

        $this->assertTrue(OmdbClientService::isSuccessResponse($result));
        $this->assertArrayHasKey('Search', $result);
        $this->assertCount(1, $result['Search']);
        $this->assertEquals('The Shawshank Redemption', $result['Search'][0]['Title']);
        $this->assertEquals('tt0111161', $result['Search'][0]['imdbID']);
    }

    public function test_search_movies_handles_empty_results(): void
    {
        Http::fake([
            'www.omdbapi.com/*' => Http::response([
                'Response' => 'False',
                'Error' => 'Movie not found!',
            ], 200),
        ]);

        $result = $this->client->searchMovies('NonExistentMovie12345');

        $this->assertFalse(OmdbClientService::isSuccessResponse($result));
        $this->assertEquals('Movie not found!', OmdbClientService::getErrorMessage($result));
    }

    public function test_get_movie_details_returns_full_info(): void
    {
        Http::fake([
            'www.omdbapi.com/*' => Http::response([
                'Title' => 'The Shawshank Redemption',
                'Year' => '1994',
                'Rated' => 'R',
                'Released' => '14 Oct 1994',
                'Runtime' => '142 min',
                'Genre' => 'Drama',
                'Director' => 'Frank Darabont',
                'Plot' => 'Two imprisoned men bond over a number of years...',
                'imdbID' => 'tt0111161',
                'imdbRating' => '9.3',
                'Response' => 'True',
            ], 200),
        ]);

        $result = $this->client->getMovieDetails('tt0111161');

        $this->assertTrue(OmdbClientService::isSuccessResponse($result));
        $this->assertEquals('The Shawshank Redemption', $result['Title']);
        $this->assertEquals('1994', $result['Year']);
        $this->assertEquals('9.3', $result['imdbRating']);
        $this->assertEquals('tt0111161', $result['imdbID']);
    }

    public function test_get_movie_details_handles_invalid_id(): void
    {
        Http::fake([
            'www.omdbapi.com/*' => Http::response([
                'Response' => 'False',
                'Error' => 'Incorrect IMDb ID.',
            ], 200),
        ]);

        $result = $this->client->getMovieDetails('invalid');

        $this->assertFalse(OmdbClientService::isSuccessResponse($result));
        $this->assertEquals('Incorrect IMDb ID.', OmdbClientService::getErrorMessage($result));
    }

    public function test_get_movie_by_title_returns_details(): void
    {
        Http::fake([
            'www.omdbapi.com/*' => Http::response([
                'Title' => 'Inception',
                'Year' => '2010',
                'imdbRating' => '8.8',
                'Plot' => 'A thief who steals corporate secrets...',
                'Response' => 'True',
            ], 200),
        ]);

        $result = $this->client->getMovieByTitle('Inception');

        $this->assertTrue(OmdbClientService::isSuccessResponse($result));
        $this->assertEquals('Inception', $result['Title']);
        $this->assertEquals('2010', $result['Year']);
    }

    public function test_get_top_movies_returns_list(): void
    {
        Http::fake([
            'www.omdbapi.com/*' => Http::sequence()
                ->push([
                    'Title' => 'The Shawshank Redemption',
                    'imdbID' => 'tt0111161',
                    'imdbRating' => '9.3',
                    'Response' => 'True',
                ], 200)
                ->push([
                    'Title' => 'The Godfather',
                    'imdbID' => 'tt0068646',
                    'imdbRating' => '9.2',
                    'Response' => 'True',
                ], 200),
        ]);

        $result = $this->client->getTopMovies(['tt0111161', 'tt0068646']);

        $this->assertCount(2, $result);
        $this->assertEquals('The Shawshank Redemption', $result[0]['Title']);
        $this->assertEquals('The Godfather', $result[1]['Title']);
    }

    public function test_get_top_movies_skips_failed_fetches(): void
    {
        Http::fake([
            'www.omdbapi.com/*' => Http::sequence()
                ->push(['Response' => 'True', 'Title' => 'Valid Movie'], 200)
                ->push(['Response' => 'False', 'Error' => 'Not found'], 200),
        ]);

        $result = $this->client->getTopMovies(['tt0111161', 'tt9999999']);

        $this->assertCount(1, $result);
        $this->assertEquals('Valid Movie', $result[0]['Title']);
    }

    public function test_handles_http_error(): void
    {
        Http::fake([
            'www.omdbapi.com/*' => Http::response(null, 500),
        ]);

        $result = $this->client->searchMovies('Test');

        $this->assertArrayHasKey('Response', $result);
        $this->assertEquals('False', $result['Response']);
        $this->assertArrayHasKey('Error', $result);
    }

    public function test_handles_network_timeout(): void
    {
        Http::fake([
            'www.omdbapi.com/*' => Http::response(null, 408),
        ]);

        $result = $this->client->getMovieDetails('tt0111161');

        $this->assertArrayHasKey('Response', $result);
        $this->assertEquals('False', $result['Response']);
    }

    public function test_search_movies_sends_correct_parameters(): void
    {
        Http::fake();

        $this->client->searchMovies('Batman', ['page' => 2, 'year' => '2022']);

        Http::assertSent(function ($request) {
            $url = $request->url();
            return str_contains($url, 's=Batman')
                && str_contains($url, 'page=2')
                && str_contains($url, 'y=2022')
                && str_contains($url, 'apikey=test-api-key');
        });
    }
}
