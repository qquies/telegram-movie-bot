<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\MovieParserService;

class TelegramBotHandler
{
    protected $token;
    protected $movieParser;

    public function __construct(MovieParserService $movieParser)
    {
        $this->token = env('TELEGRAM_BOT_TOKEN');
        $this->movieParser = $movieParser;
    }

    public function handleCallback($callback)
    {
        $chatId = $callback['message']['chat']['id'];
        $data = $callback['data'];
        $callbackId = $callback['id'];
        
        $parts = explode('_', $data);
        $action = $parts[0];

        if ($action == 'rate' && isset($parts[1])) {
            $productId = $parts[1];
            $this->sendMessage($chatId, "Выбери оценку от 1 до 5:", [
                'inline_keyboard' => [[
                    ['text' => '1', 'callback_data' => "mark_1_{$productId}"],
                    ['text' => '2', 'callback_data' => "mark_2_{$productId}"],
                    ['text' => '3', 'callback_data' => "mark_3_{$productId}"],
                    ['text' => '4', 'callback_data' => "mark_4_{$productId}"],
                    ['text' => '5', 'callback_data' => "mark_5_{$productId}"],
                ]]
            ]);
        }
        
        elseif ($action == 'mark' && isset($parts[1]) && isset($parts[2])) {
            $score = $parts[1];
            $productId = $parts[2];
            $user = DB::table('user')->where('telegram_id', $chatId)->first();

            if ($user) {
                DB::table('review')->updateOrInsert(
                    ['user_id' => $user->user_id, 'product_id' => $productId],
                    ['review_title' => '', 'review_mark' => $score]
                );

                $avg_mark = round(DB::table('review')->where('product_id', $productId)->avg('review_mark'), 1);
                DB::table('product')->where('product_id', $productId)->update(['user_mark_our' => $avg_mark]);

                $this->sendMessage($chatId, "✅ Спасибо! Твоя оценка {$score} успешно сохранена в базу данных.");
            } else {
                $this->sendMessage($chatId, "❌ ошибка: я не нашел тебя в базе пользователей. Напиши /start");
            }
        }

        elseif ($action == 'write' && isset($parts[1])) {
            $productId = $parts[1];
            $update = DB::table('user')->where('telegram_id', $chatId)->update(['current_state' => 'review_' . $productId]);

            if ($update) {
                $this->sendMessage($chatId, "📝 Отлично! Отправь мне текст своего отзыва на этот фильм следующим сообщением:");
            } else {
                $this->sendMessage($chatId, "❌ ошибка: я не нашел тебя в базе пользователей. Напиши /start");
            }
        }

        elseif ($action == 'fact' && isset($parts[1])) {
            $productId = $parts[1];
            $fact = DB::table('product_fact')->where('product_id', $productId)->inRandomOrder()->first();
            
            $responseText = $fact ? "💡 <b>А ты знал?</b>\n\n" . $fact->fact_text : "🤷‍♂️ Для этого фильма в базе пока нет интересных фактов.";
            $this->sendMessage($chatId, $responseText);
        }

        elseif ($action == 'read' && isset($parts[1])) {
            $productId = $parts[1];
            DB::table('user')->where('telegram_id', $chatId)->update(['current_state' => 'read_amount_' . $productId]);
            $this->sendMessage($chatId, "🔢 Сколько топовых отзывов ты хочешь прочитать? Введи число (например: 3, 5 или 10):");
        }

        Http::post("https://api.telegram.org/bot{$this->token}/answerCallbackQuery", ['callback_query_id' => $callbackId]);
    }

