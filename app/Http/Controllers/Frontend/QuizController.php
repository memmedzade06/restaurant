<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Referrals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Bilik Yarışı. Oyun serverdə gedir: düz cavablar brauzerə göndərilmir, vaxtı da server sayır.
 *  - basla: yeni oyun, ilk sual;
 *  - cavab: seçilən variant (və ya vaxt bitdi) yoxlanılır, nəticə qaytarılır;
 *  - novbeti: növbəti sual (mərhələ bitibsə — yeni mərhələ).
 * Mərhələni keçən təsdiqlənmiş oyunçunun balansına xal yazılır (gündəlik limitlə).
 */
class QuizController extends Controller
{
    private const KEY = 'quiz_game';
    private const LATENCY_MS = 1500; // şəbəkə gecikməsi üçün əlavə vaxt

    public function show(Request $request): View
    {
        $user = $request->user();
        $verified = $user && $user->isVerified();
        $this->playerToken($request); // açar səhifə açılanda yaradılsın — oyunun sorğularından əvvəl

        return view('frontend.games.quiz', [
            'quiz' => $this->settings() + [
                // xal yalnız qonaqlara; ofisiant və admin oynaya bilər, amma xal almır ("staff")
                'canEarn' => $verified && $user->earnsGamePoints(),
                'staff' => $user && ! $user->earnsGamePoints(),
                'pointsLeft' => $verified && $user->earnsGamePoints() ? $this->pointsLeftToday($user->id) : null,
                'startUrl' => route('games.quiz.start'),
                'answerUrl' => route('games.quiz.answer'),
                'nextUrl' => route('games.quiz.next'),
                'registerUrl' => $user ? route($user->verificationRoute()) : route('register'),
                'loginUrl' => route('login'),
                'isGuest' => ! $user,
                // təsdiqdən sonra yarımçıq oyuna qayıdanda: növbəti mərhələdən davam
                'resumeStage' => $verified ? $this->resumableStage($request) : null,
            ],
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $game = [
            'stage' => 0, 'passed' => 0, 'correct' => 0, 'lives' => 0,
            'used' => [], 'queue' => [], 'index' => 0,
            'current' => null, 'over' => false,
            'seen' => $this->seenHistory($request), // bu oyunçunun əvvəlki oyunlarda gördüyü suallar
            'today' => $this->todaySeen($request),  // bu gün artıq verilən suallar — bu gün təkrar düşmür
        ];
        if (! $this->beginStage($game)) {
            return response()->json(['message' => self::TODAY_DONE, 'todayDone' => true], 409);
        }
        $this->ask($game);
        $this->rememberToday($request, $game);
        $this->rememberSeen($request, $game);
        $this->saveGame($request, $game);

        return response()->json(['question' => $this->publicQuestion($game)]);
    }

    public function answer(Request $request): JsonResponse
    {
        $data = $request->validate(['choice' => ['nullable', 'integer', 'min:0', 'max:2']]);
        $game = $this->loadGame($request);
        if (! $game || $game['over'] || ! $game['current'] || $game['current']['answered']) {
            return response()->json(['message' => 'Oyun tapılmadı. Yenidən başlayın.'], 409);
        }

        $cur = &$game['current'];
        $cur['answered'] = true;
        $elapsed = (int) round((microtime(true) - $cur['asked_at']) * 1000);
        $timeout = $data['choice'] === null || $elapsed > $this->settings()['seconds'] * 1000 + self::LATENCY_MS;
        $ok = ! $timeout && (int) $data['choice'] === $cur['right'];

        if ($ok) {
            $game['correct']++;
        } else {
            $game['lives']--;
        }

        $result = [
            'correct' => $ok,
            'timeout' => $timeout,
            'right' => $cur['right'],
            'lives' => $game['lives'],
            'totalCorrect' => $game['correct'],
        ];
        unset($cur);

        if ($game['lives'] <= 0) {
            $game['over'] = true;
            $result += ['gameOver' => true, 'passed' => $game['passed']];
        } elseif ($game['index'] + 1 >= count($game['queue'])) {
            // mərhələ keçildi
            $game['passed'] = $game['stage'];
            [$bonus, $repeat] = $this->awardStage($request->user(), $game['stage']);
            $result += [
                'stageDone' => true,
                'stage' => $game['stage'],
                'needVerify' => $this->mustVerify($request, $game),
                'bonus' => $bonus,
                'repeat' => $repeat, // bu mərhələyə görə bu gün artıq xal alınıb
                'bestStage' => $this->bestStageFor($request->user()),
                // bu gün üçün sual qalmayıb (gün ərzində təkrar sual verilmir)
                'allDone' => $this->leftToday($game) === 0,
            ];
            if ($result['allDone']) {
                $game['over'] = true;
            }
        }

        $this->saveGame($request, $game);

        return response()->json($result + ['pointsLeft' => $this->pointsLeftFor($request->user())]);
    }

    public function next(Request $request): JsonResponse
    {
        $game = $this->loadGame($request);
        if (! $game || $game['over'] || ! $game['current'] || ! $game['current']['answered']) {
            return response()->json(['message' => 'Oyun tapılmadı. Yenidən başlayın.'], 409);
        }

        if ($game['index'] + 1 >= count($game['queue'])) {
            if ($this->mustVerify($request, $game)) {
                return response()->json(['message' => 'Davam etmək üçün profilinizi təsdiqləyin.', 'needVerify' => true], 403);
            }
            if (! $this->beginStage($game)) {
                return response()->json(['message' => self::TODAY_DONE, 'todayDone' => true], 409);
            }
        } else {
            $game['index']++;
        }
        $this->ask($game);
        $this->rememberToday($request, $game);
        $this->rememberSeen($request, $game);
        $this->saveGame($request, $game);

        return response()->json(['question' => $this->publicQuestion($game)]);
    }

    /* ---------- Oyunun saxlanması ---------- */

    /**
     * Oyunun vəziyyəti sessiyada yox, keşdə saxlanılır. Səbəb: səhifə arxa planda başqa sorğular da
     * göndərir ("İndi saytda" siqnalı və s.), Laravel isə sessiyanı hər sorğuda bütövlükdə yenidən yazır —
     * eyni anda gələn sorğu oyunun yeni vəziyyətini köhnəsi ilə əvəz edə bilirdi ("Oyun tapılmadı").
     * Sessiyada yalnız dəyişməyən açar (token) qalır; girişdə sessiya yenilənəndə də o saxlanılır.
     */
    private function playerToken(Request $request): string
    {
        $token = $request->session()->get('quiz_token');
        if (! $token) {
            $token = Str::random(32);
            $request->session()->put('quiz_token', $token);
        }

        return $token;
    }

    private function gameKey(Request $request): string
    {
        return self::KEY . ':' . $this->playerToken($request);
    }

    private function loadGame(Request $request): ?array
    {
        return Cache::get($this->gameKey($request));
    }

    private function saveGame(Request $request, array $game): void
    {
        Cache::put($this->gameKey($request), $game, now()->addHours(6));
    }

    /** Təsdiqlənməmiş oyunçu icazə verilən mərhələləri keçib — növbəti mərhələ üçün təsdiq lazımdır */
    private function mustVerify(Request $request, array $game): bool
    {
        $user = $request->user();

        return ! ($user && $user->isVerified()) && $game['stage'] >= $this->settings()['guestStages'];
    }

    /** Sessiyada mərhələsi bitmiş, davam etdirilə bilən oyun varsa — növbəti mərhələnin nömrəsi */
    private function resumableStage(Request $request): ?int
    {
        $game = $this->loadGame($request);
        if (! $game || $game['over'] || ! $game['current'] || ! $game['current']['answered']
            || $game['index'] + 1 < count($game['queue'])) {
            return null;
        }

        return $game['stage'] + 1;
    }

    /* ---------- Oyunun gedişatı ---------- */

    /**
     * Yeni mərhələ: config('quiz.stages') üzrə [asan, orta, çətin] sual sayı — mərhələ artdıqca çətinləşir.
     * Hansısa çətinlikdə sual qalmayıbsa, yeri əvvəlcə daha çətin, sonra daha asan suallarla doldurulur.
     * Mərhələ daxilində suallar asandan çətinə düzülür.
     */
    private function beginStage(array &$game): bool
    {
        $stages = config('quiz.stages', [[10, 0, 0]]);
        $mix = array_values($stages[min($game['stage'], count($stages) - 1)] ?? [10, 0, 0]);
        $perStage = $this->settings()['perStage'];

        // Hər çətinlikdə əvvəlcə bu oyunçunun hələ görmədiyi suallar (qarışıq), sonra gördükləri.
        // Belə hər oyunçu öz növbəsi ilə bütün bazanı görür, təkrar suallar az olur.
        // bu oyunda verilən və bu gün əvvəlki oyunlarda verilən suallar yenidən düşmür
        $used = array_flip(array_merge($game['used'], $game['today'] ?? []));
        $seen = array_flip($game['seen'] ?? []);
        $fresh = [1 => [], 2 => [], 3 => []];
        $old = [1 => [], 2 => [], 3 => []];
        foreach ($this->questions() as $id => $q) {
            if (! isset($used[$id])) {
                $level = max(1, min(3, (int) $q['l']));
                isset($seen[$id]) ? $old[$level][] = $id : $fresh[$level][] = $id;
            }
        }
        $pool = [];
        foreach ([1, 2, 3] as $level) {
            shuffle($fresh[$level]);
            shuffle($old[$level]);
            $pool[$level] = array_merge($fresh[$level], $old[$level]);
        }

        $picked = [1 => [], 2 => [], 3 => []];
        foreach ([1, 2, 3] as $level) {
            $picked[$level] = array_splice($pool[$level], 0, (int) ($mix[$level - 1] ?? 0));
        }
        $missing = $perStage - array_sum(array_map('count', $picked));
        // çatmayanlar: əvvəlcə daha çətin, sonra daha asan suallar
        foreach ([3, 2, 1] as $level) {
            while ($missing > 0 && $pool[$level]) {
                $picked[$level][] = array_shift($pool[$level]);
                $missing--;
            }
        }

        $queue = array_merge($picked[1], $picked[2], $picked[3]);
        if (! $queue) {
            return false;
        }

        $game['stage']++;
        $game['queue'] = array_slice($queue, 0, $perStage);
        $game['index'] = 0;
        $game['lives'] = $this->settings()['lives'];

        return true;
    }

    /** Növbəti sualı verir: variantları qarışdırır, vaxtı başladır */
    private function ask(array &$game): void
    {
        $id = $game['queue'][$game['index']];
        $q = $this->questions()[$id];
        $options = array_merge([$q['a']], $q['w']);
        shuffle($options);

        $game['used'][] = $id;
        $game['current'] = [
            'id' => $id,
            'options' => $options,
            'right' => array_search($q['a'], $options, true),
            'asked_at' => microtime(true),
            'answered' => false,
        ];
    }

    /* ---------- Gün ərzində təkrar sual yoxdur ---------- */

    public const TODAY_DONE = 'Bu günün sualları bitdi — sabah yeni suallarla gəlin!';

    /** Bu gün verilən suallar: daxil olmuşda hesaba, qonaqda brauzerə bağlı; ikisi birləşdirilir (oyun ortasında girişdə) */
    private function todayKeys(Request $request): array
    {
        $day = now('Asia/Baku')->toDateString();
        $keys = ['quiz_today:g:' . $this->playerToken($request) . ':' . $day];
        if ($request->user()) {
            $keys[] = 'quiz_today:u:' . $request->user()->id . ':' . $day;
        }

        return $keys;
    }

    private function todaySeen(Request $request): array
    {
        $ids = [];
        foreach ($this->todayKeys($request) as $key) {
            $ids = array_merge($ids, Cache::get($key, []));
        }

        return array_values(array_unique($ids));
    }

    /** Verilən sualı bu günün siyahısına yazır (gün sonuna qədər saxlanılır) */
    private function rememberToday(Request $request, array &$game): void
    {
        $game['today'] = array_values(array_unique(array_merge($game['today'] ?? [], [$game['current']['id']])));
        $keys = $this->todayKeys($request);
        Cache::put(end($keys), $game['today'], now('Asia/Baku')->endOfDay());
    }

    /** Bu gün bu oyunçuya hələ verilməmiş sual sayı */
    private function leftToday(array $game): int
    {
        return count(array_diff(array_keys($this->questions()), $game['used'], $game['today'] ?? []));
    }

    /* ---------- Oyunçunun gördüyü suallar ---------- */

    /** Daxil olmuş oyunçu — hesaba bağlı (bütün cihazlarda), qonaq — bu brauzerə bağlı */
    private function seenKey(Request $request): ?string
    {
        return $request->user() ? 'quiz_seen:' . $request->user()->id : null;
    }

    private function seenHistory(Request $request): array
    {
        $key = $this->seenKey($request);
        $ids = Cache::get($key ?? 'quiz_seen:guest:' . $this->playerToken($request), []);

        return array_values(array_filter($ids, fn ($id) => isset($this->questions()[$id])));
    }

    /**
     * Verilən sualı tarixçəyə yazır. Hansısa çətinlikdə bütün suallar görülübsə,
     * həmin çətinliyin tarixçəsi sıfırlanır — dövr yenidən başlayır.
     */
    private function rememberSeen(Request $request, array &$game): void
    {
        $id = $game['current']['id'];
        $seen = array_values(array_unique(array_merge($game['seen'] ?? [], [$id])));

        $questions = $this->questions();
        $level = (int) $questions[$id]['l'];
        $ofLevel = array_keys(array_filter($questions, fn ($q) => (int) $q['l'] === $level));
        if (! array_diff($ofLevel, $seen)) {
            $seen = array_values(array_diff($seen, $ofLevel));
        }
        $game['seen'] = $seen;

        $key = $this->seenKey($request);
        Cache::put($key ?? 'quiz_seen:guest:' . $this->playerToken($request), $seen, now()->addDays($key ? 90 : 2));
    }

    private function publicQuestion(array $game): array
    {
        return [
            'text' => $this->questions()[$game['current']['id']]['q'],
            'options' => $game['current']['options'],
            'stage' => $game['stage'],
            'number' => $game['index'] + 1,
            'total' => count($game['queue']),
            'lives' => $game['lives'],
        ];
    }

    /* ---------- Xal ---------- */

    private function dailyKey(int $userId): string
    {
        return "quiz_points:{$userId}:" . now('Asia/Baku')->toDateString();
    }

    private function pointsLeftToday(int $userId): float
    {
        return max(0, round($this->settings()['maxPerDay'] - (float) Cache::get($this->dailyKey($userId), 0), 3));
    }

    /** Bu gün xal alınmış ən yüksək mərhələ (xal qazana bilməyənlər üçün null) */
    private function bestStageFor(?User $user): ?int
    {
        return $user && $user->isVerified() && $user->earnsGamePoints() ? (int) Cache::get($this->bestKey($user->id), 0) : null;
    }

    private function pointsLeftFor(?User $user): ?float
    {
        return $user && $user->isVerified() && $user->earnsGamePoints() ? $this->pointsLeftToday($user->id) : null;
    }

    /** Xal verilən ilk mərhələ (config quiz.points_from_stage); ondan əvvəlkilər xalsızdır */
    public static function firstPaidStage(): int
    {
        return max(1, (int) config('quiz.points_from_stage', 3));
    }

    /**
     * N-ci mərhələnin xalı: firstPaidStage()-dən əvvəl — 0, həmin mərhələ — stage_points,
     * hər növbəti — stage_points_step qədər çox (step 0 — hamısı eyni);
     * bonusStage()-dən başlayaraq isə bonus_stage_points (məs. 7-ci mərhələdən 0,5).
     */
    public static function stageReward(int $stage): float
    {
        $from = self::firstPaidStage();
        if ($stage < $from) {
            return 0;
        }
        $bonusFrom = self::bonusStage();
        if ($bonusFrom && $stage >= $bonusFrom) {
            return round(max(0, (float) config('quiz.bonus_stage_points', 0.5)), 3);
        }
        $base = max(0, (float) config('quiz.stage_points', 0.25));
        $step = max(0, (float) config('quiz.stage_points_step', 0.25));

        return round($base + ($stage - $from) * $step, 3);
    }

    /** Artırılmış xalın başladığı mərhələ (config quiz.bonus_from_stage); 0 və ya ilk xallı mərhələdən kiçik — yoxdur */
    public static function bonusStage(): ?int
    {
        $s = (int) config('quiz.bonus_from_stage', 0);

        return $s > self::firstPaidStage() ? $s : null;
    }

    /** Sıra sayı: 1 → "1-ci", 3 → "3-cü", 6 → "6-cı", 9 → "9-cu", 10 → "10-cu" */
    public static function ordinal(int $n): string
    {
        $suffix = [0 => 'cu', 1 => 'ci', 2 => 'ci', 3 => 'cü', 4 => 'cü', 5 => 'ci', 6 => 'cı', 7 => 'ci', 8 => 'ci', 9 => 'cu'][$n % 10];

        return "{$n}-{$suffix}";
    }

    /** Bu gün xal yazılmış ən yüksək mərhələ (yeni oyunda təkrar keçiləndə xal verilmir) */
    private function bestKey(int $userId): string
    {
        return "quiz_best_stage:{$userId}:" . now('Asia/Baku')->toDateString();
    }

    /**
     * Mərhələ keçildi: təsdiqlənmiş oyunçuya xal (bu günün limiti çatana qədər).
     * Bu gün xal alınmış mərhələni (və ondan aşağını) yeni oyunda təkrar keçmək xal vermir —
     * xal yalnız bu günün ən yüksək xallı mərhələsindən sonrakılar üçün yazılır.
     * Qaytarır: [yazılan xal, mərhələ bu gün artıq xal veribmi]
     */
    private function awardStage(?User $user, int $stage): array
    {
        $points = self::stageReward($stage);
        if (! $user || ! $user->isVerified() || ! $user->earnsGamePoints() || $points <= 0) {
            return [0, false];
        }

        return DB::transaction(function () use ($user, $stage, $points) {
            User::whereKey($user->id)->lockForUpdate()->first(); // eyni anda iki sorğu limiti keçməsin
            $bestKey = $this->bestKey($user->id);
            if ($stage <= (int) Cache::get($bestKey, 0)) {
                return [0, true];
            }
            $give = min($points, $this->pointsLeftToday($user->id));
            if ($give <= 0) {
                return [0, false];
            }
            $key = $this->dailyKey($user->id);
            $endOfDay = now('Asia/Baku')->endOfDay();
            Cache::put($key, (float) Cache::get($key, 0) + $give, $endOfDay);
            Cache::put($bestKey, $stage, $endOfDay);
            User::whereKey($user->id)->increment('points', $give);
            \App\Services\Points::log($user->id, $give, 'quiz', ['stage' => $stage]); // tarixçə

            return [$give, false];
        });
    }

    /* ---------- Ayarlar ---------- */

    private function questions(): array
    {
        return array_values(config('quiz.questions', []));
    }

    private function settings(): array
    {
        return [
            'perStage' => max(1, (int) config('quiz.per_stage', 10)),
            'lives' => max(1, (int) config('quiz.lives', 3)),
            'seconds' => max(1, (int) config('quiz.seconds', 5)),
            'stagePoints' => self::stageReward(self::firstPaidStage()), // xal verilən ilk mərhələnin xalı
            'pointsFrom' => self::firstPaidStage(),
            'pointsFromLabel' => self::ordinal(self::firstPaidStage()),
            'stageStep' => max(0, (float) config('quiz.stage_points_step', 0.25)),
            'bonusFrom' => self::bonusStage(),
            'bonusFromLabel' => self::bonusStage() ? self::ordinal(self::bonusStage()) : null,
            'bonusPoints' => self::bonusStage() ? self::stageReward(self::bonusStage()) : 0,
            'maxPerDay' => max(0, (float) config('quiz.max_points_per_day', 2)),
            'guestStages' => max(1, (int) config('quiz.guest_stages', 2)),
        ];
    }

    /** Oyunlar səhifəsindəki kart üçün */
    public static function pointsLabel(): string
    {
        return Referrals::format(self::stageReward(self::firstPaidStage()));
    }
}
