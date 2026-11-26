<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\GameResult;
use App\Models\PointLog;
use App\Models\User;
use App\Models\WheelSpin;
use App\Services\SufreGame;
use App\Services\Points;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Süfrəni doldur: hamı oynaya bilər; nəticə, liderlər cədvəli, xal bonusu və hədiyyələr — təsdiqlənmiş istifadəçilərə.
 * Hesabı server özü hesablayır (App\Services\SufreGame).
 */
class SufreController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        $verified = $user && $user->isVerified();

        return view('frontend.games.sufre', [
            'leaders' => self::leaders(10),
            'best' => $verified ? $this->best($user->id) : null,
            'rank' => $verified ? self::rank($user->id) : null,
            // xal bonusu yalnız qonaqlara (ofisiant və admin oynaya bilər, amma xal almır)
            'bonus' => $this->bonusInfo($verified && $user->earnsGamePoints() ? $user->id : null),
            'prizes' => self::prizes(),
            'prizeLeft' => $verified ? $this->prizesLeft($user->id) : null,
        ]);
    }

    /** Yeni oyun: toxum və başlanğıc vaxtı sessiyada qalır, əşyaların siyahısı brauzerə gedir */
    public function start(Request $request): JsonResponse
    {
        $seed = random_int(1, 0x7ffffffe);
        $request->session()->put('sufre', ['seed' => $seed, 'started' => microtime(true)]);

        return response()->json(['items' => SufreGame::items($seed, (float) config('games.sufre.max_seconds'))]);
    }

    /** Oyun bitdi: sininin yerlərinə görə hesab serverdə hesablanır və yazılır (yalnız təsdiqlənmiş istifadəçi) */
    public function result(Request $request): JsonResponse
    {
        $data = $request->validate([
            'basket' => ['present', 'array', 'max:3000'],
            'basket.*' => ['nullable', 'numeric', 'min:0', 'max:' . SufreGame::WIDTH],
        ]);

        // nəticə yalnız bu sessiyada başlamış oyun üçün və bir dəfə yazılır
        $game = $request->session()->pull('sufre');
        if (! $game) {
            return $this->reject($request, 'oyun başlamayıb');
        }
        $cfg = config('games.sufre');
        $items = SufreGame::items((int) $game['seed'], (float) $cfg['max_seconds']);
        if (count($data['basket']) > count($items)) {
            return $this->reject($request, 'əşya sayı uyğun deyil');
        }
        $outcome = SufreGame::referee($items, $data['basket'], microtime(true) - (float) $game['started'], $cfg);
        if (isset($outcome['problem'])) {
            return $this->reject($request, $outcome['problem']);
        }

        $user = $request->user();
        $before = $this->best($user->id);
        GameResult::create(['user_id' => $user->id, 'game' => 'sufre', 'won' => false, 'points' => min($outcome['score'], 65535)]);

        return response()->json([
            'saved' => true,
            'score' => $outcome['score'],
            'best' => max($before, $outcome['score']),
            'record' => $outcome['score'] > $before && $outcome['score'] > 0,
            'bonus' => $this->awardBonus($user, $outcome['score']),
            'bonusInfo' => $this->bonusInfo($user->earnsGamePoints() ? $user->id : null),
        ]);
    }

    /**
     * "Hədiyyəni götür": oyun dayandırılan ana (until) qədərki gedişata görə hesab serverdə hesablanır,
     * çatılan ən böyük hədiyyə üçün kod yaradılır və oyun bitir. Gündə config('games.sufre.prizes_per_day') dəfə.
     */
    public function prize(Request $request): JsonResponse
    {
        $data = $request->validate([
            'basket' => ['present', 'array', 'max:3000'],
            'basket.*' => ['nullable', 'numeric', 'min:0', 'max:' . SufreGame::WIDTH],
            'until' => ['required', 'numeric', 'min:0'],
        ]);

        $game = $request->session()->get('sufre');
        if (! $game) {
            return $this->reject($request, 'oyun başlamayıb');
        }
        $cfg = config('games.sufre');
        $items = SufreGame::items((int) $game['seed'], (float) $cfg['max_seconds']);
        if (count($data['basket']) > count($items)) {
            return $this->reject($request, 'əşya sayı uyğun deyil');
        }
        $outcome = SufreGame::referee($items, $data['basket'], microtime(true) - (float) $game['started'], $cfg, (float) $data['until']);
        if (isset($outcome['problem'])) {
            return $this->reject($request, $outcome['problem']);
        }
        if ($outcome['bombs'] > 0) {
            return $this->reject($request, 'kömür siniyə düşüb — oyun bitib');
        }

        $score = $outcome['score'];
        $prize = collect(self::prizes())->filter(fn ($p, $min) => $score >= $min)->last();
        if (! $prize) {
            return response()->json(['message' => 'Bu xal hədiyyə üçün kifayət deyil.'], 422);
        }
        $user = $request->user();

        return DB::transaction(function () use ($request, $user, $score, $prize) {
            User::whereKey($user->id)->lockForUpdate()->first(); // eyni anda iki sorğu limiti keçməsin
            if ($this->prizesLeft($user->id) <= 0) {
                return response()->json(['message' => 'Bu günün hədiyyəsini artıq götürmüsünüz. Sabah yenə gəlin!'], 429);
            }
            $request->session()->forget('sufre'); // hədiyyə götürüldü — oyun bitdi, bu gedişatla ikinci dəfə olmaz

            $before = $this->best($user->id);
            GameResult::create(['user_id' => $user->id, 'game' => 'sufre', 'won' => true, 'points' => min($score, 65535)]);
            $spin = WheelSpin::create([
                'user_id' => $user->id,
                'game' => 'sufre',
                'prize_key' => $prize['key'],
                'prize_label' => $prize['label'],
                'code' => WheelSpin::newCode(),
                'spun_on' => $this->today(),
                'expires_at' => now()->addDays((int) config('games.sufre.code_valid_days', 1)),
            ]);
            $key = $this->prizesTakenKey($user->id);
            Cache::put($key, (int) Cache::get($key, 0) + 1, now('Asia/Baku')->endOfDay());

            return response()->json([
                'prize' => [
                    'label' => $spin->prize_label,
                    'code' => $spin->code,
                    'expires' => $spin->expires_at?->timezone('Asia/Baku')->format('d.m.Y H:i'),
                ],
                'score' => $score,
                'best' => max($before, $score),
                'bonus' => $this->awardBonus($user, $score),
            ]);
        });
    }

    /** [xal => ['key', 'label']], xala görə artan sırada */
    public static function prizes(): array
    {
        $prizes = (array) config('games.sufre.prizes', []);
        ksort($prizes);

        return $prizes;
    }

    /** Bu gün daha neçə hədiyyə götürmək olar */
    private function prizesLeft(int $userId): int
    {
        $inDb = WheelSpin::where('game', 'sufre')->where('user_id', $userId)->whereDate('spun_on', $this->today())->count();
        // kupon bazadan silinsə də (müddəti bitəndə) bu günün sayı keşdə qalır ki, limit sıfırlanmasın
        $taken = max($inDb, (int) Cache::get($this->prizesTakenKey($userId), 0));

        return max(0, (int) config('games.sufre.prizes_per_day', 1) - $taken);
    }

    private function prizesTakenKey(int $userId): string
    {
        return "sufre_prizes_taken:{$userId}:" . $this->today();
    }

    private function today(): string
    {
        return now('Asia/Baku')->toDateString();
    }

    private function reject(Request $request, string $problem): JsonResponse
    {
        logger()->warning('Süfrəni doldur: oyun rədd edildi', [
            'user_id' => $request->user()?->id,
            'reason' => $problem,
            'ip' => $request->ip(),
        ]);

        return response()->json(['message' => 'Oyun təsdiqlənmədi. Yenidən oynayın.'], 422);
    }

    /** Bonus mərhələləri: [xal => balansa yazılan xal], artan sıra ilə */
    public static function bonusTiers(): array
    {
        $tiers = array_filter((array) config('games.sufre.bonus.tiers', []), fn ($p, $s) => (int) $s > 0 && (float) $p > 0, ARRAY_FILTER_USE_BOTH);
        ksort($tiers);

        return array_map('floatval', $tiers);
    }

    /** Bonusu son 48 saatda alınmış mərhələlər (xal tarixçəsindən) — onlara görə xal hələ verilmir */
    private function bonusDone(int $userId): array
    {
        return Points::recentStages($userId, 'sufre', 'tier');
    }

    /** Səhifə üçün: mərhələlər və bu gün alınıb-alınmadığı ($userId null — xal almır, hamısı "alınıb" sayılır) */
    private function bonusInfo(?int $userId): array
    {
        $done = $userId ? $this->bonusDone($userId) : null;

        return ['tiers' => collect(self::bonusTiers())->map(fn ($points, $score) => [
            'score' => $score, 'points' => $points, 'done' => $done === null || in_array($score, $done, true),
        ])->values()->all()];
    }

    /**
     * Bir oyunda çatılan və bu gün hələ alınmamış hər mərhələ üçün balansa xal yazır.
     * Qaytarır: yazılan xalların cəmi. Ofisiant və admin oyundan xal qazanmır.
     */
    private function awardBonus(User $user, int $score): float
    {
        $reached = array_filter(self::bonusTiers(), fn ($tier) => $score >= $tier, ARRAY_FILTER_USE_KEY);
        if (! $reached || ! $user->earnsGamePoints()) {
            return 0;
        }
        $userId = $user->id;

        return DB::transaction(function () use ($userId, $reached, $score) {
            User::whereKey($userId)->lockForUpdate()->first(); // eyni anda iki sorğu eyni mərhələni iki dəfə verməsin
            $done = $this->bonusDone($userId);
            $total = 0.0;
            foreach ($reached as $tier => $points) {
                if (in_array($tier, $done, true)) {
                    continue;
                }
                User::whereKey($userId)->increment('points', $points);
                Points::log($userId, $points, 'sufre', ['score' => $score, 'tier' => $tier]);
                $total += $points;
            }

            return $total;
        });
    }

    /** Liderlər: hər oyunçunun ən yüksək nəticəsi */
    public static function leaders(int $limit = 10): Collection
    {
        return GameResult::query()
            ->where('game', 'sufre')
            ->selectRaw('user_id, MAX(points) as points, COUNT(*) as games')
            ->groupBy('user_id')
            ->having('points', '>', 0)
            ->orderByDesc('points')
            ->limit($limit)
            ->with('user:id,name')
            ->get();
    }

    /** Liderlər cədvəlində yer (rekordu olmayan üçün null): rekordu daha yüksək olan oyunçuların sayı + 1 */
    public static function rank(int $userId): ?int
    {
        $best = (int) GameResult::where('game', 'sufre')->where('user_id', $userId)->max('points');
        if ($best <= 0) {
            return null;
        }
        $above = GameResult::where('game', 'sufre')->where('user_id', '!=', $userId)
            ->select('user_id')->groupBy('user_id')->havingRaw('MAX(points) > ?', [$best])->get()->count();

        return $above + 1;
    }

    private function best(int $userId): int
    {
        return (int) GameResult::where('game', 'sufre')->where('user_id', $userId)->max('points');
    }
}