    public function handleMessage($chatId, $text, $firstName)
    {
        $user = DB::table('user')->where('telegram_id', $chatId)->first();

        // Обработка состояний (Стейт-машина)
        if ($user && $user->current_state != null) {
            if (str_starts_with($user->current_state, 'review_')) {
                $productId = str_replace('review_', '', $user->current_state);
                DB::table('review')->updateOrInsert(
                    ['user_id' => $user->user_id, 'product_id' => $productId],
                    ['review_title' => $text]
                );
                DB::table('user')->where('telegram_id', $chatId)->update(['current_state' => null]);
                $this->sendMessage($chatId, "✅ Твой текстовый отзыв успешно сохранен! Можешь искать следующий фильм");
                return;
            }

            if (str_starts_with($user->current_state, 'read_amount_')) {
                $productId = str_replace('read_amount_', '', $user->current_state);
                if (!is_numeric($text) || $text <= 0) {
                    $this->sendMessage($chatId, "⚠️ Пожалуйста, введи корректное целое число больше нуля (например, 3).");
                    return;
                }

                $limitN = (int)$text;
                $reviews = DB::table('review as r')
                    ->join('user as u', 'r.user_id', '=', 'u.user_id')
                    ->join('rank as ra', 'u.rank_Id', '=', 'ra.rank_id')
                    ->select('r.review_title', 'r.review_mark', 'ra.rank_title', DB::raw('(SELECT count(*) FROM review WHERE user_id = u.user_id) as total_reviews'))
                    ->where('r.product_id', $productId)->whereNotNull('r.review_title')->where('r.review_title', '!=', '')
                    ->orderByDesc('total_reviews')->limit($limitN)->get();

                if ($reviews->isEmpty()) {
                    $responseText = "🤷‍♂️ Для этого фильма пока нет текстовых отзывов.";
                } else {
                    $responseText = "💬 <b>Топ-{$limitN} отзывов от активных зрителей:</b>\n\n";
                    foreach ($reviews as $rev) {
                        $responseText .= "👤 <b>{$rev->rank_title}</b> (Всего отзывов: {$rev->total_reviews})\n";
                        $responseText .= "Оценка: {$rev->review_mark}/5 ⭐\n📝 <i>«{$rev->review_title}»</i>\n➖➖➖➖➖➖➖➖\n";
                    }
                }

                DB::table('user')->where('telegram_id', $chatId)->update(['current_state' => null]);
                $this->sendMessage($chatId, $responseText);
                return;
            }
        }

        // Обработка команд
        $replyText = "";
        $movieTitleForButtons = null; // Флаг, чтобы знать, нужны ли inline кнопки

        if (str_starts_with($text, '/start')) {
            $replyText = $this->handleStartCommand($chatId, $firstName);
        } elseif (str_starts_with($text, '/search')) {
            $movieTitleForButtons = trim(str_replace(['/search - Искать фильм', '/search'], '', $text));
            $replyText = $this->handleSearchCommand($movieTitleForButtons);
        } elseif (str_starts_with($text, '/director')) {
            $replyText = $this->handleDirectorCommand(trim(str_replace(['/director - По режиссерам', '/director'], '', $text)));
        } elseif (str_starts_with($text, '/studio')) {
            $replyText = $this->handleStudioCommand(trim(str_replace(['/studio - По студиям', '/studio'], '', $text)));
        } elseif (str_starts_with($text, '/awards')) {
            $replyText = $this->handleAwardsCommand(trim(str_replace(['/awards - Награды фильмов', '/awards'], '', $text)));
        } elseif (str_starts_with($text, '/status')) {
            $replyText = $this->handleStatusCommand($chatId);
        } elseif (str_starts_with($text, '/my_reviews')) {
            $replyText = $this->handleMyReviewsCommand($chatId);
        } elseif (str_starts_with($text, '/find_review')) {
            $replyText = $this->handleFindReviewCommand($chatId, trim(str_replace(['/find_review - Найти отзыв', '/find_review'], '', $text)));
        } else {
            $replyText = "Я не распознал команду. Воспользуйся меню внизу экрана или напиши /start";
        }

        // Формируем клавиатуры
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

        $replyMarkup = $mainMenu;

        if (str_starts_with($replyText, '🎬 Нашел в нашей базе.')) {
            $localMovie = DB::table('product')->where('product_title', trim($movieTitleForButtons))->first();
            if ($localMovie) {
                $replyMarkup = [
                    'inline_keyboard' => [
                        [['text' => '⭐ Оценить фильм', 'callback_data' => 'rate_'.$localMovie->product_id]],
                        [['text' => '📝 Написать отзыв', 'callback_data' => 'write_'.$localMovie->product_id]],
                        [['text' => '💡 Интересный факт', 'callback_data' => 'fact_'.$localMovie->product_id]],
                        [['text' => '👀 Читать отзывы (Топ)', 'callback_data' => 'read_'.$localMovie->product_id]]
                    ]
                ];
            }
        }

        $this->sendMessage($chatId, $replyText, $replyMarkup);
    }

