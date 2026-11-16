<?php

namespace App\Services;

use App\Models\GameResult;
use App\Models\NardDailyWinner;
use App\Models\NardMatch;
use App\Models\WheelSpin;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Nərdin günlük xal cədvəli. Xal yalnız dostla (iki nəfərlik) oyunda, oyun sona qədər oynananda verilir.
 * Gün gecə 00:00-da Bakı vaxtı ilə başlayır.
 *
 * Gün bitəndə (finalizePastDays): ilk 3 yerin sahibinə endirim kodu verilir, qaliblər
 * nard_daily_winners cədvəlinə yazılır, günün nəticələri isə silinir — yeni gün sıfırdan başlayır.
 */
class NardLeaderboard
{
    public const GAME = 'nard_pvp';
    private const TZ = 'Asia/Baku';

    /** Günün başlanğıcı (0 — bu gün, 1 — dünən), tətbiqin saat qurşağında */
    public static function dayStart(int $daysAgo = 0): CarbonImmutable
    {
        return CarbonImmutable::now(self::TZ)->startOfDay()->subDays($daysAgo)->setTimezone(config('app.timezone'));
    }

    /** Bu günün xal cədvəli */
    public static function today(int $limit = 10): Collection
    {
        return self::board(self::dayStart(), $limit);
    }

    /** Verilən günün xal cədvəli: [user_id, points, wins, games, user] */
    private static function board(CarbonImmutable $from, int $limit): Collection
    {
        return GameResult::query()
            ->where('game', self::GAME)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $from->addDay())
            ->selectRaw('user_id, SUM(points) as points, SUM(won) as wins, COUNT(*) as games')
            ->groupBy('user_id')
            ->having('points', '>', 0)
            ->orderByDesc('points')
            ->orderByDesc('wins')
            ->orderBy('user_id')
            ->limit($limit)
            ->with('user:id,name')
            ->get();
    }

    /** Dünənin qalibləri (yekun vurulmuş gündən) */
    public static function yesterdayWinners(): Collection
    {
        return NardDailyWinner::with(['user:id,name', 'prize'])
            ->whereDate('day', self::dayStart(1)->setTimezone(self::TZ)->toDateString())
            ->orderBy('place')
            ->get();
    }

    /** Oyunçunun bu günkü nəticəsi */
    public static function myToday(int $userId): array
    {
        $row = GameResult::where('game', self::GAME)->where('user_id', $userId)
            ->where('created_at', '>=', self::dayStart())
            ->selectRaw('COUNT(*) as games, COALESCE(SUM(won),0) as wins, COALESCE(SUM(points),0) as points')
            ->first();

        return ['games' => (int) $row->games, 'wins' => (int) $row->wins, 'points' => (int) $row->points];
    }

    /**
     * Bitmiş günlərə yekun vurur: hər bitmiş günün ilk 3 yerinə endirim kodu verir,
     * qalibləri yazır və həmin günün nəticələrini silir. Təkrar çağırılsa heç nə dəyişmir.
     * Səhifə açılanda da çağırılır — serverdə cron olmasa da gün vaxtında bağlanır.
     */
    public static function finalizePastDays(): void
    {
        $current = self::dayStart();
        $oldest = GameResult::where('game', self::GAME)->where('created_at', '<', $current)->min('created_at');
        if (! $oldest) {
            return;
        }

        // eyni anda iki sorğu yekun vurmasın
        Cache::lock('nard-daily-finalize', 30)->get(function () use ($current, $oldest) {
            $day = CarbonImmutable::parse($oldest)->setTimezone(self::TZ)->startOfDay()->setTimezone(config('app.timezone'));
            for (; $day < $current; $day = $day->addDay()) {
                DB::transaction(function () use ($day) {
                    $date = $day->setTimezone(self::TZ)->toDateString();
                    if (! NardDailyWinner::whereDate('day', $date)->exists()) {
                        self::awardDay($day, $date);
                    }
                    // günün nəticələri silinir — yeni gün sıfırdan başlayır
                    GameResult::where('game', self::GAME)->where('created_at', '<', $day->addDay())->delete();
                });
            }
        });
    }

    private static function awardDay(CarbonImmutable $from, string $date): void
    {
        $prizes = config('games.nard.daily_prizes', []);
        $validDays = (int) config('games.nard.prize_valid_days', 7);

        foreach (self::board($from, count($prizes)) as $i => $row) {
            $place = $i + 1;
            $prize = $prizes[$place] ?? null;
            $spin = $prize ? WheelSpin::create([
                'user_id' => $row->user_id,
                'game' => 'nard',
                'prize_key' => $prize['key'],
                'prize_label' => $prize['label'] . ' (' . self::placeName($place) . ' yer)',
                'code' => WheelSpin::newCode(),
                'spun_on' => now(self::TZ)->toDateString(),
                'expires_at' => now()->addDays($validDays),
            ]) : null;

            NardDailyWinner::create([
                'day' => $date,
                'place' => $place,
                'user_id' => $row->user_id,
                'points' => (int) $row->points,
                'wins' => (int) $row->wins,
                'wheel_spin_id' => $spin?->id,
            ]);
        }
    }

    /** 1 → "1-ci", 3 → "3-cü" */
    public static function placeName(int $place): string
    {
        return [1 => '1-ci', 2 => '2-ci', 3 => '3-cü'][$place] ?? "{$place}.";
    }

    /**
     * Bitmiş oyunun xallarını yazır. Xal yalnız oyun sona qədər oynananda verilir (təslim olma və ya
     * çıxma xal vermir) və eyni iki oyunçu arasında gündə config('games.nard.pair_daily_limit') oyuna qədər.
     * Qaytarır: qalibin aldığı xal (0 — xal verilmədi).
     */
    public static function award(NardMatch $match): int
    {
        if ($match->end_reason !== 'finished' || $match->winner === null) {
            return 0;
        }

        $pair = [(int) $match->player0_id, (int) $match->player1_id];
        $rated = NardMatch::where('status', 'finished')->where('end_reason', 'finished')
            ->where('id', '!=', $match->id)
            ->where('updated_at', '>=', self::dayStart())
            ->where(fn ($q) => $q->where(fn ($q) => $q->where('player0_id', $pair[0])->where('player1_id', $pair[1]))
                ->orWhere(fn ($q) => $q->where('player0_id', $pair[1])->where('player1_id', $pair[0])))
            ->count();
        if ($rated >= (int) config('games.nard.pair_daily_limit', 3)) {
            return 0;
        }

        $points = (int) config($match->mars ? 'games.nard.points_mars' : 'games.nard.points_win');
        $winnerId = $pair[(int) $match->winner];
        $loserId = $pair[1 - (int) $match->winner];
        GameResult::create(['user_id' => $winnerId, 'game' => self::GAME, 'won' => true, 'mars' => $match->mars, 'points' => $points]);
        GameResult::create(['user_id' => $loserId, 'game' => self::GAME, 'won' => false, 'mars' => $match->mars, 'points' => 0]);

        return $points;
    }
}
