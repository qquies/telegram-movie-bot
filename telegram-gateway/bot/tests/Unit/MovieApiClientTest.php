<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\MovieApiClient;
use Illuminate\Support\Facades\Http;

class MovieApiClientTest extends TestCase
{
    public function test_search_imdb_returns_data_on_success()
    {
        Http::fake([
            '*' => Http::response ([
                'title' => 'Матрица',
                'year' => 1999,
                'rating' => 8.7
            
        ], 200)
        ]);

        $client = new MovieApiClient();
        $result = $client->searchInImdb('Матрица');

        $this->assertNotNull($result);
        $this->assertEquals('Матрица', $result['title']);
        $this->assertEquals(1999, $result['year']);
        
    }

    public function test_search_imdb_handles_server_error()
    {
        Http::fake([
            '*' => Http::response (null, 500)
        ]);

        $client = new MovieApiClient();
        $result = $client->searchInImdb('Матрица');

        $this->assertNull($result);
        
    }
}