    // Хелпер для отправки сообщений
    private function sendMessage($chatId, $text, $replyMarkup = null) {
        $payload = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'];
        if ($replyMarkup) {
            $payload['reply_markup'] = json_encode($replyMarkup);
        }
        Http::post("https://api.telegram.org/bot{$this->token}/sendMessage", $payload);
    }

    // --- ЛОГИКА КОМАНД ---

    private function handleStartCommand($chatId, $firstName) {
        $existingUser = DB::table('user')->where('telegram_id', $chatId)->first();
        if ($existingUser) {
            return "С возвращением, {$firstName}! Рад снова тебя видеть в нашем кино-боте.";
        } else {
            DB::table('user')->insert([
                'telegram_id' => $chatId, 'user_name' => $firstName, 
                'user_surname' => ' ', 'rank_Id' => 1 
            ]);
            return "Привет, {$firstName}! Я успешно зарегистрировал тебя в базе данных.";
        }
    }

    private function handleSearchCommand(string $movieTitle) {
        if (empty($movieTitle)) return "Пожалуйста, укажите название фильма. Пример: /search Бетман";

        $localMovie = $this->getLocalMovie($movieTitle);
        $needsApiUpdate = true; 

        if ($localMovie) {
            if (Carbon::parse($localMovie->date_of_update)->diffInDays(now()) < 7) {
                $needsApiUpdate = false; 
                return $this->formatMovieReply($localMovie);
            } else {
                Log::info("Данные устарели. Идем в API...");
            }
        }

        if ($needsApiUpdate) {
            $success = $this->movieParser->fetchAndParseApiData($movieTitle, $localMovie);
            if (!$success) return "🔍 Я попытался найти '{$movieTitle}', но оба микросервиса сейчас недоступны.";
            
            return $this->formatMovieReply($this->getLocalMovie($movieTitle));
        }
    }

    private function getLocalMovie($title) {
        return DB::table('product')
            ->leftJoin('genre', 'product.genre_id', '=', 'genre.genre_id')
            ->leftJoin('director', 'product.director_id', '=', 'director.director_id')
            ->where('product_title', trim($title))
            ->select('product.*', 'genre.genre_title', 'director.director_name', 'director.director_surname')
            ->first();
    }

    private function formatMovieReply($localMovie) {
        if (!$localMovie) return "Произошла ошибка при получении данных.";
        $reply = "🎬 Нашел в нашей базе.\n <b>{$localMovie->product_title}</b>\n\n";
        if ($localMovie->poster_link) $reply .= "🖼 <a href='{$localMovie->poster_link}'>Постер к фильму</a>\n";
        $reply .= "🎭 Жанр: <b>{$localMovie->genre_title}</b>\n";
        $reply .= "🎥 Режиссер: <b>{$localMovie->director_name} {$localMovie->director_surname}</b>\n";
        $reply .= "💰 Бюджет: {$localMovie->budget} $\n\n";
        $reply .= "⭐️ Наша оценка: <b>{$localMovie->user_mark_our}</b>\n";
        $reply .= "🍅 Кинопоиск: {$localMovie->user_mark_kino_poisk} | IMDb: {$localMovie->user_mark_imdb}";
        return $reply;
    }

    private function handleDirectorCommand(string $dir_data) {
        $dir_data_sp = explode(' ', $dir_data);
        $name = $dir_data_sp[0] ?? '';
        $surname = $dir_data_sp[1] ?? '';
        if (empty($name) || empty($surname)) return "Укажи имя и фамилию режиссера. Пример: /director Кристофер Нолан";
        
        $movies = DB::table('product')->join('director', 'product.director_id', '=', 'director.director_id')
            ->where('director.director_surname', 'ILIKE', "%{$surname}%")->orWhere('director.director_name', 'ILIKE', "%{$name}%")
            ->pluck('product.product_title');

        if ($movies->isEmpty()) return "К сожалению, я не нашел фильмов этого режиссера в базе.";
        return "🎬 <b>Фильмы режиссера '{$dir_data}':</b>\n\n- " . $movies->implode("\n- ");
    }

