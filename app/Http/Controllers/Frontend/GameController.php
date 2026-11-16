<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\GameResult;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\WheelSpin;
use App\Services\BakuWeather;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Oyunlar: hamı oynaya bilər, xal və hədiyyələr yalnız Telegram ilə təsdiqlənmiş istifadəçilərə yazılır.
 */
class GameController extends Controller
{
    public function index(): View
    {
        return view('frontend.games.index', [
            'leaders' => $this->leaders(10),
            'stackLeaders' => $this->stackLeaders(10),
            'onlineUsers' => User::online()->orderByDesc('last_seen_at')->limit(30)->get(['id', 'name', 'last_seen_at']),
            'onlineCount' => User::online()->count(),
        ]);
    }

    public function stack(Request $request): View
    {
        $user = $request->user();

        $verified = $user && $user->hasVerifiedPhone();

        return view('frontend.games.stack', [
            'leaders' => $this->stackLeaders(10),
            'best' => $verified ? $this->stackBest($user->id) : null,
            'prizes' => $this->stackPrizes(),
            'prizeLeft' => $verified ? $this->stackPrizesLeft($user->id) : null,
            // ?hava=rain kimi baxış rejimi; yoxsa canlı hava səhifə açılandan sonra ayrıca sorğu ilə gəlir
            // (xidmət bir neçə saniyə cavab verə bilər — səhifə bunu gözləməsin)
            'weather' => BakuWeather::preview($request->query('hava')),
        ]);
    }

    /** Bakının indiki havası (keşdən) — Stəkan qülləsinin hava effektləri üçün */
    public function stackWeather(BakuWeather $weather): JsonResponse
    {
        return response()->json(['weather' => $weather->current()]);
    }

    /** Yeni oyun başladı — vaxtı sessiyada saxlayırıq (hədiyyə və nəticəni yoxlamaq üçün) */
    public function stackStart(Request $request): JsonResponse
    {
        $request->session()->put('stack_started_at', microtime(true));
        $request->session()->forget('stack_prize_claimed');

        return response()->json(['ok' => true]);
    }

    /** Oyunun gedişatı: hər qonan stəkan üçün [başlanğıcdan keçən ms, sapma px] */
    private const LOG_RULES = [
        'log' => ['required', 'array', 'max:1000'],
        'log.*' => ['required', 'array', 'size:2'],
        'log.*.0' => ['required', 'integer', 'min:0'],
        'log.*.1' => ['required', 'numeric'],
    ];

    /**
     * Botlara qarşı yoxlama. Oyun brauzerdə oynanır, ona görə server hər stəkanın nə vaxt və necə
     * qonduğunu yoxlayır: insan bunu edə bilməz, bot isə adətən buradan ilişir.
     * Qaytarır: null — hər şey qaydasındadır, yoxsa rədd səbəbi (loga yazılır, oyunçuya göstərilmir).
     */
    private function stackLogProblem(array $log, int $score, ?float $started): ?string
    {
        $cfg = config('games.stack');
        if (! $started) {
            return 'oyun başlamayıb';
        }
        if (count($log) !== $score) {
            return 'stəkan sayı xala uyğun deyil';
        }
        $elapsedMs = (microtime(true) - $started) * 1000;
        if ($elapsedMs < $score * (float) $cfg['min_seconds_per_glass'] * 1000) {
            return 'çox tez';
        }
        if ($score === 0) {
            return null;
        }

        $times = array_map(fn ($e) => (int) $e[0], $log);
        $offsets = array_map(fn ($e) => (float) $e[1], $log);
        if (end($times) > $elapsedMs + 3000) {
            return 'vaxt serverdəkindən uzundur';
        }

        // stəkanlar arası fasilə: stəkan yuxarıdan düşüb qonana qədər insan da, bot da bundan tez edə bilməz
        $gaps = [];
        $prev = 0;
        foreach ($times as $t) {
            if ($t - $prev < (int) $cfg['min_glass_interval_ms']) {
                return 'stəkanlar arası fasilə çox qısadır';
            }
            $gaps[] = $t - $prev;
            $prev = $t;
        }

        // sapma dayaq sahəsindən böyük ola bilməz — belə stəkan qülləni aşırardı
        foreach ($offsets as $d) {
            if (abs($d) > (float) $cfg['max_offset_px']) {
                return 'mümkünsüz sapma';
            }
        }

        // insan kimi oynamayan: çox bərabər ritm və ya millimetrlə dəqiq atışlar
        if ($score >= (int) $cfg['bot_check_from']) {
            $mean = array_sum($gaps) / count($gaps);
            $var = array_sum(array_map(fn ($g) => ($g - $mean) ** 2, $gaps)) / count($gaps);
            if ($mean > 0 && sqrt($var) / $mean < (float) $cfg['min_rhythm_spread']) {
                return 'ritm çox bərabərdir';
            }
            $exact = count(array_filter($offsets, fn ($d) => abs($d) < 0.3));
            if ($exact / count($offsets) > (float) $cfg['max_exact_share']) {
                return 'atışlar insan üçün həddən artıq dəqiqdir';
            }
        }

        return null;
    }

