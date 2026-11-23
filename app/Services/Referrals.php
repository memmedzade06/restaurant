<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;

/**
 * Dəvət (referal) sistemi:
 *  1. Kimsə dəvət linkini və ya QR kodu açır — kod cookie-də yadda saxlanılır (config referral.cookie_days).
 *  2. Qeydiyyatdan keçəndə yeni istifadəçiyə "kim dəvət edib" yazılır.
 *  3. Yeni istifadəçi hesabını təsdiqləyəndə (Telegram, e-poçt və ya Google ilə) dəvət edənin balansına xal yazılır (bir dəfə).
 *  Xal yalnız qonaqlara və ofisiantlara yazılır (User::earnsReferralPoints) — adminə düşən pay heç kimə keçmir.
 */
class Referrals
{
    public const COOKIE = 'ref';

    /** Dəvət linki: /devet/{kod} */
    public static function link(User $user): string
    {
        return route('referral', $user->referralCode());
    }

    /** Linki açan ziyarətçinin brauzerində kodu saxlayır */
    public static function remember(string $code): void
    {
        Cookie::queue(self::COOKIE, $code, (int) config('referral.cookie_days', 30) * 24 * 60);
    }

    /** Qeydiyyat zamanı: yeni istifadəçiyə dəvət edəni yazır (kod etibarlıdırsa) */
    public static function attach(User $newUser, Request $request): void
    {
        $code = strtoupper(trim((string) $request->cookie(self::COOKIE)));
        if ($code === '') {
            return;
        }

        $referrer = User::where('referral_code', $code)->verified()->first();
        if ($referrer && $referrer->id !== $newUser->id) {
            $newUser->forceFill(['referred_by' => $referrer->id])->save();
        }
        Cookie::queue(Cookie::forget(self::COOKIE));
    }

    /** Səviyyələr üzrə xallar: [1-ci, 2-ci, 3-cü ...] (config referral.levels) */
    public static function levels(): array
    {
        return array_values(array_map(fn ($p) => max(0, (float) $p), config('referral.levels', [1])));
    }

    /**
     * Hesab təsdiqlənəndə: dəvət zənciri boyunca yuxarı xal yazılır — birbaşa dəvət edənə 0.5,
     * onu dəvət edənə 0.25, ondan yuxarıdakına 0.125 (config referral.levels).
     * Hər yeni istifadəçi üçün yalnız bir dəfə (referral_rewarded_at), eyni anda iki sorğu gəlsə də.
     */
    public static function reward(User $user): void
    {
        // xal yalnız dəvət olunan profilini təsdiqləyəndə (Telegram, e-poçt kodu və ya Google) yazılır
        $user->refresh();
        if (! $user->referred_by || $user->referral_rewarded_at || ! $user->isVerified()) {
            return;
        }

        DB::transaction(function () use ($user) {
            $claimed = User::whereKey($user->id)->whereNull('referral_rewarded_at')
                ->update(['referral_rewarded_at' => now()]);
            if (! $claimed) {
                return;
            }

            $seen = [$user->id => true]; // zəncirdə dövr olsa belə, heç kim iki dəfə xal almasın
            $upId = $user->referred_by;
            foreach (self::levels() as $points) {
                if (! $upId || isset($seen[$upId])) {
                    break;
                }
                $seen[$upId] = true;
                $up = User::whereKey($upId)->first(['id', 'type', 'is_admin', 'referred_by', 'phone_verified_at', 'email_verified_at']);
                if (! $up) {
                    break;
                }
                // xalı yalnız təsdiqlənmiş dəvət edən alır; təsdiqlənməmiş olsa da, zəncir yuxarı davam edir
                if ($points > 0 && $up->isVerified() && $up->earnsReferralPoints()) {
                    User::whereKey($upId)->increment('points', $points);
                }
                $upId = $up->referred_by;
            }
        });
    }

    /**
     * Dəvət piramidası: $user-in link/QR ilə dəvət etdikləri, onların dəvət etdikləri və s. —
     * xal verilən səviyyə sayı qədər dərinlikdə (config referral.levels).
     * Qaytarır: [
     *   'levels' => [['count' => 3, 'verified' => 2, 'points' => 0.5, 'earned' => 1.0], ...],
     *   'nodes'  => [['id', 'name', 'date', 'verified', 'level', 'points', 'children' => [...]], ...],
     *   'truncated' => bool,  // çox böyük ağacda yalnız ilk $limit nəfər göstərilir
     * ]
     * Hər səviyyə üçün bir sorğu; dövr olsa belə heç kim iki dəfə göstərilmir.
     */
    public static function tree(User $user, int $limit = 500): array
    {
        // admin dəvətə görə xal almır — ona xal "qazandınız" kimi göstərilmir
        $levels = array_map(fn ($p) => $user->earnsReferralPoints() ? $p : 0.0, self::levels());
        $seen = [$user->id => true];
        $byParent = [];
        $stats = [];
        $parents = [$user->id];
        $shown = 0;
        $truncated = false;

        foreach ($levels as $depth => $points) {
            if (! $parents) {
                $stats[] = ['count' => 0, 'verified' => 0, 'points' => $points, 'earned' => 0.0];
                continue;
            }
            $rows = User::whereIn('referred_by', $parents)
                ->orderBy('id')
                ->get(['id', 'name', 'referred_by', 'created_at', 'referral_rewarded_at']);

            $next = [];
            $count = $verified = 0;
            foreach ($rows as $r) {
                if (isset($seen[$r->id])) {
                    continue;
                }
                $seen[$r->id] = true;
                $count++;
                $isVerified = (bool) $r->referral_rewarded_at;
                $verified += $isVerified ? 1 : 0;
                $next[] = $r->id;
                if ($shown >= $limit) {
                    $truncated = true;
                    continue;
                }
                $shown++;
                $byParent[$r->referred_by][] = [
                    'id' => $r->id,
                    'name' => $r->short_name,
                    'date' => $r->created_at,
                    'verified' => $isVerified,
                    'level' => $depth + 1,
                    'points' => $isVerified ? $points : 0.0,
                ];
            }
            $stats[] = ['count' => $count, 'verified' => $verified, 'points' => $points, 'earned' => round($verified * $points, 3)];
            $parents = $next;
        }

        $build = function (int $parentId) use (&$build, &$byParent): array {
            return array_map(fn ($n) => $n + ['children' => $build($n['id'])], $byParent[$parentId] ?? []);
        };

        return ['levels' => $stats, 'nodes' => $build($user->id), 'truncated' => $truncated];
    }

    /** Xalı göstərmək üçün: 3 → "3", 1.5 → "1,5", 0.25 → "0,25", 0.125 → "0,125" */
    public static function format(float $points): string
    {
        return rtrim(rtrim(number_format($points, 3, ',', ' '), '0'), ',');
    }
}
