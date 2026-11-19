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
        if (! $spin->code || ! $user || ! $user->telegram_chat_id || ! $user->isVerified() || ! $this->bot->configured()) {
            return;
        }

        try {
            // bir mesaj: təbrik + kod, oyunda qazananlara altında dəvət linki; link önizləməsi göstərilmir
            $this->bot->sendMessage($user->telegram_chat_id, $this->text($spin),
                ($this->buttons($spin) ?? []) + ['disable_web_page_preview' => true]);
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

        // oyunda qazananlara: dəvət linkini paylaşıb daha çox hədiyyə qazanmaq olar
        // (marketdən alan onsuz da xalı dəvətlə toplayıb — ona yazılmır)
        if ($spin->game !== 'market') {
            $levels = Referrals::levels();
            $lines[] = '';
            $lines[] = '💡 <b>Daha çox hədiyyə qazanın!</b>';
            $lines[] = 'Profilinizdəki linki dostlarınızla paylaşın. Kimsə onunla qeydiyyatdan keçəndə '
                . '<b>' . Referrals::format($levels[0] ?? 0) . ' xal</b> qazanırsınız, xallarla isə '
                . 'Hədiyyə marketində çay, şirniyyat, yemək və qəlyan ala bilərsiniz.';
            $lines[] = '🔗 Sizin linkiniz: ' . Referrals::link($spin->user);
        }

        // https olmayanda (lokal) düymə yoxdur — linklər mətndə
        if (! $this->buttons($spin)) {
            $lines[] = '';
            $lines[] = '🎁 Hədiyyələrim: ' . route('prizes');
        }

        return implode("\n", $lines);
    }

    /** Mesajın altındakı düymələr: Hədiyyələrim (+ oyunda qazananlara Hədiyyə marketi) */
    private function buttons(WheelSpin $spin): ?array
    {
        $prizes = TelegramBot::linkButton('🎁 Hədiyyələrim', route('prizes'));
        if (! $prizes || $spin->game === 'market') {
            return $prizes;
        }
        $market = TelegramBot::linkButton('🛍 Hədiyyə marketi', route('market'));
        $prizes['reply_markup']['inline_keyboard'][] = $market['reply_markup']['inline_keyboard'][0];

        return $prizes;
    }
}