    private function rejectStackGame(Request $request, string $problem): JsonResponse
    {
        logger()->warning('Stəkan qülləsi: oyun rədd edildi', [
            'user_id' => $request->user()?->id,
            'score' => $request->input('score'),
            'reason' => $problem,
            'ip' => $request->ip(),
        ]);

        return response()->json(['message' => 'Oyun təsdiqlənmədi. Yenidən oynayın.'], 422);
    }

    /**
     * "Hədiyyəni qəbul et": oyunçunun xalına uyğun hədiyyə üçün kod yaradır, oyunun nəticəsini yazır.
     * Hər oyunda bir dəfə, gündə config('games.stack.prizes_per_day') dəfə.
     */
    public function stackPrize(Request $request): JsonResponse
    {
        $data = $request->validate(['score' => ['required', 'integer', 'min:1', 'max:1000']] + self::LOG_RULES);
        $score = (int) $data['score'];
        $user = $request->user();

        $prize = collect($this->stackPrizes())->filter(fn ($p, $min) => $score >= $min)->last();
        if (! $prize) {
            return response()->json(['message' => 'Bu xal hədiyyə üçün kifayət deyil.'], 422);
        }

        // Oyun bu sessiyada başlamalı, bu oyunda hədiyyə hələ götürülməməli və gedişat insan oyununa oxşamalıdır
        if ($request->session()->get('stack_prize_claimed')) {
            return $this->rejectStackGame($request, 'bu oyunda hədiyyə artıq götürülüb');
        }
        if ($problem = $this->stackLogProblem($data['log'], $score, $request->session()->get('stack_started_at'))) {
            return $this->rejectStackGame($request, $problem);
        }
        $request->session()->put('stack_prize_claimed', true);

        return DB::transaction(function () use ($user, $score, $prize) {
            User::whereKey($user->id)->lockForUpdate()->first();

            if ($this->stackPrizesLeft($user->id) <= 0) {
                return response()->json(['message' => 'Bu günün hədiyyəsini artıq götürmüsünüz. Sabah yenə gəlin!'], 429);
            }

            GameResult::create(['user_id' => $user->id, 'game' => 'stack', 'won' => true, 'points' => $score]);

            $spin = WheelSpin::create([
                'user_id' => $user->id,
                'game' => 'stack',
                'prize_key' => $prize['key'],
                'prize_label' => $prize['label'],
                'code' => $this->newCode(),
                'spun_on' => $this->today(),
                'expires_at' => now()->addDays((int) config('games.wheel.code_valid_days', 7)),
            ]);

            return response()->json(['prize' => $this->spinPayload($spin), 'best' => $this->stackBest($user->id)]);
        });
    }

    /** [xal => ['key', 'label']], xala görə artan sırada */
    private function stackPrizes(): array
    {
        $prizes = config('games.stack.prizes', []);
        ksort($prizes);

        return $prizes;
    }

    private function stackPrizesLeft(int $userId): int
    {
        $taken = WheelSpin::where('game', 'stack')->where('user_id', $userId)->whereDate('spun_on', $this->today())->count();

        return max(0, (int) config('games.stack.prizes_per_day', 1) - $taken);
    }

    /** Stəkan qülləsi: oyunun hesabını yazır, ən yaxşı nəticəni qaytarır (yalnız təsdiqlənmiş istifadəçi) */
    public function stackResult(Request $request): JsonResponse
    {
        $data = $request->validate(['score' => ['required', 'integer', 'min:0', 'max:1000'], 'log' => ['present', 'array', 'max:1000']] + array_diff_key(self::LOG_RULES, ['log' => 1]));

        // nəticə yalnız bu sessiyada başlamış oyun üçün, bir dəfə və insan oyununa oxşayırsa yazılır
        $started = $request->session()->pull('stack_started_at');
        if ($problem = $this->stackLogProblem($data['log'], (int) $data['score'], $started)) {
            return $this->rejectStackGame($request, $problem);
        }

        $before = $this->stackBest($request->user()->id);

        GameResult::create([
            'user_id' => $request->user()->id,
            'game' => 'stack',
            'won' => false,
            'points' => $data['score'],
        ]);

        return response()->json([
            'saved' => true,
            'best' => max($before, $data['score']),
            'record' => $data['score'] > $before && $data['score'] > 0,
        ]);
    }

