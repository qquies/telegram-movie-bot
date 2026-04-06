<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Services\MovieApiClient;

class TelegramRunPolling extends Command
{
    // Как мы будем запускать эту команду в терминале
    protected $signature = 'telegram:poll';
    
    // Описание команды
    protected $description = 'Запуск бесконечного цикла опроса Telegram (Long Polling)';

    public function handle()
    {
        $token = env('TELEGRAM_BOT_TOKEN');
        if (!$token) {
            $this->error('ОШИБКА: Токен не найден в .env!');
            return;
        }

        $this->info('Бот запущен. Ожидание сообщений...');
        $offset = 0;

        // Наш классический суперцикл
        while (true) {
            try {
                // Делаем HTTP GET запрос к Telegram. timeout=20 означает, что соединение
                // будет висеть открытым 20 секунд в ожидании новых сообщений (Long Polling)
                $response = Http::timeout(30)->get("https://api.telegram.org/bot{$token}/getUpdates", [
                    'offset' => $offset,
                    'timeout' => 20, 
                ]);

                if ($response->successful()) {
                    $updates = $response->json('result');
                    
                    foreach ($updates as $update) {
                        $this->processUpdate($update);
                        
                        // Сбрасываем "флаг прерывания", чтобы не обрабатывать сообщение дважды
                        $offset = $update['update_id'] + 1; 
                    }
                }
            } catch (\Exception $e) {
                // Если пропал интернет, просто ждем 2 секунды и пробуем снова
                $this->error('Ошибка сети: ' . $e->getMessage());
                sleep(2);
            }
        }
    }

