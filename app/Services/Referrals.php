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
 *  3. Yeni istifadəçi nömrəsini Telegram ilə təsdiqləyəndə dəvət edənin balansına xal yazılır (bir dəfə).
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
     * Nömrə təsdiqlənəndə: dəvət zənciri boyunca yuxarı xal yazılır — birbaşa dəvət edənə 1,
     * onu dəvət edənə 0.5, ondan yuxarıdakına 0.25 (config referral.levels).
     * Hər yeni istifadəçi üçün yalnız bir dəfə (referral_rewarded_at), eyni anda iki sorğu gəlsə də.
     */
    public static function reward(User $user): void
    {
        if (! $user->referred_by || $user->referral_rewarded_at) {
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
                if ($points > 0) {
                    User::whereKey($upId)->increment('points', $points);
                }
                $upId = User::whereKey($upId)->value('referred_by');
            }
        });
    }

    /** Xalı göstərmək üçün: 3 → "3", 1.5 → "1,5", 0.25 → "0,25", 0.125 → "0,125" */
    public static function format(float $points): string
    {
        return rtrim(rtrim(number_format($points, 3, ',', ' '), '0'), ',');
    }
}
