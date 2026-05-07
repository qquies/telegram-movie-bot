<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\MovieParserService; 
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class MovieParserTest extends TestCase
{
    // Обязательно используем транзакции, чтобы не засорять реальную БД тестовыми фильмами
    use DatabaseTransactions;

    public function test_movie_is_parsed_from_fake_api_and_saved_to_database()
    {
        // 1. НАСТРОЙКА (Arrange): Создаем фейковые ответы от микросервисов
       Http::fake([
        '*' => Http::sequence()
            // 1-й запрос: searchInImdb -> возвращает структуру поиска
            ->push([
                'Search' => [
                    ['imdbID' => 'tt1375666']   // ID фильма "Inception"
                ]
            ], 200)
            // 2-й запрос: searchInKinopoisk -> оставляем как было
            ->push([
                'success' => true,
                'data' => [
                    'docs' => [
                        [
                            'name' => 'Начало',
                            'rating' => ['kp' => 8.671],
                            'genres' => [['name' => 'фантастика']],
                            'poster' => ['url' => 'https://fake-url.com/poster.jpg']
                        ]
                    ]
                ]
            ], 200)
            // 3-й запрос: getImdbMovieById -> детальная информация
            ->push([
                'Title'    => 'Inception',
                'imdbRating' => '8.8',
                'Director' => 'Christopher Nolan',
                'Production' => 'Warner Bros.',
                'Country'  => 'USA',
                'Awards'   => 'Won 4 Oscars',
                'BoxOffice' => '$292,576,195',
                'Poster'   => 'https://fake-url.com/poster.jpg'
            ], 200),
        ]);


        $command = new MovieParserService();
        $command->fetchAndParseApiData('Начало', null);


        $this->assertDatabaseHas('product', [
            'product_title' => 'Начало',
            'user_mark_kino_poisk' => 8.671,
            'user_mark_imdb' => 8.8,
            'poster_link' => 'https://fake-url.com/poster.jpg'
        ]);

        $this->assertDatabaseHas('director', [
            'director_name' => 'Christopher',
            'director_surname' => 'Nolan'
        ]);


        $this->assertDatabaseHas('genre', [
            'genre_title' => 'Фантастика' 
        ]);
        

        $this->assertDatabaseHas('studio', [
            'studio_title' => 'Warner Bros.'
        ]);
    }
}