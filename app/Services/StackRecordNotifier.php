<?php

namespace App\Services;

use App\Models\GameResult;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Stəkan qülləsi: kimsə birinci yerdəki oyunçunu keçəndə köhnə liderə Telegram-dan xəbər verir —
 * yeni lider neçə xal yığdı və nə qazandı.
 *
 * Yalnız birincilik dəyişəndə göndərilir: yeni lider sonra öz rekordunu yeniləsə, köhnə liderə
 * təkrar mesaj getmir (o artıq birinci deyildi).
 */
class StackRecordNotifier
{
    public function __construct(private readonly TelegramBot $bot)
    {
    }

    /**
     * Nəticə bazaya YAZILMAZDAN ƏVVƏL çağırılır.
     * Bu xal kimisə birinci yerdən salırsa, onu və onun rekordunu qaytarır: [User, int] və ya null.
     */
    public function overtaken(User $player, int $score): ?array
    {
        if ($score < 1) {
            return null;
        }

        $leader = GameResult::query()
            ->where('game', 'stack')
            ->where('user_id', '!=', $player->id)
            ->selectRaw('user_id, MAX(points) as best')
            ->groupBy('user_id')
            ->orderByDesc('best')
            ->first();

        if (! $leader || (int) $leader->best < 1 || $score <= (int) $leader->best) {
            return null;
        }

        // Oyunçu artıq birinci idisə, birincilik dəyişmir
        $mine = (int) GameResult::where('game', 'stack')->where('user_id', $player->id)->max('points');
        if ($mine > (int) $leader->best) {
            return null;
        }

        $user = User::find($leader->user_id);

        return $user && $user->telegram_chat_id && $user->isVerified() ? [$user, (int) $leader->best] : null;
    }

    /** Mesaj cavab brauzerə getdikdən sonra göndərilir — Telegram ləng cavab versə də oyunçu gözləmir */
    public function notify(User $oldLeader, int $oldBest, User $winner, int $score, ?string $prize): void
    {
        if (! $this->bot->configured()) {
            return;
        }

        $chatId = $oldLeader->telegram_chat_id;
        $text = "⚠️ <b>Rekordunuz qırıldı!</b>\n\n"
            . '🏆 <b>' . e($winner->short_name) . "</b> Stəkan qülləsində <b>{$score} xal</b> yığıb birinci yerə çıxdı.\n"
            . "Sizin rekordunuz: {$oldBest} xal.\n"
            . ($prize ? '🎁 Qazandığı: <b>' . e($prize) . "</b>\n" : '')
            . "\nBirinciliyi geri almaq üçün gəlin oynayın 😉\n" . route('games.stack');

        // terminating funksiyaları tətbiqdə qalır (məs. testlərdə növbəti sorğuda yenə çağırılır) — mesaj bir dəfə getsin
        $sent = false;
        app()->terminating(function () use ($chatId, $text, &$sent) {
            if ($sent) {
                return;
            }
            $sent = true;
            try {
                $this->bot->sendMessage($chatId, $text);
            } catch (\Throwable $e) {
                Log::warning('Stəkan qülləsi: rekord bildirişi göndərilmədi: ' . $e->getMessage());
            }
        });
    }
}
