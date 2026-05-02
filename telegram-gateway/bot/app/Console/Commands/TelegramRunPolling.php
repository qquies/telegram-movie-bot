<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Services\TelegramBotHandler;
use App\Services\MovieParserService;

class TelegramRunPolling extends Command
{
    protected $signature = 'telegram:poll';
    protected $description = 'Запуск бесконечного цикла опроса Telegram (Long Polling)';

    public function handle()
    {
        $token = env('TELEGRAM_BOT_TOKEN');
        if (!$token) {
            $this->error('ОШИБКА: Токен не найден в .env!');
            return;
        }

        // Инициализируем наши новые классы-сервисы
        $parserService = new MovieParserService();
        $botHandler = new TelegramBotHandler($parserService);

        $this->info('Бот запущен. Ожидание сообщений...');
        $offset = 0;

        while (true) {
            try {
                $response = Http::timeout(30)->get("https://api.telegram.org/bot{$token}/getUpdates", [
                    'offset' => $offset,
                    'timeout' => 20, 
                ]);

                if ($response->successful()) {
                    $updates = $response->json('result');
                    
                    foreach ($updates as $update) {
                        $this->processUpdate($update, $botHandler);
                        $offset = $update['update_id'] + 1; 
                    }
                }
            } catch (\Exception $e) {
                $this->error('Ошибка сети: ' . $e->getMessage());
                sleep(2);
            }
        }
    }

    private function processUpdate($update, TelegramBotHandler $botHandler)
    {
        // 1. Если это нажатие на кнопку (Callback)
        if (isset($update['callback_query'])) {
            $botHandler->handleCallback($update['callback_query']);
            return;
        }

        // 2. Если это текстовое сообщение
        if (isset($update['message']['text'])) {
            $chatId = $update['message']['chat']['id'];
            $text = $update['message']['text'];
            $firstName = $update['message']['from']['first_name'] ?? 'Аноним';
            
            $this->info("Получено: [{$text}] от чата {$chatId}");

            // Отдаем текст обработчику
            $botHandler->handleMessage($chatId, $text, $firstName);
        }
    }
}