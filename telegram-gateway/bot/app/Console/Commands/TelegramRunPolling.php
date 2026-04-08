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
                    

                } 
                else {
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

            elseif ($action == 'fact' && isset($parts[1])) {
                $productId = $parts[1];

                $fact = DB::table('product_fact')
                            ->where('product_id', $productId)
                            ->inRandomOrder()
                            ->first();
                if ($fact) {
                    $responseText = "💡 <b>А ты знал?</b>\n\n" . $fact->fact_text;
                }
                else
                {
                    $responseText = "🤷‍♂️ Для этого фильма в базе пока нет интересных фактов. Мы их скоро добавим!";
                }
                Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $responseText,
                    'parse_mode' => 'HTML'
                ]);
            }
            elseif ($action == 'read' && isset($parts[1])) {
                $productId = $parts[1];
                $update = DB::table('user')->where('telegram_id', $chatId)->update([
                    'current_state' => 'read_amount_' . $productId
                ]);

                if ($update) {
                    Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                        'chat_id' => $chatId,
                        'text' => "🔢 Сколько топовых отзывов ты хочешь прочитать? Введи число (например: 3, 5 или 10):"
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


            $user = DB::table('user')->where('telegram_id', $chatId)->first();

            if ($user && $user->current_state != null && str_starts_with($user->current_state, 'review_')) {
                $productId = str_replace('review_', '', $user->current_state);

                DB::table('review')->updateOrInsert(
                    [
                        'user_id' => $user->user_id,
                        'product_id' => $productId
                    ],
                    [
                        'review_title' => $text
                    ]
                );

                DB::table('user')->where('telegram_id', $chatId)->update([
                    'current_state' => null
                ]);
                Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => "✅ Твой текстовый отзыв успешно сохранен! Можешь искать следующий фильм",  
                    ]);
                return;
            }

            if ($user && $user->current_state != null && str_starts_with($user->current_state, 'read_amount_')) {
                $productId = str_replace('read_amount_', '', $user->current_state);

                // Защита от "дурака" (Валидация типов данных)
                // Если юзер ввел не число (а например слово "пять")
                if (!is_numeric($text) || $text <= 0) {
                    Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                        'chat_id' => $chatId,
                        'text' => "⚠️ Пожалуйста, введи корректное целое число больше нуля (например, 3)."
                    ]);
                    return; // Выходим, состояние НЕ сбрасываем, ждем дальше
                }

                $limitN = (int)$text; // Приводим к целому числу (кастуем тип)

                // Тот самый сложный и крутой SQL-запрос
                $reviews = DB::table('review as r')
                    ->join('user as u', 'r.user_id', '=', 'u.user_id')
                    ->join('rank as ra', 'u.rank_Id', '=', 'ra.rank_id')
                    ->select(
                        'r.review_title', 
                        'r.review_mark', 
                        'ra.rank_title',
                        DB::raw('(SELECT count(*) FROM review WHERE user_id = u.user_id) as total_reviews')
                    )
                    ->where('r.product_id', $productId)
                    ->whereNotNull('r.review_title')
                    ->where('r.review_title', '!=', '')
                    ->orderByDesc('total_reviews')
                    ->limit($limitN) // Используем число, которое ввел юзер!
                    ->get();

                if ($reviews->isEmpty()) {
                    $responseText = "🤷‍♂️ Для этого фильма пока нет текстовых отзывов.";
                } else {
                    $responseText = "💬 <b>Топ-{$limitN} отзывов от активных зрителей:</b>\n\n";
                    foreach ($reviews as $rev) {
                        $responseText .= "👤 <b>{$rev->rank_title}</b> (Всего отзывов: {$rev->total_reviews})\n";
                        $responseText .= "Оценка: {$rev->review_mark}/5 ⭐\n";
                        $responseText .= "📝 <i>«{$rev->review_title}»</i>\n";
                        $responseText .= "➖➖➖➖➖➖➖➖\n";
                    }
                }

                // СБРОС СОСТОЯНИЯ: Возвращаем пользователя в обычный режим
                DB::table('user')->where('telegram_id', $chatId)->update([
                    'current_state' => null
                ]);

                Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $responseText,
                    'parse_mode' => 'HTML'
                ]);

                return; // Текст обработан, выходим из цикла
            }
            $replyText = "";

             // Парсим входящий буфер: проверяем префиксы команд
            if (str_starts_with($text, '/start')) {
                $replyText = $this->handleStartCommand($chatId, $firstName);
                
            } elseif (str_starts_with($text, '/search')) {
                $movieTitle = trim(str_replace(['/search - Искать фильм', '/search'], '', $text));
                $replyText = $this->handleSearchCommand($movieTitle);
                
            } elseif (str_starts_with($text, '/director')) {
                $directorName = trim(str_replace(['/director - По режиссерам', '/director'], '', $text));
                $replyText = $this->handleDirectorCommand($directorName);
                
            } elseif (str_starts_with($text, '/studio')) {
                $studioName = trim(str_replace(['/studio - По студиям', '/studio'], '', $text));
                $replyText = $this->handleStudioCommand($studioName);
                
            } elseif (str_starts_with($text, '/awards')) {
                $movieTitle = trim(str_replace(['/awards - Награды фильмов', '/awards'], '', $text));
                $replyText = $this->handleAwardsCommand($movieTitle);
                
            } elseif (str_starts_with($text, '/status')) {
                $replyText = $this->handleStatusCommand($chatId);
                
            } elseif (str_starts_with($text, '/my_reviews')) {
                $replyText = $this->handleMyReviewsCommand($chatId);
                
            } elseif (str_starts_with($text, '/find_review')) {
                $movieTitle = trim(str_replace(['/find_review - Найти отзыв', '/find_review'], '', $text));
                $replyText = $this->handleFindReviewCommand($chatId, $movieTitle);
                
            } else {
                $replyText = "Я не распознал команду. Воспользуйся меню внизу экрана или напиши /start";
            }
            // Отправляем ответ обратно пользователю через HTTP POST запрос
            
            $payload = [
                'chat_id' => $chatId,
                'text' => $replyText,  
                
                    'parse_mode' => 'HTML'
            ];

            $mainMenu = [
                    'keyboard' => [
                        [['text' => '/start - Главное меню'], ['text' => '/search - Искать фильм']],
                        [['text' => '/director - По режиссерам'], ['text' => '/studio - По студиям']],
                        [['text' => '/awards - Награды фильмов'], ['text' => '/status - Мой рейтинг']],
                        [['text' => '/my_reviews - Мои отзывы'], ['text' => '/find_review - Найти отзыв']],
                    ],
                    'resize_keyboard' => true,
                    'is_persistent' => true
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
                        ],
                        [
                            ['text' => '💡 Интересный факт', 'callback_data' => 'fact_'.$localMovie->product_id]
                        ],
                        [
                            ['text' => '👀 Читать отзывы (Топ)', 'callback_data' => 'read_'.$localMovie->product_id]
                        ]
                    ]
                ];
                $payload['reply_markup'] = json_encode($keyboard);
            }
            else {
                $payload['reply_markup'] = json_encode($mainMenu);
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

        $localMovie = DB::table('product')
            ->leftJoin('genre', 'product.genre_id', '=', 'genre.genre_id')
            ->leftJoin('director', 'product.director_id', '=', 'director.director_id')
            ->where('product_title', trim($movieTitle))
            ->select('product.*', 'genre.genre_title', 'director.director_name', 'director.director_surname')
            ->first();
        
        $needsApiUpdate = true; 

        if ($localMovie) {
            $lastUpdate = \Carbon\Carbon::parse($localMovie->date_of_update);
            
            if ($lastUpdate->diffInDays(now()) < 7) {
                $needsApiUpdate = false; 
                
                // Формируем расширенный и красивый ответ
                $reply = "🎬 <b>{$localMovie->product_title}</b>\n\n";
                if ($localMovie->poster_link) {
                    $reply .= "🖼 <a href='{$localMovie->poster_link}'>Постер к фильму</a>\n";
                }
                $reply .= "🎭 Жанр: <b>{$localMovie->genre_title}</b>\n";
                $reply .= "🎥 Режиссер: <b>{$localMovie->director_name} {$localMovie->director_surname}</b>\n";
                $reply .= "💰 Бюджет: {$localMovie->budget} $\n\n";
                $reply .= "⭐️ Наша оценка: <b>{$localMovie->user_mark_our}</b>\n";
                $reply .= "🍅 Кинопоиск: {$localMovie->user_mark_kino_poisk} | IMDb: {$localMovie->user_mark_imdb}";
                
                return $reply;
            } else {
                // ВАЖНО: заменяем $this->info на Log::info, чтобы тест не падал из-за консольного вывода!
                \Illuminate\Support\Facades\Log::info("Данные устарели. Идем в API...");
            }
        }

        // Если фильма нет ИЛИ он устарел — идём в API товарищей
        if ($needsApiUpdate) {
            return $this->fetchAndParseApiData($movieTitle, $localMovie);
        }
    }

    private function handleDirectorCommand(string $dir_data) {
        $dir_data_sp =explode(' ', $dir_data);
        $name = $dir_data_sp[0];
        $surname = $dir_data_sp[1];
        if (empty($name)) return "Укажи фамилию  имя режиссера. Пример: /director Кристофер Нолан";
        if (empty($surname)) return "Укажи фамилию  имя режиссера. Пример: /director Кристофер Нолан";
        // SELECT product_title FROM product JOIN director ON ... WHERE name ILIKE %...%
        // ILIKE в PostgreSQL ищет без учета регистра букв
        
        $movies = DB::table('product')
            ->join('director', 'product.director_id', '=', 'director.director_id')
            ->where('director.director_surname', 'ILIKE', "%{$surname}%")
            ->orWhere('director.director_name', 'ILIKE', "%{$name}%")
            ->pluck('product.product_title'); // pluck вытаскивает только одну колонку в массив

        if ($movies->isEmpty()) return "К сожалению, я не нашел фильмов этого режиссера в базе.";
        return "🎬 <b>Фильмы режиссера '{$dir_data}':</b>\n\n- " . $movies->implode("\n- ");
    }

    private function handleStudioCommand(string $name) {
        if (empty($name)) return "Укажи название студии. Пример: /studio Warner Bros";
        
        $movies = DB::table('product')
            ->join('studio', 'product.studio_id', '=', 'studio.studio_id')
            ->where('studio.studio_title', 'ILIKE', "%{$name}%")
            ->pluck('product.product_title');

        if ($movies->isEmpty()) return "Не нашел фильмов от студии '{$name}'.";
        return "🎥 <b>Фильмы студии '{$name}':</b>\n\n- " . $movies->implode("\n- ");
    }

    private function handleAwardsCommand(string $title) {
        if (empty($title)) return "Укажи название фильма. Пример: /awards Матрица";

        $awards = DB::table('reward')
            ->join('reward_product_conection', 'reward.reward_id', '=', 'reward_product_conection.reward_id')
            ->join('product', 'reward_product_conection.product_id', '=', 'product.product_id')
            ->where('product.product_title', 'ILIKE', "%{$title}%")
            ->select('reward.reward_category', 'reward.reward_title')
            ->get();

        if ($awards->isEmpty()) return "Для фильма '{$title}' в базе нет наград.";
        
        $text = "🏆 <b>Награды фильма '{$title}':</b>\n\n";
        foreach ($awards as $award) {
            $text .= "🔸 {$award->reward_category}: {$award->reward_title}\n";
        }
        return $text;
    }

    private function handleStatusCommand($chatId) {
        $user = DB::table('user')
            ->join('rank', 'user.rank_Id', '=', 'rank.rank_id')
            ->where('telegram_id', $chatId)
            ->select('user.user_name', 'user.user_id', 'rank.rank_title')
            ->first();

        if (!$user) return "Я тебя не знаю. Напиши /start";

        $reviewCount = DB::table('review')->where('user_id', $user->user_id)->count();

        return "📊 <b>Твой статус, {$user->user_name}:</b>\n\n".
               "🎖 Текущий ранг: <b>{$user->rank_title}</b>\n".
               "📝 Написано отзывов: <b>{$reviewCount}</b>";
    }

    private function handleMyReviewsCommand($chatId) {
        $user = DB::table('user')->where('telegram_id', $chatId)->first();
        if (!$user) return "Напиши /start";

        $reviews = DB::table('review')
            ->join('product', 'review.product_id', '=', 'product.product_id')
            ->where('review.user_id', $user->user_id)
            ->whereNotNull('review.review_title')
            ->where('review.review_title', '!=', '')
            ->orderBy('review.date_of_update', 'desc')
            ->get();

        if ($reviews->isEmpty()) return "Ты еще не написал ни одного отзыва!";

        $text = "📝 <b>Твои отзывы:</b>\n\n";
        foreach ($reviews as $rev) {
            $text .= "🎬 <b>{$rev->product_title}</b> ({$rev->review_mark}/5 ⭐)\n";
            $text .= "<i>«{$rev->review_title}»</i>\n➖➖➖➖➖➖\n";
        }
        return $text;
    }

    private function handleFindReviewCommand($chatId, $title) {
        if (empty($title)) return "Укажи фильм, отзыв на который хочешь найти. Пример: /find_review Матрица";

        $user = DB::table('user')->where('telegram_id', $chatId)->first();
        if (!$user) return "Напиши /start";

        $review = DB::table('review')
            ->join('product', 'review.product_id', '=', 'product.product_id')
            ->where('review.user_id', $user->user_id)
            ->where('product.product_title', 'ILIKE', "%{$title}%")
            ->first();

        if (!$review) return "Ты не оставлял отзыв на фильм '{$title}'.";

        return "🔍 <b>Твой отзыв на «{$review->product_title}»:</b>\n\n".
               "Оценка: {$review->review_mark}/5 ⭐\n".
               "Текст: <i>{$review->review_title}</i>";
    }

    public function fetchAndParseApiData(string $movieTitle, $localMovie) 
    {
        $apiClient = new MovieApiClient();

        $imdbData = $apiClient-> searchInImdb($movieTitle);
        $kpData = $apiClient-> searchInKinopoisk($movieTitle);

        if (!$imdbData && !$kpData) {
            return "🔍 Я попытался найти '{$movieTitle}', но оба микросервиса сейчас недоступны.";
        }
        $updatePayload = [
            'product_title' => $movieTitle
        ];

        if (!$localMovie && !isset($updatePayload['country'])) {
            $updatePayload['country'] = 'Неизвестно';
        }
        // 1. Парсим наши жанр

        if ($kpData && isset($kpData['data']['docs'][0]['genres'][0]['name'])) {
            $genreName = mb_convert_case($kpData['data']['docs'][0]['genres'][0]['name'], MB_CASE_TITLE, "UTF-8"); // Делаем с большой буквы
            
            $genreId = DB::table('genre')->where('genre_title', $genreName)->value('genre_id');
            if (!$genreId) {
                $genreId = DB::table('genre')->insertGetId(['genre_title' => $genreName], 'genre_id');
            }
            $updatePayload['genre_id'] = $genreId;
        } elseif (!$localMovie) {
            $updatePayload['genre_id'] = 1; // Заглушка, если API не вернул жанр для нового фильма
        }

        // 2. Парсим режисера
        if ($imdbData && isset($imdbData['Director']) && $imdbData['Director'] !== 'N/A') {
            // В IMDb режиссеры идут через запятую. Берем первого.
            $firstDirector = explode(',', $imdbData['Director'])[0];
            
            // Разбиваем на имя и фамилию по пробелу (как требует БД)
            $nameParts = explode(' ', trim($firstDirector), 2);
            $dName = $nameParts[0];
            $dSurname = $nameParts[1] ?? ' '; // Если фамилии нет, ставим пробел

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
            $updatePayload['director_id'] = 1; // Заглушка для нового фильма
        }

        // 3 парсим студии и оценки

        if ($imdbData) {
            if (isset($imdbData['imdbRating']) && $imdbData['imdbRating'] !== 'N/A') {
                $updatePayload['user_mark_imdb'] = (float)$imdbData['imdbRating'];
            }
            if (isset($imdbData['Country']) && $imdbData['Country'] !== 'N/A') {
                $updatePayload['country'] = $imdbData['Country'];
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
        // постер

        if ($kpData && isset($kpData['data']['docs'][0]['rating']['kp'])) {
            $updatePayload['user_mark_kino_poisk'] = (float)$kpData['data']['docs'][0]['rating']['kp'];
        }

        if (isset($kpData['data']['docs'][0]['poster']['url'])) {
                $updatePayload['poster_link'] = $kpData['data']['docs'][0]['poster']['url'];
        }

        if (!isset($updatePayload['poster_link']) && $imdbData && isset($imdbData['Poster']) && $imdbData['Poster'] !== 'N/A') {
            $updatePayload['poster_link'] = $imdbData['Poster'];
        }
            
        DB::table('product')->updateOrInsert(
            ['product_title' => $movieTitle],
            $updatePayload
        );

        // 4. ПАРСИМ НАГРАДЫ И СВЯЗЫВАЕМ С ФИЛЬМОМ
        if ($imdbData && isset($imdbData['Awards']) && $imdbData['Awards'] !== 'N/A') {
            $awardsText = $imdbData['Awards']; // Например: "Nominated for 1 Oscar. 15 wins & 60 nominations total"
            
            // Проверяем, не добавляли ли мы уже эту "награду" в базу
            $rewardId = DB::table('reward')->where('reward_title', $awardsText)->value('reward_id');
            if (!$rewardId) {

                $rewardId = DB::table('reward')->insertGetId([
                    'reward_title' => $awardsText,
                    'reward_category' => 'IMDb Aggregated Awards'
                ], 'reward_id');
            }

            DB::table('reward_product_conection')->updateOrInsert(
                [
                    'reward_id' => $rewardId, 
                    'product_id' => $productId
                ],
                [
                    'condition_reward' => true 
                ]
            );
        }


        

        // Возвращаем красивый ответ
        return $this->handleSearchCommand($movieTitle);

    }
}