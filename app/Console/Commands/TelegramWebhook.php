<?php

namespace App\Console\Commands;

use App\Services\TelegramBot;
use Illuminate\Console\Command;

/**
 * Serverdə bir dəfə işlədilir: Telegram bota gələn mesajları sayta (HTTPS) göndərsin.
 */
class TelegramWebhook extends Command
{
    protected $signature = 'telegram:webhook {--delete : Webhook-u söndürür}';

    protected $description = 'Telegram webhook ünvanını qurur (APP_URL əsasında)';

    public function handle(TelegramBot $bot): int
    {
        if (! $bot->configured() || ! config('services.telegram.webhook_secret')) {
            $this->error('.env faylında TELEGRAM_BOT_TOKEN və TELEGRAM_WEBHOOK_SECRET yazılmalıdır.');
            return self::FAILURE;
        }

        if ($this->option('delete')) {
            $this->line($bot->call('deleteWebhook')->body());
            return self::SUCCESS;
        }

        $url = route('telegram.webhook');
        if (! str_starts_with($url, 'https://')) {
            $this->error("Webhook yalnız HTTPS ünvanla işləyir. APP_URL-i yoxlayın: {$url}");
            return self::FAILURE;
        }

        $response = $bot->call('setWebhook', [
            'url' => $url,
            'secret_token' => config('services.telegram.webhook_secret'),
            'allowed_updates' => ['message'],
        ]);

        $this->line($response->body());

        return $response->json('ok') ? self::SUCCESS : self::FAILURE;
    }
}
