<?php

namespace App\Support;

use App\Models\WheelSpin;
use Illuminate\Support\Facades\Cache;

/**
 * Çarx oyunu və Hədiyyə Kranı: gündə yalnız BİR oyunda hədiyyə götürmək olar.
 * Birində hədiyyə götürən həmin gün o birində hədiyyə ala bilməz (Bakı vaxtı ilə gün).
 *
 * Kupon istifadə olunub və ya vaxtı keçib bazadan silinə bilər — ona görə oyunun günlük
 * raundundakı (keşdə) "götürülüb" qeydinə də baxılır.
 */
class DailyPrize
{
    /** oyun açarı => "...da" forması (mesaj üçün) */
    private const GAMES = [
        'wheel' => 'Çarx oyununda',
        'claw' => 'Hədiyyə Kranında',
    ];

    /**
     * Bu gün başqa oyunda hədiyyə götürülübsə, həmin oyunun adı ("Hədiyyə Kranında"), yoxsa null.
     */
    public static function claimedElsewhere(int $userId, string $game): ?string
    {
        $today = now('Asia/Baku')->toDateString();

        foreach (self::GAMES as $other => $where) {
            if ($other === $game) {
                continue;
            }
            $inDb = WheelSpin::where('game', $other)->where('user_id', $userId)
                ->whereDate('spun_on', $today)->whereNotNull('code')->exists();
            $round = Cache::get("{$other}_round:{$userId}:{$today}");
            if ($inDb || ! empty($round['claimed'])) {
                return $where;
            }
        }

        return null;
    }

    public static function message(string $where): string
    {
        return "Bu gün {$where} artıq hədiyyə qazanmısınız. Əyləncə üçün oynaya bilərsiniz, amma hədiyyə verilmir — gündə yalnız bir oyunda hədiyyə götürmək olar.";
    }
}
