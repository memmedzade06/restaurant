<?php

namespace App\Services;

use App\Models\WheelSpin;
use Illuminate\Support\Facades\Log;

/**
 * Hədiyyə qazanan istifadəçiyə Telegram botu ilə təbrik və kupon kodu göndərir.
 * Yalnız Telegram ilə təsdiqlənmiş (botla söhbəti olan) istifadəçilərə; bot qoşulmayıbsa — heç nə etmir.
 */
class PrizeNotifier
{
    /** Hədiyyə haradan gəlib — mesajda "… hədiyyə qazandınız" */
    private const WHERE = [
        'wheel' => 'Çarx oyununda',
        'stack' => 'Stəkan qülləsində',
        'nard' => 'Nərddə günün qalibi oldunuz və',
        'claw' => 'Hədiyyə Kranında',
    ];

    public function __construct(private readonly TelegramBot $bot)
    {
    }

    public function notify(WheelSpin $spin): void
    {
        $user = $spin->user;
        if (! $spin->code || ! $user || ! $user->telegram_chat_id || ! $user->hasVerifiedPhone() || ! $this->bot->configured()) {
            return;
        }

        try {
            $this->bot->sendMessage($user->telegram_chat_id, $this->text($spin), $this->button() ?? []);
        } catch (\Throwable $e) {
            // bildiriş getməsə də hədiyyə qüvvədədir (kod saytda — Hədiyyələrim səhifəsində)
            Log::warning('Hədiyyə bildirişi Telegram-a göndərilmədi: ' . $e->getMessage(), ['spin_id' => $spin->id]);
        }
    }

    public function text(WheelSpin $spin): string
    {
        $label = e($spin->prize_label);
        $lines = $spin->game === 'market'
            ? ['🎁 <b>Hədiyyəniz hazırdır!</b>', "Hədiyyə marketindən: <b>{$label}</b>"]
            : ['🎉 <b>Təbriklər!</b>', (self::WHERE[$spin->game] ?? 'Oyunda') . " hədiyyə qazandınız: <b>{$label}</b>"];

        $lines[] = '';
        $lines[] = '🎟 Kod: <code>' . e($spin->code) . '</code>';
        if ($spin->expires_at) {
            $lines[] = '⏰ ' . $spin->expires_at->timezone('Asia/Baku')->format('d.m.Y H:i') . ' tarixinədək etibarlıdır.';
        }
        $lines[] = 'Kodu restoranda ofisianta göstərin.';

        // https olmayanda (lokal) düymə yoxdur — link mətndə
        if (! $this->button()) {
            $lines[] = '';
            $lines[] = '🎁 Hədiyyələrim: ' . route('prizes');
        }

        return implode("\n", $lines);
    }

    private function button(): ?array
    {
        return TelegramBot::linkButton('🎁 Hədiyyələrim', route('prizes'));
    }
}
