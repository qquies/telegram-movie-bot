<?php

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\Api\OmdbController;
use App\Services\CachedOmdbService;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class OmdbControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // тест на возврат 404 при ошибке OMDb API
    public function test_search_returns_404_when_service_returns_failure(): void
    {
        $service = Mockery::mock(CachedOmdbService::class); // создаем mock объект CachedOmdbService
        $service->shouldReceive('searchMovies') // проверяем, что метод searchMovies был вызван один раз
            ->once()
            ->with('x', []) // проверяем, что метод был вызван с аргументами 'x' и []
            ->andReturn(['Response' => 'False', 'Error' => 'not found']);

        $controller = new OmdbController($service); // создаем экземпляр контроллера
        $request = Request::create('/api/search', 'GET', ['q' => 'x']);

        $response = $controller->search($request); // выполняем запрос

        $this->assertSame(404, $response->getStatusCode()); // проверяем, что статус ответа 404
        $this->assertSame('not found', $response->getData(true)['message']); // проверяем, что в ответе есть поле message со значением 'not found'
    }

    // тест на возврат 200 при успешном получении детальной информации о фильме
    public function test_movie_returns_200_on_success(): void
    {
        $service = Mockery::mock(CachedOmdbService::class);
        $service->shouldReceive('getMovieDetails')
            ->once()
            ->with('tt1', ['plot' => 'short'])
            ->andReturn(['Response' => 'True', 'Title' => 'OK']);

        $controller = new OmdbController($service);
        $request = Request::create('/api/movies/tt1', 'GET');

        $response = $controller->movie($request, 'tt1');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('OK', $response->getData(true)['Title']);
    }
}
