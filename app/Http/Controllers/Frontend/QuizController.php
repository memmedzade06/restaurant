<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Referrals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Sual oyunu. Oyun serverdə gedir: düz cavablar brauzerə göndərilmir, vaxtı da server sayır.
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

        return view('frontend.games.quiz', [
            'quiz' => $this->settings() + [
                'canEarn' => $verified,
                'pointsLeft' => $verified ? $this->pointsLeftToday($user->id) : null,
                'startUrl' => route('games.quiz.start'),
                'answerUrl' => route('games.quiz.answer'),
                'nextUrl' => route('games.quiz.next'),
                'registerUrl' => $user ? route($user->verificationRoute()) : route('register'),
            ],
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $game = [
            'stage' => 0, 'passed' => 0, 'correct' => 0, 'lives' => 0,
            'used' => [], 'queue' => [], 'index' => 0,
            'current' => null, 'over' => false,
        ];
        $this->beginStage($game);
        $this->ask($game);
        $request->session()->put(self::KEY, $game);

        return response()->json(['question' => $this->publicQuestion($game)]);
    }

    public function answer(Request $request): JsonResponse
    {
        $data = $request->validate(['choice' => ['nullable', 'integer', 'min:0', 'max:2']]);
        $game = $request->session()->get(self::KEY);
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
            $result += [
                'stageDone' => true,
                'stage' => $game['stage'],
                'bonus' => $this->awardStage($request->user(), $game['stage']),
                'allDone' => count($game['used']) >= count($this->questions()),
            ];
            if ($result['allDone']) {
                $game['over'] = true;
            }
        }

        $request->session()->put(self::KEY, $game);

        return response()->json($result + ['pointsLeft' => $this->pointsLeftFor($request->user())]);
    }

    public function next(Request $request): JsonResponse
    {
        $game = $request->session()->get(self::KEY);
        if (! $game || $game['over'] || ! $game['current'] || ! $game['current']['answered']) {
            return response()->json(['message' => 'Oyun tapılmadı. Yenidən başlayın.'], 409);
        }

        if ($game['index'] + 1 >= count($game['queue'])) {
            if (! $this->beginStage($game)) {
                return response()->json(['message' => 'Suallar bitdi.'], 409);
            }
        } else {
            $game['index']++;
        }
        $this->ask($game);
        $request->session()->put(self::KEY, $game);

        return response()->json(['question' => $this->publicQuestion($game)]);
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

        $used = array_flip($game['used']);
        $pool = [1 => [], 2 => [], 3 => []];
        foreach ($this->questions() as $id => $q) {
            if (! isset($used[$id])) {
                $pool[max(1, min(3, (int) $q['l']))][] = $id;
            }
        }
        foreach ($pool as &$ids) {
            shuffle($ids);
        }
        unset($ids);

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

    private function pointsLeftFor(?User $user): ?float
    {
        return $user && $user->isVerified() ? $this->pointsLeftToday($user->id) : null;
    }

    /** N-ci mərhələnin xalı: 1-ci — stage_points, hər növbəti — stage_points_step qədər çox */
    public static function stageReward(int $stage): float
    {
        $base = max(0, (float) config('quiz.stage_points', 0.25));
        $step = max(0, (float) config('quiz.stage_points_step', 0.25));

        return round($base + max(0, $stage - 1) * $step, 3);
    }

    /** Mərhələ keçildi: təsdiqlənmiş oyunçuya xal (bu günün limiti çatana qədər). Qaytarır: yazılan xal */
    private function awardStage(?User $user, int $stage): float
    {
        $points = self::stageReward($stage);
        if (! $user || ! $user->isVerified() || $points <= 0) {
            return 0;
        }

        return DB::transaction(function () use ($user, $points) {
            User::whereKey($user->id)->lockForUpdate()->first(); // eyni anda iki sorğu limiti keçməsin
            $give = min($points, $this->pointsLeftToday($user->id));
            if ($give <= 0) {
                return 0;
            }
            $key = $this->dailyKey($user->id);
            Cache::put($key, (float) Cache::get($key, 0) + $give, now('Asia/Baku')->endOfDay());
            User::whereKey($user->id)->increment('points', $give);

            return $give;
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
            'stagePoints' => self::stageReward(1),
            'stageStep' => max(0, (float) config('quiz.stage_points_step', 0.25)),
            'maxPerDay' => max(0, (float) config('quiz.max_points_per_day', 2)),
        ];
    }

    /** Oyunlar səhifəsindəki kart üçün */
    public static function pointsLabel(): string
    {
        return Referrals::format(self::stageReward(1));
    }
}
