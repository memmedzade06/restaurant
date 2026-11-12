<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Telegram Bot API üçün sadə müştəri.
 */
class TelegramBot
{
    public function __construct(private readonly ?string $token)
    {
    }

    public function configured(): bool
    {
        return (bool) $this->token;
    }

    public function call(string $method, array $params = [], int $timeout = 15): Response
    {
        return Http::timeout($timeout)
            ->asJson()
            ->post("https://api.telegram.org/bot{$this->token}/{$method}", $params);
    }

    public function sendMessage(string|int $chatId, string $text, array $extra = []): void
    {
        $this->call('sendMessage', ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'] + $extra);
    }

    /** Telefon nömrəsini paylaşmaq üçün düymə */
    public static function contactKeyboard(): array
    {
        return ['reply_markup' => [
            'keyboard' => [[['text' => '📱 Nömrəmi paylaş', 'request_contact' => true]]],
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
        ]];
    }

    public static function removeKeyboard(): array
    {
        return ['reply_markup' => ['remove_keyboard' => true]];
    }
}
