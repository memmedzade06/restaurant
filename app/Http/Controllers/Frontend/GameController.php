<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\GameResult;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\WheelSpin;
use App\Services\BakuWeather;
use App\Services\Crossword;
use App\Services\NardLeaderboard;
use App\Services\StackRecordNotifier;
use App\Support\DailyPrize;
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
        NardLeaderboard::finalizePastDays(); // bitmiş günə yekun (cron olmasa da)

        return view('frontend.games.index', [
            'leaders' => NardLeaderboard::today(),
            'lastWinners' => NardLeaderboard::yesterdayWinners(),
            'stackLeaders' => $this->stackLeaders(10),
            'onlineUsers' => User::online()->orderByDesc('last_seen_at')->limit(30)->get(['id', 'name', 'last_seen_at']),
            'onlineCount' => User::online()->count(),
        ]);
    }

    public function stack(Request $request): View
    {
        $user = $request->user();

        $verified = $user && $user->isVerified();

        return view('frontend.games.stack', [
            'leaders' => $this->stackLeaders(10),
            'best' => $verified ? $this->stackBest($user->id) : null,
            'prizes' => $this->stackPrizes(),
            'prizeLeft' => $verified ? $this->stackPrizesLeft($user->id) : null,
            'bonus' => $this->stackBonusConfig() + ['left' => $verified ? $this->stackBonusLeft($user->id) : 0],
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
    public function stackPrize(Request $request, StackRecordNotifier $notifier): JsonResponse
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

        return DB::transaction(function () use ($user, $score, $prize, $notifier) {
            User::whereKey($user->id)->lockForUpdate()->first();

            if ($this->stackPrizesLeft($user->id) <= 0) {
                return response()->json(['message' => 'Bu günün hədiyyəsini artıq götürmüsünüz. Sabah yenə gəlin!'], 429);
            }

            $overtaken = $notifier->overtaken($user, $score); // yazmazdan əvvəl: kimisə birincilikdən salırmı
            GameResult::create(['user_id' => $user->id, 'game' => 'stack', 'won' => true, 'points' => $score]);
            if ($overtaken) {
                $notifier->notify($overtaken[0], $overtaken[1], $user, $score, $prize['label']);
            }

            $spin = WheelSpin::create([
                'user_id' => $user->id,
                'game' => 'stack',
                'prize_key' => $prize['key'],
                'prize_label' => $prize['label'],
                'code' => $this->newCode(),
                'spun_on' => $this->today(),
                'expires_at' => now()->addDays((int) config('games.stack.code_valid_days', 1)),
            ]);
            $this->countStackPrize($user->id);
            $bonus = $this->awardStackBonus($user->id, $score);

            return response()->json(['prize' => $this->spinPayload($spin), 'best' => $this->stackBest($user->id), 'bonus' => $bonus]);
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
        $inDb = WheelSpin::where('game', 'stack')->where('user_id', $userId)->whereDate('spun_on', $this->today())->count();
        // istifadə olunan kupon bazadan silinir — bu günün sayı keşdə də saxlanılır ki, limit sıfırlanmasın
        $taken = max($inDb, (int) Cache::get($this->stackTakenKey($userId), 0));

        return max(0, (int) config('games.stack.prizes_per_day', 1) - $taken);
    }

    private function stackTakenKey(int $userId): string
    {
        return "stack_prizes_taken:{$userId}:" . $this->today();
    }

    /** Bu gün götürülən Stəkan qülləsi hədiyyələrinin sayını artırır (Bakı vaxtı ilə gün bitəndə silinir) */
    private function countStackPrize(int $userId): void
    {
        $key = $this->stackTakenKey($userId);
        Cache::put($key, (int) Cache::get($key, 0) + 1, now('Asia/Baku')->endOfDay());
    }

    /** ['score' => neçə stəkan, 'points' => neçə xal, 'per_day' => gündə neçə dəfə] */
    private function stackBonusConfig(): array
    {
        return [
            'score' => max(1, (int) config('games.stack.bonus.score', 40)),
            'points' => max(0, (float) config('games.stack.bonus.points', 0.5)),
            'per_day' => max(0, (int) config('games.stack.bonus.per_day', 2)),
        ];
    }

    private function stackBonusKey(int $userId): string
    {
        return "stack_bonus:{$userId}:" . $this->today();
    }

    /** Bu gün daha neçə dəfə bonus almaq olar */
    private function stackBonusLeft(int $userId): int
    {
        return max(0, $this->stackBonusConfig()['per_day'] - (int) Cache::get($this->stackBonusKey($userId), 0));
    }

    /**
     * Bonus: oyunçu kifayət qədər stəkan yığıbsa və bu günün limiti bitməyibsə — balansına xal yazılır.
     * Çağıran tərəf oyunun gedişatını artıq yoxlayıb. Qaytarır: yazılan xal (0 — bonus yoxdur).
     */
    private function awardStackBonus(int $userId, int $score): float
    {
        ['score' => $need, 'points' => $points] = $this->stackBonusConfig();
        if ($points <= 0 || $score < $need) {
            return 0;
        }

        return DB::transaction(function () use ($userId, $points) {
            User::whereKey($userId)->lockForUpdate()->first(); // eyni anda iki sorğu limiti keçməsin
            if ($this->stackBonusLeft($userId) <= 0) {
                return 0;
            }
            $key = $this->stackBonusKey($userId);
            Cache::put($key, (int) Cache::get($key, 0) + 1, now('Asia/Baku')->endOfDay());
            User::whereKey($userId)->increment('points', $points);

            return $points;
        });
    }

    /** Stəkan qülləsi: oyunun hesabını yazır, ən yaxşı nəticəni qaytarır (yalnız təsdiqlənmiş istifadəçi) */
    public function stackResult(Request $request, StackRecordNotifier $notifier): JsonResponse
    {
        $data = $request->validate(['score' => ['required', 'integer', 'min:0', 'max:1000'], 'log' => ['present', 'array', 'max:1000']] + array_diff_key(self::LOG_RULES, ['log' => 1]));

        // nəticə yalnız bu sessiyada başlamış oyun üçün, bir dəfə və insan oyununa oxşayırsa yazılır
        $started = $request->session()->pull('stack_started_at');
        if ($problem = $this->stackLogProblem($data['log'], (int) $data['score'], $started)) {
            return $this->rejectStackGame($request, $problem);
        }

        $before = $this->stackBest($request->user()->id);
        $overtaken = $notifier->overtaken($request->user(), (int) $data['score']); // yazmazdan əvvəl yoxlanılır

        GameResult::create([
            'user_id' => $request->user()->id,
            'game' => 'stack',
            'won' => false,
            'points' => $data['score'],
        ]);

        if ($overtaken) {
            // bu oyunda götürülmüş hədiyyə (finişdən sonra rekord üçün davam edəndə olur)
            $prize = WheelSpin::where('game', 'stack')->where('user_id', $request->user()->id)
                ->where('created_at', '>=', now()->setTimestamp((int) $started))
                ->latest('id')->value('prize_label');
            $notifier->notify($overtaken[0], $overtaken[1], $request->user(), (int) $data['score'], $prize);
        }

        return response()->json([
            'saved' => true,
            'best' => max($before, $data['score']),
            'record' => $data['score'] > $before && $data['score'] > 0,
            'bonus' => $this->awardStackBonus($request->user()->id, (int) $data['score']),
        ]);
    }

    public function nard(Request $request): View
    {
        NardLeaderboard::finalizePastDays(); // bitmiş günə yekun (cron olmasa da)
        $user = $request->user();

        return view('frontend.games.nard', [
            'leaders' => NardLeaderboard::today(),
            'lastWinners' => NardLeaderboard::yesterdayWinners(),
            'myStats' => $user && $user->isVerified() ? NardLeaderboard::myToday($user->id) : null,
        ]);
    }

    /** Nərd oyununun nəticəsini yazır (yalnız təsdiqlənmiş istifadəçi) */
    public function nardResult(Request $request): JsonResponse
    {
        $request->validate([
            'won' => ['required', 'boolean'],
            'mars' => ['sometimes', 'boolean'],
        ]);

        // Kompüterə qarşı oyun xal vermir — xal yalnız dostla oyunda (NardLeaderboard).
        // Köhnə səhifə açıq qalanda da xəta verməsin deyə ünvan saxlanılıb.
        return response()->json(['saved' => false, 'points' => 0]);
    }

    /**
     * Krossvord: hər gün hamı üçün eyni (tarixdən qurulur); ?n=<rəqəm> — məşq üçün başqa krossvord.
     * Hələlik yalnız əyləncə üçündür — xal yazılmır, hədiyyə verilmir.
     */
    public function crossword(Request $request): View
    {
        $n = (string) $request->query('n', '');
        $practice = $n !== '' && ctype_digit($n) && strlen($n) <= 10 && (int) $n > 0;
        $seed = $practice ? (int) $n % 2147483647 : Crossword::dailySeed();

        return view('frontend.games.crossword', [
            'puzzle' => Crossword::make($seed),
            'practice' => $practice,
            'dateLabel' => now('Asia/Baku')->format('d.m.Y'),
        ]);
    }

    public function wheel(Request $request): View
    {
        $user = $request->user();
        $verified = $user && $user->isVerified();

        return view('frontend.games.wheel', [
            'segments' => $this->segments(),
            'closed' => $this->closedKeys(),
            'verified' => $verified,
            'attempts' => $this->attempts(),
            'state' => $verified ? $this->wheelState($user->id) : null,
        ]);
    }

    /**
     * "Fırlat": çarx dayanmadan fırlanmağa başlayır (məharət oyunu — nəticəni oyunçunun "Dayan" basdığı an həll edir).
     * Fırlanmanın parametrlərini (başlanğıc bucaq, istiqamət, sürət, ritm) server seçir və sessiyada saxlayır.
     * Təsdiqlənmiş istifadəçinin cəhdi elə burada sayılır — bəyənmədiyi fırlanmanı atıb yenisini başlatmaq olmur.
     * Qonaqlar üçün sınaq fırlatmasıdır.
     */
    public function spin(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $user->isVerified()) {
            $n = (int) $request->session()->get('wheel_demo_count', 0);
            $request->session()->put('wheel_demo_count', $n + 1);

            return response()->json(['run' => $this->newRun($request, 'demo', $n), 'demo' => true]);
        }

        return DB::transaction(function () use ($request, $user) {
            User::whereKey($user->id)->lockForUpdate()->first(); // eyni anda iki fırlatmanın qarşısını alır
            $state = $this->wheelState($user->id);

            // Bu gün hədiyyə artıq götürülüb (çarxda və ya Hədiyyə Kranında) və ya cəhdlər bitib — əyləncə üçün
            // fırlada bilər: cəhd sayılmır, nəticə yadda saxlanmır, hədiyyə verilmir
            if ($state['fun']) {
                $run = $this->newRun($request, 'fun', random_int(0, count(config('games.wheel.speeds', [1])) - 1));

                return response()->json(['run' => $run, 'demo' => false, 'fun' => true, 'state' => $state]);
            }
            if ($state['attemptsLeft'] <= 0) {
                return response()->json(['message' => 'Bu gün üçün cəhdləriniz bitdi. Sabah yenə gəlin!', 'state' => $state], 429);
            }

            // Fırlatma bazaya yazılmır: cəhd sayı və son nəticə serverin keşində saxlanılır.
            // Yeni fırlatma əvvəlki nəticəni silir.
            $round = $this->round($user->id);
            $round['count']++;
            $round['last'] = null;
            $round['pending'] = now()->timestamp; // fırlanır (səhifə bağlansa, max_seconds sonra cəhd uğursuz sayılır)
            $this->saveRound($user->id, $round);

            return response()->json([
                'run' => $this->newRun($request, 'real', $round['count'] - 1),
                'demo' => false,
                'state' => $this->wheelState($user->id),
            ]);
        });
    }

    /**
     * "Dayan": oyunçu fırlanma başlayandan neçə millisaniyə sonra basdığını göndərir.
     * Server həmin andakı bucağı özü hesablayır və göstəricinin altındakı sektoru tapır.
     */
    public function stop(Request $request): JsonResponse
    {
        $data = $request->validate(['ms' => ['required', 'integer', 'min:0', 'max:600000']]);
        $run = $request->session()->pull('wheel_run');
        if (! $run) {
            return response()->json(['message' => 'Çarx fırlanmır. "Fırlat" basın.'], 422);
        }

        // Oyunçu serverdə keçən vaxtdan çox ola bilməz (şəbəkə gecikməsi yalnız əksinə təsir edir)
        $elapsed = (microtime(true) - $run['started']) * 1000;
        if ($data['ms'] > $elapsed + 300) {
            logger()->warning('Çarx oyunu: dayanma vaxtı uyğun deyil', [
                'user_id' => $request->user()?->id, 'ms' => $data['ms'], 'elapsed' => round($elapsed), 'ip' => $request->ip(),
            ]);
            $this->clearPending($request, $run);

            return response()->json(['message' => 'Fırlatma təsdiqlənmədi. Yenidən cəhd edin.'], 422);
        }

        // Oyunçu "Dayan" basmayıb, çarx vaxt bitəndə özü dayanıb — hədiyyə yoxdur, cəhd uğursuzdur
        $timeout = (int) $data['ms'] >= (int) $run['max_ms'];
        $ms = min((int) $data['ms'], (int) $run['max_ms']);
        $angle = self::runAngle($run, $ms);
        $segments = $this->segments();
        $index = self::indexAt($segments, $angle);
        $segment = $segments[$index];
        if ($timeout) {
            $segment = ['key' => 'timeout', 'label' => 'Vaxt bitdi', 'prize' => false];
        }
        $closed = in_array($segment['key'], $this->closedKeys(), true);
        $prize = $segment['prize'] && ! $closed;

        $payload = [
            'index' => $index,
            'angle' => round($angle, 3),
            'label' => $segment['label'],
            'prize' => $prize,
            'closed' => $closed,
            'timeout' => $timeout,
            'demo' => $run['mode'] === 'demo',
            'fun' => $run['mode'] === 'fun',
        ];

        $user = $request->user();
        if (! $user || ! $user->isVerified() || $run['mode'] === 'demo') {
            return response()->json($payload);
        }

        return DB::transaction(function () use ($user, $run, $segment, $prize, $payload) {
            User::whereKey($user->id)->lockForUpdate()->first();

            if ($run['mode'] === 'real' && $run['user_id'] === $user->id && $run['day'] === $this->today()) {
                $round = $this->round($user->id);
                $round['pending'] = false;
                if (! $round['claimed']) {
                    $round['last'] = ['key' => $segment['key'], 'label' => $segment['label'], 'prize' => $prize];
                }
                $this->saveRound($user->id, $round);
            }

            return response()->json($payload + ['state' => $this->wheelState($user->id)]);
        });
    }

    /** Rədd edilmiş fırlatma: cəhd sayılıb, nəticə yoxdur */
    private function clearPending(Request $request, array $run): void
    {
        $user = $request->user();
        if ($run['mode'] === 'real' && $user && $run['user_id'] === $user->id && $run['day'] === $this->today()) {
            $round = $this->round($user->id);
            $round['pending'] = false;
            $this->saveRound($user->id, $round);
        }
    }

    /** Yeni fırlanmanın parametrləri; brauzerə gedir, server isə başlama vaxtı ilə birgə sessiyada saxlayır */
    private function newRun(Request $request, string $mode, int $attemptNo): array
    {
        $cfg = config('games.wheel');
        $speeds = array_values($cfg['speeds'] ?? [180]);
        $base = (float) $speeds[max(0, min($attemptNo, count($speeds) - 1))];
        $rnd = fn () => random_int(0, 1_000_000) / 1_000_000;
        [$pMin, $pMax] = $cfg['wobble_period'] ?? [1.4, 2.6];

        $maxMs = (int) (($cfg['max_seconds'] ?? 15) * 1000);

        // İstiqamət dəyişmə anları (ms): dönüşlər bir-birinə qarışmasın deyə aralarında ən azı 2 × flip_ms
        $flipMs = max(100, (int) ($cfg['reversal_ms'] ?? 500));
        [$gMin, $gMax] = $cfg['reversal_gap'] ?? [0, 0];
        [$fMin, $fMax] = $cfg['reversal_first'] ?? [$gMin, $gMax];
        $flips = [];
        if ($gMax > 0) {
            $t = (int) round(($fMin + $rnd() * ($fMax - $fMin)) * 1000);
            while ($t < $maxMs && count($flips) < 50) {
                $flips[] = max($t, ($flips ? end($flips) : 0) + 2 * $flipMs);
                $t = end($flips) + (int) round(($gMin + $rnd() * ($gMax - $gMin)) * 1000);
            }
        }

        $run = [
            'a0' => round($rnd() * 360, 3),
            'dir' => random_int(0, 1) ? 1 : -1,
            'v' => round($base * (1 + ($rnd() * 2 - 1) * (float) ($cfg['speed_jitter'] ?? 0)), 3),
            'k' => min(0.9, max(0, (float) ($cfg['wobble'] ?? 0))),
            'period' => round($pMin + $rnd() * ($pMax - $pMin), 3),
            'phase' => round($rnd() * 2 * M_PI, 4),
            'flips' => $flips,
            'flip_ms' => $flipMs,
            'max_ms' => $maxMs,
        ];

        $request->session()->put('wheel_run', $run + [
            'started' => microtime(true),
            'mode' => $mode,
            'user_id' => $request->user()?->id,
            'day' => $this->today(),
        ]);

        return $run;
    }

    /** Hesablama addımı (ms): bucaq bu addımlarla toplanır — wheel.js-də eyni */
    public const RUN_STEP_MS = 5;

    /**
     * Çarxın sürəti (dərəcə/san, işarəli) $ms anında:
     * v · (1 + k · sin(2π t / period + phase)) · istiqamət. Hər dönüş anı ətrafında (flip_ms ərzində)
     * istiqamət hamar şəkildə +1-dən −1-ə keçir: çarx yavaşlayır, bir anlıq dayanır, əks tərəfə fırlanır.
     * wheel.js-də eyni düstur var.
     */
    public static function runSpeed(array $run, float $ms): float
    {
        $t = $ms / 1000;
        $speed = $run['v'] * (1 + $run['k'] * sin(2 * M_PI * $t / $run['period'] + $run['phase'])) * $run['dir'];
        $h = ($run['flip_ms'] ?? 500) / 2;
        foreach ($run['flips'] ?? [] as $f) {
            if ($ms >= $f + $h) {
                $speed = -$speed;
            } elseif ($ms > $f - $h) {
                $speed *= cos(M_PI * ($ms - $f + $h) / (2 * $h));
            }
        }

        return $speed;
    }

    /**
     * Çarxın bucağı (dərəcə, saat əqrəbi istiqamətində, 0–360) başlanğıcdan $ms sonra:
     * sürət RUN_STEP_MS addımlarla (trapesiya üsulu) toplanır, son natamam addım xətti.
     */
    public static function runAngle(array $run, float $ms): float
    {
        $dt = self::RUN_STEP_MS;
        $steps = (int) floor($ms / $dt);
        $travel = 0.0;
        $prev = self::runSpeed($run, 0);
        for ($i = 0; $i < $steps; $i++) {
            $next = self::runSpeed($run, ($i + 1) * $dt);
            $travel += ($prev + $next) / 2 * $dt / 1000;
            $prev = $next;
        }
        $travel += $prev * ($ms - $steps * $dt) / 1000;

        return fmod(fmod($run['a0'] + $travel, 360) + 360, 360);
    }

    /**
     * Göstərici (yuxarıda) altındakı sektor. 0-cı sektorun ortası çarxın "yuxarısı"dır,
     * sektorlar saat əqrəbi istiqamətində düzülüb; çarx $rotation dərəcə dönübsə, göstəricinin
     * altında çarxın (360 − rotation) nöqtəsi olur.
     */
    public static function indexAt(array $segments, float $rotation): int
    {
        $theta = fmod(fmod(360 - $rotation, 360) + 360, 360);
        $pos = fmod($theta + $segments[0]['deg'] / 2, 360);

        foreach ($segments as $i => $s) {
            if ($pos < $s['deg']) {
                return $i;
            }
            $pos -= $s['deg'];
        }

        return array_key_last($segments);
    }

    /** Bu gün bütün oyunçular üçün limiti dolmuş (bağlı) sektorların açarları */
    private function closedKeys(): array
    {
        $closed = [];
        foreach (config('games.wheel.daily_caps', []) as $key => $cap) {
            if ($this->capTaken($key) >= (int) $cap) {
                $closed[] = $key;
            }
        }

        return $closed;
    }

    private function capTaken(string $key): int
    {
        $inDb = WheelSpin::where('game', 'wheel')->where('prize_key', $key)->whereDate('spun_on', $this->today())->count();
        // istifadə olunan kupon bazadan silinir — sayı keşdə də saxlanılır
        return max($inDb, (int) Cache::get($this->capKey($key), 0));
    }

    private function capKey(string $key): string
    {
        return "wheel_cap:{$key}:" . $this->today();
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
            if ($state['blockedBy']) {
                return response()->json(['message' => $state['blockedMessage'], 'state' => $state], 422);
            }

            $round = $this->round($user->id);
            $last = $round['last'];
            if (! $last || ! $this->lastIsPrize($last)) {
                return response()->json(['message' => 'Götürüləcək hədiyyə yoxdur. Çarxı fırladın.', 'state' => $state], 422);
            }

            // Bazaya yalnız götürülən hədiyyə yazılır
            $spin = WheelSpin::create([
                'user_id' => $user->id,
                'game' => 'wheel',
                'prize_key' => $last['key'],
                'prize_label' => $last['label'],
                'code' => $this->newCode(),
                'spun_on' => $this->today(),
                // çarx masada oynanılır, endirim həmin masanın cari hesabına — kod yalnız bu ziyarət üçündür
                'expires_at' => now()->addMinutes((int) config('games.wheel.code_valid_minutes', 10)),
            ]);
            if (array_key_exists($last['key'], config('games.wheel.daily_caps', []))) {
                $capKey = $this->capKey($last['key']);
                Cache::put($capKey, $this->capTaken($last['key']), now('Asia/Baku')->endOfDay());
            }

            $round['last'] = null;
            // vaxtı keçmiş kupon bazadan silinir — "bu gün götürülüb" qeydi gün sonunadək raundda qalır
            $round['claimed'] = $this->spinPayload($spin);
            $this->saveRound($user->id, $round);

            return response()->json(['state' => $this->wheelState($user->id)]);
        });
    }

    /**
     * Bu günün vəziyyəti: neçə cəhd qalıb, son nəticə, götürülmüş hədiyyə.
     */
    private function wheelState(int $userId): array
    {
        $spin = WheelSpin::where('game', 'wheel')->where('user_id', $userId)->whereDate('spun_on', $this->today())->whereNotNull('code')->latest('id')->first();
        $round = $this->round($userId);
        $last = $round['last'];

        // Kupon bazadadırsa ondan, vaxtı keçib silinibsə raunddakı surətdən — gün ərzində təkrar fırlatmaq olmur
        $claimed = $spin ? $this->spinPayload($spin) : ($round['claimed'] ?? null);
        if ($claimed && ! $spin) {
            $claimed['expired'] = true;
        }

        // gündə yalnız bir oyunda hədiyyə: bu gün Hədiyyə Kranında götürübsə, çarxı yalnız əyləncə üçün fırladır
        $blockedBy = $claimed ? null : DailyPrize::claimedElsewhere($userId, 'wheel');

        $left = $claimed || $blockedBy ? 0 : max(0, $this->attempts() - $round['count']);
        // cəhdlər hədiyyəsiz bitib (götürülməmiş hədiyyə gözləmir) — əyləncə üçün oynaya bilər
        // (son cəhd hələ fırlanırsa — nəticəsini gözləyirik)
        // (dayandırılmadan atılmış fırlatma — səhifə bağlanıb — max_seconds keçəndən sonra uğursuz sayılır)
        $spinning = ! empty($round['pending'])
            && now()->timestamp - (int) $round['pending'] <= (int) config('games.wheel.max_seconds', 15) + 5;
        $outOfAttempts = ! $claimed && ! $blockedBy && $left <= 0 && ! $spinning && ! ($last && $this->lastIsPrize($last));

        return [
            'attempts' => $this->attempts(),
            'attemptsLeft' => $left,
            'last' => $last && ! $claimed && ! $blockedBy ? ['label' => $last['label'], 'prize' => $this->lastIsPrize($last)] : null,
            'claimed' => $claimed,
            'blockedBy' => $blockedBy,
            'blockedMessage' => $blockedBy ? DailyPrize::message($blockedBy) : null,
            // əyləncə rejimi: limitsiz oynamaq olar, hədiyyəsiz — hədiyyə götürülüb və ya cəhdlər bitib
            'fun' => (bool) ($claimed || $blockedBy || $outOfAttempts),
            'funReason' => $claimed ? 'claimed' : ($blockedBy ? 'blocked' : ($outOfAttempts ? 'attempts' : null)),
        ];
    }

    /** Bu günün raundu (keşdə): ['count' => fırlatma sayı, 'last' => son nəticə və ya null] */
    private function round(int $userId): array
    {
        return Cache::get($this->roundKey($userId), []) + ['count' => 0, 'last' => null, 'claimed' => null, 'pending' => false];
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

    /** Son nəticə hədiyyədirmi: dayananda yazılır (bağlı sektor hədiyyə vermir) */
    private function lastIsPrize(array $last): bool
    {
        return (bool) ($last['prize'] ?? (collect($this->segments())->firstWhere('key', $last['key'])['prize'] ?? false));
    }

    /* ---------------------------------------------------------------- */

    /** Sektorlar, hər birinə "deg" (çarxdakı eni, dərəcə; cəmi 360) əlavə olunur */
    private function segments(): array
    {
        $segments = array_values(config('games.wheel.segments'));
        $total = array_sum(array_map(fn ($s) => max(0.0, (float) ($s['size'] ?? 1)), $segments)) ?: 1;

        return array_map(fn ($s) => $s + ['deg' => max(0.0, (float) ($s['size'] ?? 1)) * 360 / $total], $segments);
    }

    private function newCode(): string
    {
        return WheelSpin::newCode();
    }

    private function spinPayload(WheelSpin $spin): array
    {
        return [
            'label' => $spin->prize_label,
            'prize' => (bool) $spin->code,
            'code' => $spin->code,
            'expires' => $spin->expires_at?->timezone('Asia/Baku')->format('d.m.Y H:i'), // kod 1 gün etibarlıdır — saat da lazımdır
            // "Hədiyyəni götür" basılan an (Bakı vaxtı)
            'claimed_at' => $spin->created_at?->timezone('Asia/Baku')->format('d.m.Y H:i'),
            // istifadə olunmuş kod da artıq keçərli deyil (silinmir, admin paneldə saxlanılır)
            'expired' => $spin->status() !== WheelSpin::ACTIVE,
            'used' => $spin->status() === WheelSpin::USED,
        ];
    }

    private function today(): string
    {
        return now('Asia/Baku')->toDateString();
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
}
