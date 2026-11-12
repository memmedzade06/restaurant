<?php

namespace App\Console\Commands;

use App\Services\TelegramBot;
use App\Services\TelegramVerification;
use Illuminate\Console\Command;

/**
 * Lokal test üçün: webhook olmadan bota gələn mesajları oxuyur.
 * Serverdə bunun əvəzinə bir dəfə "telegram:webhook" işlədilir.
 */
class TelegramPoll extends Command
{
    protected $signature = 'telegram:poll';

    protected $description = 'Telegram botunun mesajlarını polling ilə emal edir (lokal test üçün)';

    public function handle(TelegramBot $bot, TelegramVerification $verification): int
    {
        if (! $bot->configured()) {
            $this->error('.env faylında TELEGRAM_BOT_TOKEN yazılmayıb.');
            return self::FAILURE;
        }

        $bot->call('deleteWebhook'); // polling webhook ilə birgə işləmir
        $this->info('Bot dinlənilir... (dayandırmaq üçün Ctrl+C)');

        $offset = 0;
        while (true) {
            $response = $bot->call('getUpdates', ['offset' => $offset, 'timeout' => 25, 'allowed_updates' => ['message']], 35);

            foreach ($response->json('result', []) as $update) {
                $offset = $update['update_id'] + 1;
                try {
                    $verification->handleUpdate($update);
                    $this->line('emal olundu: update ' . $update['update_id']);
                } catch (\Throwable $e) {
                    report($e);
                    $this->error($e->getMessage());
                }
            }
        }
    }
}