    private function handleStudioCommand(string $name) {
        if (empty($name)) return "Укажи название студии. Пример: /studio Warner Bros";
        
        $movies = DB::table('product')->join('studio', 'product.studio_id', '=', 'studio.studio_id')
            ->where('studio.studio_title', 'ILIKE', "%{$name}%")->pluck('product.product_title');

        if ($movies->isEmpty()) return "Не нашел фильмов от студии '{$name}'.";
        return "🎥 <b>Фильмы студии '{$name}':</b>\n\n- " . $movies->implode("\n- ");
    }

    private function handleAwardsCommand(string $title) {
        if (empty($title)) return "Укажи название фильма. Пример: /awards Матрица";

        $awards = DB::table('reward')
            ->join('reward_product_conection', 'reward.reward_id', '=', 'reward_product_conection.reward_id')
            ->join('product', 'reward_product_conection.product_id', '=', 'product.product_id')
            ->where('product.product_title', 'ILIKE', "%{$title}%")->select('reward.reward_category', 'reward.reward_title')->get();

        if ($awards->isEmpty()) return "Для фильма '{$title}' в базе нет наград.";
        
        $text = "🏆 <b>Награды фильма '{$title}':</b>\n\n";
        foreach ($awards as $award) $text .= "🔸 {$award->reward_category}: {$award->reward_title}\n";
        return $text;
    }

    private function handleStatusCommand($chatId) {
        $user = DB::table('user')->join('rank', 'user.rank_Id', '=', 'rank.rank_id')
            ->where('telegram_id', $chatId)->select('user.user_name', 'user.user_id', 'rank.rank_title')->first();
        if (!$user) return "Я тебя не знаю. Напиши /start";

        $reviewCount = DB::table('review')->where('user_id', $user->user_id)->count();
        return "📊 <b>Твой статус, {$user->user_name}:</b>\n\n🎖 Текущий ранг: <b>{$user->rank_title}</b>\n📝 Написано отзывов: <b>{$reviewCount}</b>";
    }

    private function handleMyReviewsCommand($chatId) {
        $user = DB::table('user')->where('telegram_id', $chatId)->first();
        if (!$user) return "Напиши /start";

        $reviews = DB::table('review')->join('product', 'review.product_id', '=', 'product.product_id')
            ->where('review.user_id', $user->user_id)->whereNotNull('review.review_title')->where('review.review_title', '!=', '')
            ->orderBy('review.date_of_update', 'desc')->get();

        if ($reviews->isEmpty()) return "Ты еще не написал ни одного отзыва!";

        $text = "📝 <b>Твои отзывы:</b>\n\n";
        foreach ($reviews as $rev) {
            $text .= "🎬 <b>{$rev->product_title}</b> ({$rev->review_mark}/5 ⭐)\n<i>«{$rev->review_title}»</i>\n➖➖➖➖➖➖\n";
        }
        return $text;
    }

    private function handleFindReviewCommand($chatId, $title) {
        if (empty($title)) return "Укажи фильм, отзыв на который хочешь найти. Пример: /find_review Матрица";
        $user = DB::table('user')->where('telegram_id', $chatId)->first();
        if (!$user) return "Напиши /start";

        $review = DB::table('review')->join('product', 'review.product_id', '=', 'product.product_id')
            ->where('review.user_id', $user->user_id)->where('product.product_title', 'ILIKE', "%{$title}%")->first();

        if (!$review) return "Ты не оставлял отзыв на фильм '{$title}'.";
        return "🔍 <b>Твой отзыв на «{$review->product_title}»:</b>\n\nОценка: {$review->review_mark}/5 ⭐\nТекст: <i>{$review->review_title}</i>";
    }
}