    public function nard(Request $request): View
    {
        $user = $request->user();

        return view('frontend.games.nard', [
            'leaders' => $this->leaders(10),
            'myStats' => $user && $user->hasVerifiedPhone() ? $this->stats($user->id) : null,
        ]);
    }

    /** Nərd oyununun nəticəsini yazır (yalnız təsdiqlənmiş istifadəçi) */
    public function nardResult(Request $request): JsonResponse
    {
        $data = $request->validate([
            'won' => ['required', 'boolean'],
            'mars' => ['sometimes', 'boolean'],
        ]);

        $won = (bool) $data['won'];
        $mars = $won && ($data['mars'] ?? false);
        $points = $won ? config($mars ? 'games.nard.points_mars' : 'games.nard.points_win') : 0;

        GameResult::create([
            'user_id' => $request->user()->id,
            'game' => 'nard',
            'won' => $won,
            'mars' => $mars,
            'points' => $points,
        ]);

        return response()->json(['saved' => true, 'points' => $points, 'stats' => $this->stats($request->user()->id)]);
    }

    public function wheel(Request $request): View
    {
        $user = $request->user();
        $verified = $user && $user->hasVerifiedPhone();

        return view('frontend.games.wheel', [
            'segments' => $this->segments(),
            'verified' => $verified,
            'attempts' => $this->attempts(),
            'state' => $verified ? $this->wheelState($user->id) : null,
        ]);
    }

    /**
     * Çarxı fırladır. Nəticəni server seçir (brauzerdə dəyişdirmək olmur).
     * Təsdiqlənmiş istifadəçinin gündə 3 cəhdi var; hər fırlatmadan sonra ya hədiyyəni götürür,
     * ya da yenidən fırladır (əvvəlki nəticə itir). Qonaqlar üçün sınaq fırlatmasıdır.
     */
    public function spin(Request $request): JsonResponse
    {
        $segments = $this->segments();
        $user = $request->user();

        if (! $user || ! $user->hasVerifiedPhone()) {
            $index = $this->pickIndex($segments);

            return response()->json([
                'index' => $index,
                'label' => $segments[$index]['label'],
                'prize' => $segments[$index]['prize'],
                'demo' => true,
            ]);
        }

        return DB::transaction(function () use ($user, $segments) {
            User::whereKey($user->id)->lockForUpdate()->first(); // eyni anda iki fırlatmanın qarşısını alır
            $state = $this->wheelState($user->id);

            if ($state['claimed']) {
                return response()->json(['message' => 'Bu günün hədiyyəsini artıq götürmüsünüz. Sabah yenə gəlin!', 'state' => $state], 429);
            }
            if ($state['attemptsLeft'] <= 0) {
                return response()->json(['message' => 'Bu gün üçün cəhdləriniz bitdi. Sabah yenə gəlin!', 'state' => $state], 429);
            }

            $index = $this->pickIndex($segments);
            $segment = $segments[$index];

            // Fırlatma bazaya yazılmır: cəhd sayı və son nəticə serverin keşində saxlanılır
            $round = $this->round($user->id);
            $round['count']++;
            $round['last'] = ['key' => $segment['key'], 'label' => $segment['label']];
            $this->saveRound($user->id, $round);

            return response()->json([
                'index' => $index,
                'demo' => false,
                'label' => $segment['label'],
                'prize' => (bool) $segment['prize'],
                'state' => $this->wheelState($user->id),
            ]);
        });
    }

    /** "Hədiyyəni götür": son fırlatmanın hədiyyəsi üçün kod yaradır və bu günün raundunu bağlayır */
    public function claim(Request $request): JsonResponse
    {
        $user = $request->user();

        return DB::transaction(function () use ($user) {
            User::whereKey($user->id)->lockForUpdate()->first();
            $state = $this->wheelState($user->id);

            if ($state['claimed']) {
                return response()->json(['state' => $state]); // artıq götürülüb — eyni kodu qaytarırıq
            }

            $round = $this->round($user->id);
            $last = $round['last'];
            if (! $last || ! $this->isPrize($last['key'])) {
                return response()->json(['message' => 'Götürüləcək hədiyyə yoxdur. Çarxı fırladın.', 'state' => $state], 422);
            }

            // Bazaya yalnız götürülən hədiyyə yazılır
            WheelSpin::create([
                'user_id' => $user->id,
                'game' => 'wheel',
                'prize_key' => $last['key'],
                'prize_label' => $last['label'],
                'code' => $this->newCode(),
                'spun_on' => $this->today(),
                'expires_at' => now()->addDays((int) config('games.wheel.code_valid_days', 7)),
            ]);

            $round['last'] = null;
            $this->saveRound($user->id, $round);

            return response()->json(['state' => $this->wheelState($user->id)]);
        });
    }

