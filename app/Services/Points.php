<?php

namespace App\Services;

use App\Models\PointLog;
use App\Models\User;

/**
 * Xal tarixçəsi: balans dəyişəndə çağırılır (balansı dəyişən tranzaksiyanın içində).
 * Mənbələr: referral | quiz | stack | market | instagram | admin (PointLog::SOURCES).
 */
class Points
{
    public static function log(int $userId, float $amount, string $source, array $meta = [], ?int $relatedUserId = null, ?int $adminId = null, ?string $note = null): void
    {
        if (abs($amount) < 0.0005) {
            return;
        }
        PointLog::create([
            'user_id' => $userId,
            'amount' => round($amount, 3),
            'source' => $source,
            'related_user_id' => $relatedUserId,
            'admin_id' => $adminId,
            'meta' => $meta ?: null,
            'note' => $note !== null ? mb_substr($note, 0, 200) : null,
        ]);
    }

    /**
     * İstifadəçinin tarixçəsi: son qeydlər, mənbələr üzrə yekun və tarixçədən əvvəlki xallar
     * (balans − bütün qeydlərin cəmi: oyunlardan gələn köhnə xallar heç yerdə saxlanmayıb).
     */
    public static function history(User $user, int $limit = 100): array
    {
        $logs = PointLog::where('user_id', $user->id)->with(['related:id,name', 'admin:id,name'])
            ->latest('created_at')->latest('id')->limit($limit)->get();
        $totals = PointLog::where('user_id', $user->id)->selectRaw('source, SUM(amount) as total, COUNT(*) as cnt')
            ->groupBy('source')->get()->keyBy('source');
        $sum = (float) $totals->sum('total');
        $before = round((float) $user->points - $sum, 3);

        return [
            'logs' => $logs,
            'totals' => collect(PointLog::SOURCES)->keys()
                ->filter(fn ($s) => isset($totals[$s]))
                ->mapWithKeys(fn ($s) => [$s => ['total' => round((float) $totals[$s]->total, 3), 'count' => (int) $totals[$s]->cnt]])->all(),
            'before' => abs($before) < 0.0005 ? 0.0 : $before,
            'count' => (int) $totals->sum('cnt'),
        ];
    }
}
