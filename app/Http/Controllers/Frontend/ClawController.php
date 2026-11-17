<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WheelSpin;
use App\Support\DailyPrize;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Hədiyyə Kranı: tutulan şarın içindən real hədiyyə çıxır (Bəxt çarxı kimi).
 *
 * Gedişat (hamısı serverdə — brauzerdə nəticəni dəyişmək və cəhd artırmaq olmur):
 *  1) "basla" — çəngəl düşür: bir cəhd sayılır, bir dəfəlik açar (token) verilir;
 *  2) "ac"    — şar çıxışa düşdü: açarla şarın içindəkini server seçir (ehtimallar config('games.claw.prizes'));
 *  3) "gotur" — son hədiyyə üçün kod yaranır, günün raundu bağlanır. Yenidən oynasa, əvvəlki hədiyyə itir.
 * Təsdiqlənməmiş istifadəçi və qonaq sınaq rejimində oynayır — kod verilmir.
 */
class ClawController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        $verified = $user && $user->hasVerifiedPhone();

        return view('frontend.games.claw', [
            'verified' => $verified,
            'attempts' => $this->attempts(),
            'state' => $verified ? $this->state($user->id) : null,
            // şarlardan çıxa bilən hədiyyələrin siyahısı (ehtimallar göstərilmir, boş şar daxil deyil)
            'prizes' => collect(config('games.claw.prizes'))->where('prize', true)->sortByDesc('weight')->pluck('label')->unique()->values(),
        ]);
    }

    /** Çəngəl düşür: cəhd sayılır və şarı açmaq üçün bir dəfəlik açar verilir */
    public function start(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = Str::random(32);

        if (! $user || ! $user->hasVerifiedPhone()) {
            $request->session()->put('claw_demo', ['token' => $token, 'at' => microtime(true)]);

            return response()->json(['token' => $token, 'demo' => true]);
        }

        return DB::transaction(function () use ($request, $user, $token) {
            User::whereKey($user->id)->lockForUpdate()->first(); // eyni anda iki cəhd olmasın
            $state = $this->state($user->id);

            // Bu gün hədiyyə artıq götürülüb (kranda və ya Bəxt çarxında) — əyləncə üçün oynaya bilər:
            // cəhd sayılmır, şar açılır, amma hədiyyə verilmir
            if ($state['fun']) {
                $request->session()->put('claw_demo', ['token' => $token, 'at' => microtime(true)]);

                return response()->json(['token' => $token, 'demo' => false, 'fun' => true, 'state' => $state]);
            }
            if ($state['attemptsLeft'] <= 0) {
                return response()->json(['message' => 'Bu gün üçün cəhdləriniz bitdi. Sabah yenə gəlin!', 'state' => $state], 429);
            }

            $round = $this->round($user->id);
            $round['count']++;
            $round['last'] = null;                                   // yeni cəhd — götürülməmiş hədiyyə itir
            $round['token'] = ['value' => $token, 'at' => microtime(true)];
            $this->saveRound($user->id, $round);

            return response()->json(['token' => $token, 'demo' => false, 'state' => $this->state($user->id)]);
        });
    }

    /** Şar çıxışa düşdü: içindəkini server seçir */
    public function open(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'size:32']]);
        $user = $request->user();
        $minSeconds = (float) config('games.claw.min_seconds_per_attempt', 1.5);

        // sınaq (qonaq) və əyləncə (bu gün hədiyyə götürülüb) cəhdləri — hədiyyəsiz
        $demo = $request->session()->get('claw_demo');
        if ($demo && hash_equals($demo['token'], $data['token'])) {
            $request->session()->forget('claw_demo');
            if (microtime(true) - $demo['at'] < $minSeconds) {
                return response()->json(['message' => 'Cəhd tapılmadı.'], 422);
            }
            $p = $this->pick();
            $verified = $user && $user->hasVerifiedPhone();

            return response()->json(['label' => $p['label'], 'prize' => (bool) $p['prize'], 'demo' => ! $verified, 'fun' => $verified]
                + ($verified ? ['state' => $this->state($user->id)] : []));
        }
        if (! $user || ! $user->hasVerifiedPhone()) {
            return response()->json(['message' => 'Cəhd tapılmadı.'], 422);
        }

        return DB::transaction(function () use ($user, $data, $minSeconds) {
            User::whereKey($user->id)->lockForUpdate()->first();
            $round = $this->round($user->id);
            $t = $round['token'];

            // açar bu cəhdə aid olmalı, bir dəfə işləməli və çəngəlin hərəkətinə vaxt keçməlidir
            if (! $t || ! hash_equals($t['value'], $data['token']) || microtime(true) - $t['at'] < $minSeconds) {
                return response()->json(['message' => 'Cəhd tapılmadı.', 'state' => $this->state($user->id)], 422);
            }

            $p = $this->pick();
            $round['token'] = null;
            $round['last'] = ['key' => $p['key'], 'label' => $p['label']];
            $this->saveRound($user->id, $round);

            return response()->json(['label' => $p['label'], 'prize' => (bool) $p['prize'], 'demo' => false, 'state' => $this->state($user->id)]);
        });
    }

    /** "Hədiyyəni götür": son açılan şarın hədiyyəsi üçün kod yaradır və günü bağlayır */
    public function claim(Request $request): JsonResponse
    {
        $user = $request->user();

        return DB::transaction(function () use ($user) {
            User::whereKey($user->id)->lockForUpdate()->first();
            $state = $this->state($user->id);
            if ($state['claimed']) {
                return response()->json(['state' => $state]); // artıq götürülüb — eyni kodu qaytarırıq
            }
            if ($state['blockedBy']) {
                return response()->json(['message' => $state['blockedMessage'], 'state' => $state], 422);
            }

            $round = $this->round($user->id);
            $last = $round['last'];
            if (! $last || ! $this->isPrize($last['key'])) {
                return response()->json(['message' => 'Götürüləcək hədiyyə yoxdur. Şar tutun.', 'state' => $state], 422);
            }

            $spin = WheelSpin::create([
                'user_id' => $user->id,
                'game' => 'claw',
                'prize_key' => $last['key'],
                'prize_label' => $last['label'],
                'code' => WheelSpin::newCode(),
                'spun_on' => $this->today(),
                'expires_at' => now()->addMinutes((int) config('games.claw.code_valid_minutes', 10)),
            ]);

            $round['last'] = null;
            // kupon istifadə olunub və ya vaxtı keçib silinsə də, "bu gün götürülüb" qeydi gün sonunadək qalır
            $round['claimed'] = $this->payload($spin);
            $this->saveRound($user->id, $round);

            return response()->json(['state' => $this->state($user->id)]);
        });
    }

    /* ---------------------------------------------------------------- */

    /** Bu günün vəziyyəti: neçə cəhd qalıb, son açılan şar, götürülmüş hədiyyə */
    private function state(int $userId): array
    {
        $spin = WheelSpin::where('game', 'claw')->where('user_id', $userId)
            ->whereDate('spun_on', $this->today())->whereNotNull('code')->latest('id')->first();
        $round = $this->round($userId);

        $claimed = $spin ? $this->payload($spin) : ($round['claimed'] ?? null);
        if ($claimed && ! $spin) {
            $claimed['expired'] = true;
        }
        $last = $round['last'];
        // gündə yalnız bir oyunda hədiyyə: bu gün Bəxt çarxında götürübsə, kranda yalnız əyləncə üçün oynayır
        $blockedBy = $claimed ? null : DailyPrize::claimedElsewhere($userId, 'claw');

        $left = $claimed || $blockedBy ? 0 : max(0, $this->attempts() - $round['count']);
        // cəhdlər hədiyyəsiz bitib (götürülməmiş hədiyyə gözləmir) — əyləncə üçün oynaya bilər
        $outOfAttempts = ! $claimed && ! $blockedBy && $left <= 0 && ! ($last && $this->isPrize($last['key']));

        return [
            'attempts' => $this->attempts(),
            'attemptsLeft' => $left,
            'last' => $last && ! $claimed && ! $blockedBy ? ['label' => $last['label'], 'prize' => $this->isPrize($last['key'])] : null,
            'claimed' => $claimed,
            'blockedBy' => $blockedBy,
            'blockedMessage' => $blockedBy ? DailyPrize::message($blockedBy) : null,
            // əyləncə rejimi: limitsiz oynamaq olar, hədiyyəsiz — hədiyyə götürülüb və ya cəhdlər bitib
            'fun' => (bool) ($claimed || $blockedBy || $outOfAttempts),
            'funReason' => $claimed ? 'claimed' : ($blockedBy ? 'blocked' : ($outOfAttempts ? 'attempts' : null)),
        ];
    }

    private function payload(WheelSpin $spin): array
    {
        return [
            'label' => $spin->prize_label,
            'code' => $spin->code,
            'expires' => $spin->expires_at?->timezone('Asia/Baku')->format('d.m.Y H:i'),
            'claimed_at' => $spin->created_at?->timezone('Asia/Baku')->format('d.m.Y H:i'),
            'expired' => $spin->status() === WheelSpin::EXPIRED,
        ];
    }

    /** Ehtimala görə şarın içindəki hədiyyə */
    private function pick(): array
    {
        $prizes = array_values(config('games.claw.prizes'));
        $roll = random_int(1, max(1, array_sum(array_column($prizes, 'weight'))));
        foreach ($prizes as $p) {
            $roll -= $p['weight'];
            if ($roll <= 0) {
                return $p;
            }
        }

        return end($prizes);
    }

    private function isPrize(string $key): bool
    {
        return (bool) (collect(config('games.claw.prizes'))->firstWhere('key', $key)['prize'] ?? false);
    }

    private function attempts(): int
    {
        return max(1, (int) config('games.claw.attempts_per_day', 3));
    }

    private function round(int $userId): array
    {
        return Cache::get($this->roundKey($userId), []) + ['count' => 0, 'last' => null, 'claimed' => null, 'token' => null];
    }

    private function saveRound(int $userId, array $round): void
    {
        Cache::put($this->roundKey($userId), $round, now('Asia/Baku')->endOfDay()); // gün bitəndə özü silinir
    }

    private function roundKey(int $userId): string
    {
        return "claw_round:{$userId}:" . $this->today();
    }

    private function today(): string
    {
        return now('Asia/Baku')->toDateString();
    }
}