    /**
     * Bu günün vəziyyəti: neçə cəhd qalıb, son nəticə, götürülmüş hədiyyə.
     */
    private function wheelState(int $userId): array
    {
        $claimed = WheelSpin::where('game', 'wheel')->where('user_id', $userId)->whereDate('spun_on', $this->today())->whereNotNull('code')->latest('id')->first();
        $round = $this->round($userId);
        $last = $round['last'];

        return [
            'attempts' => $this->attempts(),
            'attemptsLeft' => $claimed ? 0 : max(0, $this->attempts() - $round['count']),
            'last' => $last && ! $claimed ? ['label' => $last['label'], 'prize' => $this->isPrize($last['key'])] : null,
            'claimed' => $claimed ? $this->spinPayload($claimed) : null,
        ];
    }

    /** Bu günün raundu (keşdə): ['count' => fırlatma sayı, 'last' => son nəticə və ya null] */
    private function round(int $userId): array
    {
        return Cache::get($this->roundKey($userId), ['count' => 0, 'last' => null]);
    }

    private function saveRound(int $userId, array $round): void
    {
        // Bakı vaxtı ilə gün bitəndə özü silinir — sabah yenə 3 cəhd
        Cache::put($this->roundKey($userId), $round, now('Asia/Baku')->endOfDay());
    }

    private function roundKey(int $userId): string
    {
        return "wheel_round:{$userId}:" . $this->today();
    }

    private function attempts(): int
    {
        return max(1, (int) config('games.wheel.attempts_per_day', 3));
    }

    private function isPrize(string $key): bool
    {
        return (bool) (collect($this->segments())->firstWhere('key', $key)['prize'] ?? false);
    }

    /* ---------------------------------------------------------------- */

    private function segments(): array
    {
        return array_values(config('games.wheel.segments'));
    }

    private function pickIndex(array $segments): int
    {
        $total = array_sum(array_column($segments, 'weight'));
        $roll = random_int(1, max(1, $total));

        foreach ($segments as $i => $segment) {
            $roll -= $segment['weight'];
            if ($roll <= 0) {
                return $i;
            }
        }

        return array_key_last($segments);
    }

    private function newCode(): string
    {
        do {
            $code = 'QP-' . strtoupper(Str::random(6));
        } while (WheelSpin::where('code', $code)->exists());

        return $code;
    }

    private function spinPayload(WheelSpin $spin): array
    {
        return [
            'label' => $spin->prize_label,
            'prize' => (bool) $spin->code,
            'code' => $spin->code,
            'expires' => $spin->expires_at?->timezone('Asia/Baku')->format('d.m.Y'),
            // "Hədiyyəni götür" basılan an (Bakı vaxtı)
            'claimed_at' => $spin->created_at?->timezone('Asia/Baku')->format('d.m.Y H:i'),
        ];
    }

    private function today(): string
    {
        return now('Asia/Baku')->toDateString();
    }

    /** Nərd liderləri — iki nəfərlik oyun səhifəsi üçün */
    public function nardLeaders(int $limit = 10): Collection
    {
        return $this->leaders($limit);
    }

    /** Nərd rekordlar cədvəli: ən çox xal toplayanlar */
    private function leaders(int $limit = 10): Collection
    {
        return GameResult::query()
            ->where('game', 'nard')
            ->selectRaw('user_id, SUM(points) as points, SUM(won) as wins, COUNT(*) as games')
            ->groupBy('user_id')
            ->having('points', '>', 0)
            ->orderByDesc('points')
            ->orderByDesc('wins')
            ->limit($limit)
            ->with('user:id,name')
            ->get();
    }

    /** Stəkan qülləsi liderləri: hər oyunçunun ən yüksək nəticəsi */
    private function stackLeaders(int $limit = 10): Collection
    {
        return GameResult::query()
            ->where('game', 'stack')
            ->selectRaw('user_id, MAX(points) as points, COUNT(*) as games')
            ->groupBy('user_id')
            ->having('points', '>', 0)
            ->orderByDesc('points')
            ->limit($limit)
            ->with('user:id,name')
            ->get();
    }

    private function stackBest(int $userId): int
    {
        return (int) GameResult::where('game', 'stack')->where('user_id', $userId)->max('points');
    }

    private function stats(int $userId): array
    {
        $row = GameResult::where('game', 'nard')->where('user_id', $userId)
            ->selectRaw('COUNT(*) as games, COALESCE(SUM(won),0) as wins, COALESCE(SUM(points),0) as points')
            ->first();

        return ['games' => (int) $row->games, 'wins' => (int) $row->wins, 'points' => (int) $row->points];
    }
}
