<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

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
        // Проверяем, что нам прислали именно текст, а не стикер или фото
        if (isset($update['message']['text'])) {
            $chatId = $update['message']['chat']['id'];
            $text = $update['message']['text'];
            
            // Выводим в нашу консоль
            $this->info("Получено: [{$text}] от чата {$chatId}");

            // Отправляем ответ обратно пользователю через HTTP POST запрос
            $token = env('TELEGRAM_BOT_TOKEN');
            Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => "Я получил твое сообщение: " . $text,
            ]);
        }
    }
}