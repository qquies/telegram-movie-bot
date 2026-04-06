<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class BotDatabaseLogicTest extends TestCase
{
    use DatabaseTransactions;  

    public function test_user_can_rate_movie_and_average_is_calculation()
    {
        $rankId = DB::table('rank')->insertGetid(['rank_title' => 'Тест'], 'rank_id');


        $userId = DB::table('user')->insertGetid([
            'telegram_id' => 123456789,
            'user_name' => 'Тестер',
            'user_surname' => ' ',
            'rank_Id' => $rankId
        ], 'user_id');

        $genreId = DB::table('genre')->insertGetId(['genre_title' => 'Драма'], 'genre_id');
        $directorId = DB::table('director')->insertGetId(['director_name' => 'Режиссер', 'director_surname' => 'Тестовый'], 'director_id');

        // Вставляем фильм
        $productId = DB::table('product')->insertGetId([
            'product_title' => 'Тестовый фильм',
            'genre_id' => $genreId,
            'director_id' => $directorId,
            'country' => 1,
            'budget' => 1000
        ], 'product_id');

        DB::table('review')->updateOrInsert(
            ['user_id' => $userId, 'product_id' => $productId],
            ['review_title' => '', 'review_mark' => 4]
        );
        $avg = DB::table('review')->where('product_id', $productId)->avg('review_mark');
        DB::table('product')->where('product_id', $productId)->update(['user_mark_our' => $avg]);

        // Проверяем, что в таблице product оценка стала 4
        $this->assertDatabaseHas('product', [
            'product_id' => $productId,
            'user_mark_our' => 4
        ]);

        DB::table('review')->updateOrInsert(
            ['user_id' => $userId, 'product_id' => $productId],
            ['review_title' => '', 'review_mark' => 5]
        );

        $newAvg = DB::table('review')->where('product_id', $productId)->avg('review_mark');
        DB::table('product')->where('product_id', $productId)->update(['user_mark_our' => $newAvg]);
       
        $this->assertDatabaseHas('product', [
            'product_id' => $productId,
            'user_mark_our' => 5
        ]);    

        $reviewCount = DB::table('review')->where('product_id', $productId)->count();
        $this->assertEquals(1, $reviewCount);
    }
}
