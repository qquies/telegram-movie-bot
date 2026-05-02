<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Services\MovieApiClient;

class MovieParserService
{
    public function fetchAndParseApiData(string $movieTitle, $localMovie) 
    {
        $apiClient = new MovieApiClient();

        $imdbSearchData = $apiClient->searchInImdb($movieTitle);
        $kpData = $apiClient->searchInKinopoisk($movieTitle);

        // Если ничего не нашли - возвращаем false
        if (!$imdbSearchData && !$kpData) {
            return false; 
        }

        $imdbData = null; 
        
        if ($imdbSearchData && isset($imdbSearchData['Search'][0]['imdbID'])) {
            $imdbId = $imdbSearchData['Search'][0]['imdbID']; 
            $imdbData = $apiClient->getImdbMovieById($imdbId);
        }

        $updatePayload = [
            'product_title' => $movieTitle
        ];

        if (!$localMovie && !isset($updatePayload['country'])) {
            $updatePayload['country'] = 'Неизвестно';
        }

        // Парсинг Жанров
        if ($kpData && isset($kpData['data']['docs'][0]['genres'][0]['name'])) {
            $genreName = mb_convert_case($kpData['data']['docs'][0]['genres'][0]['name'], MB_CASE_TITLE, "UTF-8"); 
            $genreId = DB::table('genre')->where('genre_title', $genreName)->value('genre_id');
            if (!$genreId) {
                $genreId = DB::table('genre')->insertGetId(['genre_title' => $genreName], 'genre_id');
            }
            $updatePayload['genre_id'] = $genreId;
        } elseif (!$localMovie) {
            $updatePayload['genre_id'] = 1; 
        }

        // Парсинг Режиссера
        if ($imdbData && isset($imdbData['Director']) && $imdbData['Director'] !== 'N/A') {
            $firstDirector = explode(',', $imdbData['Director'])[0];
            $nameParts = explode(' ', trim($firstDirector), 2);
            $dName = $nameParts[0];
            $dSurname = $nameParts[1] ?? ' '; 

            $directorId = DB::table('director')
                ->where('director_name', $dName)
                ->where('director_surname', $dSurname)
                ->value('director_id');
                
            if (!$directorId) {
                $directorId = DB::table('director')->insertGetId([
                    'director_name' => $dName,
                    'director_surname' => $dSurname
                ], 'director_id');
            }
            $updatePayload['director_id'] = $directorId;
        } elseif (!$localMovie) {
            $updatePayload['director_id'] = 1; 
        }

        // Парсинг Студии, страны, оценок
        if ($imdbData) {
            if (isset($imdbData['imdbRating']) && $imdbData['imdbRating'] !== 'N/A') {
                $updatePayload['user_mark_imdb'] = (float)$imdbData['imdbRating'];
            }
            if (isset($imdbData['Country']) && $imdbData['Country'] !== 'N/A') {
                $updatePayload['country'] = trim(explode(',', $imdbData['Country'])[0]);
            }
            if (isset($imdbData['Production']) && $imdbData['Production'] !== 'N/A') {
                $studioName = $imdbData['Production'];
                $studioId = DB::table('studio')->where('studio_title', $studioName)->value('studio_id');
                if (!$studioId) {
                    $studioId = DB::table('studio')->insertGetId(['studio_title' => $studioName], 'studio_id');
                }
                $updatePayload['studio_id'] = $studioId;
            }
        }

        if ($kpData && isset($kpData['data']['docs'][0]['rating']['kp'])) {
            $updatePayload['user_mark_kino_poisk'] = (float)$kpData['data']['docs'][0]['rating']['kp'];
        }

        if ($kpData && isset($kpData['data']['docs'][0]['poster']['url'])) {
            $updatePayload['poster_link'] = $kpData['data']['docs'][0]['poster']['url'];
        }

        if (!isset($updatePayload['poster_link']) && $imdbData && isset($imdbData['Poster']) && $imdbData['Poster'] !== 'N/A') {
            $updatePayload['poster_link'] = $imdbData['Poster'];
        }
            
         if ($imdbData && isset($imdbData['BoxOffice']) && $imdbData['BoxOffice'] !== 'N/A') {
            
            $cleanBudget = preg_replace('/[^0-9.]/', '', $imdbData['BoxOffice']);
            
            // Проверяем, получилось ли адекватное число, чтобы БД не ругалась на ограничение >= 0
            if (is_numeric($cleanBudget) && $cleanBudget >= 0) {
                $updatePayload['budget'] = (float)$cleanBudget;
            }
        }
        
        // Запись в БД
        DB::table('product')->updateOrInsert(
            ['product_title' => $movieTitle],
            $updatePayload
        );

        $productId = DB::table('product')->where('product_title', $movieTitle)->value('product_id');

        // Парсинг Наград
        if ($productId && $imdbData && isset($imdbData['Awards']) && $imdbData['Awards'] !== 'N/A') {
            $awardsText = $imdbData['Awards']; 
            
            $rewardId = DB::table('reward')->where('reward_title', $awardsText)->value('reward_id');
            if (!$rewardId) {
                $rewardId = DB::table('reward')->insertGetId([
                    'reward_title' => $awardsText,
                    'reward_category' => 'IMDb Aggregated Awards'
                ], 'reward_id');
            }

            DB::table('reward_product_conection')->updateOrInsert(
                ['reward_id' => $rewardId, 'product_id' => $productId],
                ['condition_reward' => true]
            );
        }

       

        return true; // Успешно обработано
    }
}