    // Функция обработки одного конкретного сообщения
    private function processUpdate($update)
    {
        $token = env('TELEGRAM_BOT_TOKEN');
        
        // Перехват прерывания: Если пришло нажатие на Inline-кнопку
        if (isset($update['callback_query'])) {
            $callback = $update['callback_query'];
            $chatId = $callback['message']['chat']['id'];
            $data = $callback['data'];
            $callbackId = $callback['id'];
            
            $parts = explode('_', $data);
            $action = $parts[0];

            if ($action == 'rate' && isset($parts[1])) {
                $productId = $parts[1];

                Http::post("https://api.telegram.org/bot{$token}/sendMessage",[
                    'chat_id' => $chatId,
                    'text' => "Выбери оценку от 1 до 5:",
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => '1', 'callback_data' => "mark_1_{$productId}"],
                                ['text' => '2', 'callback_data' => "mark_2_{$productId}"],
                                ['text' => '3', 'callback_data' => "mark_3_{$productId}"],
                                ['text' => '4', 'callback_data' => "mark_4_{$productId}"],
                                ['text' => '5', 'callback_data' => "mark_5_{$productId}"],

                            ]
                        ]
                    ])
                ]);
            }
            
            elseif ($action == 'mark' && isset($parts[1]) && isset($parts[2])) {
                $score = $parts[1];
                $productId = $parts[2];

                $user = DB::table('user')->where('telegram_id', $chatId)->first();

                if ($user) {
                    DB::table('review')->updateOrinsert([
                        'user_id' => $user->user_id ,
                        'product_id' => $productId
                    ],
                    [
                        'review_title' => '',
                        'review_mark' => $score
                    ]);

                    $avg_mark =  round(DB::table('review')
                                    ->where('product_id', $productId)
                                    ->avg('review_mark'), 1);

                            
                    DB::table('product')
                        ->where('product_id', $productId)
                        ->update([
                            'user_mark_our' => $avg_mark
                        ]);


                    Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => "✅ Спасибо! Твоя оценка {$score} успешно сохранена в базу данных.",  
                    ]);
                    

                } else {
                    Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => "❌ ошибка: я не нашел тебя в базе пользователей. Напиши \start",  
                    ]);
                }
            }

            elseif ($action == 'write' && isset($parts[1])) {
                $productId = $parts[1];

                $update = DB::table('user')
                    ->where('telegram_id', $chatId)
                    ->update([
                        'current_state' => 'review_' . $productId
                    ]);

                if ($update) {
                    Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                        'chat_id' => $chatId,
                        'text' => "📝 Отлично! Отправь мне текст своего отзыва на этот фильм следующим сообщением:"
                    ]);
                } else {
                    Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => "❌ ошибка: я не нашел тебя в базе пользователей. Напиши \start",  
                    ]);
                }
            }

            Http::post("https://api.telegram.org/bot{$token}/answerCallbackQuery", [
                'callback_query_id' => $callbackId
            ]);
        }
        // Проверяем, что нам прислали именно текст, а не стикер или фото
        if (isset($update['message']['text'])) {
            $chatId = $update['message']['chat']['id'];
            $text = $update['message']['text'];
            // Пытаемся достать имя пользователя из Telegram, если скрыто - ставим "Аноним"
            $firstName = $update['message']['from']['first_name'] ?? 'Аноним';
            // Выводим в нашу консоль
            $this->info("Получено: [{$text}] от чата {$chatId}");

            $replyText = "";

             if ($text == '/start') {
                $replyText = $this->handleStartCommand($chatId, $firstName);
            } elseif (str_starts_with($text, '/search')){
                $movieTitle = trim(str_replace('/search', '', $text));
                $replyText = $this->handleSearchCommand($movieTitle);
            }else {
                $replyText = "Я пока понимаю только команду /start. Попробуй отправить ее!";
            }
            // Отправляем ответ обратно пользователю через HTTP POST запрос

            $payload = [
                'chat_id' => $chatId,
                'text' => $replyText,  
            ];

            if (str_starts_with($replyText, '🎬 Нашел в нашей базе.')) {
                $localMovie = DB::table('product')->where('product_title', trim($movieTitle))->first();
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '⭐ Оценить фильм', 'callback_data' => 'rate_'.$localMovie->product_id]
                        ],
                        [
                            ['text' => '📝 Написать отзыв', 'callback_data' => 'write_'.$localMovie->product_id]
                        ]
                    ]
                ];
                $payload['reply_markup'] = json_encode($keyboard);
            }
            Http::post("https://api.telegram.org/bot{$token}/sendMessage", $payload);
        
        }
    }

    private function handleStartCommand($chatId, $firstName)
    {
        try {
            // SELECT * FROM "user" WHERE telegram_id = $chatId LIMIT 1;
            // (Laravel сам подставит кавычки и защитит от SQL-иньекций)
            $existingUser = DB::table('user')->where('telegram_id', $chatId)->first();

            if ($existingUser) {
                return "С возвращением, {$firstName}! Рад снова тебя видеть в нашем кино-боте.";
            } else {
                // INSERT INTO "user" (telegram_id, user_name, rank_Id) VALUES (...)
                DB::table('user')->insert([
                    'telegram_id' => $chatId,
                    'user_name' => $firstName,
                    'user_surname' => ' ', // В твоей БД это поле NOT NULL, ставим пробел как дефолт
                    'rank_Id' => 1 // ID ранга (например, 1 - это "Новичок" из твоих CRUD-скриптов)
                ]);
                return "Привет, {$firstName}! Я успешно зарегистрировал тебя в базе данных.";
            }
        } catch (\Exception $e) {
            $this->error("Ошибка БД: " . $e->getMessage());
            return "Упс, проблема с базой данных. Технические неполадки.";
        }
    }

    private function handleSearchCommand(string $movieTitle) {
        if (empty($movieTitle)) {
            return "Пожалуйста, укажите название фильма. Пример: /search Бетман";
        }

        try {

            $localMovie = DB::table('product')->where('product_title', trim($movieTitle))->first();
            if ($localMovie) {
                return "🎬 Нашел в нашей базе.\n".
                        "Оценка фильма на Кинопоиске: {$localMovie->user_mark_kino_poisk}\n".
                        "Оценка фильма на IMDB: {$localMovie->user_mark_imdb}\n".
                        "Наша оценка фильма: {$localMovie->user_mark_our}⭐\n".
                        "Бюджет: {$localMovie->budget} $";
            } 
        }catch (\Exception $e) {
            $this->error("Ошибка чтения БД: " . $e->getMessage());
        }

        $apiClient = new MovieApiClient();

        $result = $apiClient->searchInImdb($movieTitle);

        if ($result == null) {
            return "🔍 Я порытался найти '{$movieTitle}', но микросервис базы фильмов (IMDb) сейчас недоступен. Товариши еще работают над ним!";
        }

        return "🌐 Нашел в интернете (через API)! Вот данные: ". json_encode($result, JSON_UNESCAPED_UNICODE);
    }
}