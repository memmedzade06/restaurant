<?php

namespace App\Services;

/**
 * Süfrəni doldur oyununun hakimi.
 *
 * Oyun başlayanda server təsadüfi "toxum" (seed) seçir və ondan bütün düşən əşyaların siyahısını yaradır:
 * hər biri nə vaxt çıxır, harada, hansı sürətlə düşür, nemətdir (şirniyyat, çay, qızıl çaynik), yoxsa közərmiş kömür. Siyahı brauzerə gedir, brauzer onu çəkir.
 * Oyun bitəndə brauzer xal yox, yalnız hər əşya sininin xəttinə çatan anda sininin harada olduğunu göndərir.
 * Server həmin toxumdan siyahını yenidən qurur və hesabı özü hesablayır: hansı nemət tutulub, kömür siniyə düşübmü,
 * kombo, canlar, oyunun nə vaxt bitdiyi. Saxta "999 xal" göndərmək mənasızdır.
 *
 * Bütün düsturlar public/js/games/sufre.js-də eynidir — birini dəyişsəniz, o birini də dəyişin.
 */
class SufreGame
{
    public const WIDTH = 360;           // oyun sahəsinin məntiqi eni
    public const HEIGHT = 560;          // və hündürlüyü
    public const START_Y = -40;         // əşya ekranın yuxarısından çıxır
    public const CATCH_Y = 498;         // sininin üstü: əşya bu xəttə çatanda tutulub-tutulmadığı yoxlanır
    public const BASKET_HALF = 48;      // sininin başlanğıcdakı yarı eni: əşya sininin ortasından bu qədər uzaqdadırsa — tutulmayıb
    // Oynadıqca sini kiçilir: SHRINK_FROM saniyədən sonra hər saniyədə SHRINK_RATE px, ən kiçiyi HALF_MIN (bax: halfAt)
    public const SHRINK_FROM = 30;
    public const SHRINK_RATE = 0.2;
    public const HALF_MIN = 30;
    // Çətinlik: ilk EASY_SECONDS saniyə asandır, sonra səviyyə 20 saniyədə 0-dan 1-ə qalxır və hər 60 saniyədə
    // daha bir vahid artır (ən çox MAX_LEVEL): nemətlər sürətlənir, kömür çoxalır, əşyalar daha sıx düşür
    public const EASY_SECONDS = 10;
    public const MAX_LEVEL = 3;
    public const LIVES = 1;            // bir can: siniyə düşən ilk kömür oyunu bitirir
    public const MAX_COMBO = 5;
    public const COMBO_STEP = 4;        // hər ardıcıl 4 nemətdə kombo bir pillə artır (x1 → x2 → … → x5)
    // şirniyyat/çərəz, şokolad, armudu stəkanda çay, qızıl çaynik, qəlyan, kömür
    public const VALUES = ['n' => 1, 'c' => 2, 'f' => 3, 'g' => 5, 'h' => 10, 'b' => 0];
    // adi nemətlərin (növ 'n') öz xalı, item[5]-ə görə: paxlava, şəkərbura, qənd, limon, qoz
    public const SWEET_VALUES = [2, 2, 1, 1, 1];

    /** Toxum → əşyaların siyahısı: [[çıxma vaxtı s, növ, x, sürət px/s, faza, rəng], ...] */
    public static function items(int $seed, float $maxSeconds): array
    {
        $state = $seed & 0x7fffffff ?: 1;
        $rand = function () use (&$state): float {
            $state = ($state * 1103515245 + 12345) & 0x7fffffff;

            return $state / 0x80000000;
        };

        $items = [];
        $t = 0.6;
        while ($t < $maxSeconds) {
            $lvl = self::level($t);
            $r = $rand();
            $coal = min(0.18 + $lvl * 0.12, 0.45); // kömürün payı: 18% → 30% (40 s) → … ən çox 45%
            $kind = match (true) {
                $r < $coal => 'b',
                $r < $coal + 0.07 => 'g',
                $r < $coal + 0.14 => 'h', // qəlyan: qızıl çaynik qədər tez-tez (payı adi nemətlərdən götürülüb)
                $r < $coal + 0.26 => 'f',
                default => 'n',
            };
            $x = round(30 + $rand() * (self::WIDTH - 60), 1);
            $jitter = $rand() * 25;
            $vy = round(match ($kind) {
                'f' => 170 + $lvl * 40, // çay tez düşür
                'b' => 105 + $lvl * 95 + $jitter,
                default => 85 + $lvl * 75 + $jitter,
            }, 1);
            $phase = round($rand() * 6.283, 3);
            $sub = (int) floor($rand() * 6); // adi nemətin növü: paxlava, şəkərbura, qənd, limon, qoz, şokolad
            if ($kind === 'n' && $sub === 5) {
                $kind = 'c'; // şokolad — ayrıca xalı var (adi nemətlər qədər tez-tez düşür)
            }
            $items[] = [round($t, 3), $kind, $x, $vy, $phase, $sub];
            $t += max(0.3, 1.0 - $lvl * 0.6); // əşyalar arası fasilə: 1 s → … → 0,3 s (çətinliyə görə)
        }

        return $items;
    }

    /** Çətinlik səviyyəsi t anında (0 — ilk 10 s, 1 — 30 s, sonra hər 60 s-də +1, ən çox MAX_LEVEL) */
    public static function level(float $t): float
    {
        $t -= self::EASY_SECONDS;
        if ($t <= 0) {
            return 0.0;
        }

        return min(min($t / 20, 1) + max(0, ($t - 20) / 60), self::MAX_LEVEL);
    }

    /** Sininin t anındakı yarı eni: 30 s-dən sonra yavaş-yavaş kiçilir */
    public static function halfAt(float $t): float
    {
        return max(self::HALF_MIN, self::BASKET_HALF - max(0, $t - self::SHRINK_FROM) * self::SHRINK_RATE);
    }

    /** Tutulan əşyanın xalı (komboya vurulmamış) */
    public static function value(array $item): int
    {
        return $item[1] === 'n' ? (self::SWEET_VALUES[$item[5]] ?? 1) : self::VALUES[$item[1]];
    }

    /** Əşyanın sini xəttinə çatdığı an (oyun başlayandan saniyə) */
    public static function crossTime(array $item): float
    {
        return $item[0] + (self::CATCH_Y - self::START_Y) / $item[3];
    }

    /** Əşyanın t anındakı üfüqi yeri: nemətlər yellənə-yellənə düşür, kömür azca titrəyir */
    public static function itemX(array $item, float $t): float
    {
        return $item[1] === 'b'
            ? $item[2] + 2 * sin($item[4] + $t * 3)
            : $item[2] + 6 * sin($item[4] + $t * 2);
    }

    /**
     * Oyunun nəticəsini hesablayır.
     * $basket[i] — i-ci əşya xəttə çatanda sininin ortasının x-i (çatmayıbsa null).
     * $elapsed — oyun başlayandan real olaraq keçən saniyə (serverin saatı ilə).
     * $until — oyun bu anda dayandırılıb (hədiyyə götürülür): yalnız bu ana qədər xəttə çatan əşyalar sayılır.
     * Qaytarır: ['score' => ..., 'caught' => ..., 'bombs' => ..., 'seconds' => ...] və ya ['problem' => səbəb].
     */
    public static function referee(array $items, array $basket, float $elapsed, array $cfg, ?float $until = null): array
    {
        $maxSeconds = $until === null ? (float) $cfg['max_seconds'] : min((float) $cfg['max_seconds'], $until);
        $order = array_keys($items);
        $cross = array_map([self::class, 'crossTime'], $items);
        usort($order, fn ($a, $b) => $cross[$a] <=> $cross[$b]);

        $score = $caught = $bombs = $streak = $exact = 0;
        $end = $maxSeconds;
        $reported = [];
        $prev = null;
        foreach ($order as $i) {
            $t = $cross[$i];
            if ($t > $maxSeconds) {
                break; // vaxt bitdi
            }
            $bx = $basket[$i] ?? null;
            if ($bx === null) {
                return ['problem' => 'əşya buraxılıb'];
            }
            $bx = (float) $bx;
            $half = self::halfAt($t);
            if ($bx < $half - 1 || $bx > self::WIDTH - $half + 1) {
                return ['problem' => 'sini sahədən kənardadır'];
            }
            // sini bir yerdən o birinə "sıçraya" bilməz
            if ($prev && abs($bx - $prev[1]) > (float) $cfg['max_speed'] * ($t - $prev[0]) + 2) {
                return ['problem' => 'sini həddən artıq sürətlidir'];
            }
            $prev = [$t, $bx];
            $reported[$i] = true;

            $dist = abs(self::itemX($items[$i], $t) - $bx);
            $kind = $items[$i][1];
            if ($dist > $half) {
                if ($kind !== 'b') {
                    $streak = 0; // nemət yerə düşdü — kombo sıfırlanır
                }
                continue;
            }
            if ($kind === 'b') {
                $bombs++;
                $streak = 0;
                if ($bombs >= self::LIVES) {
                    $end = $t;
                    break;
                }
                continue;
            }
            $combo = min(self::MAX_COMBO, 1 + intdiv($streak, self::COMBO_STEP));
            $score += self::value($items[$i]) * $combo;
            $streak++;
            $caught++;
            if ($dist < 0.5) {
                $exact++;
            }
        }

        // oyun bitəndən sonrakı əşyalar üçün sini yeri ola bilməz
        foreach ($basket as $i => $bx) {
            if ($bx !== null && ! isset($reported[$i])) {
                return ['problem' => 'oyun bitəndən sonrakı əşya'];
            }
        }
        // oyunu sürətləndirib bir neçə saniyəyə "oynamaq" olmaz: oyun vaxtı real vaxtdan uzun ola bilməz
        if ($end > $elapsed + 3) {
            return ['problem' => 'oyun vaxtı real vaxtdan uzundur'];
        }
        // insan nemətin düz altına sinini nadir hallarda tutur; bot isə həmişə
        if ($caught >= (int) $cfg['bot_check_from'] && $exact / $caught > (float) $cfg['max_exact_share']) {
            return ['problem' => 'tutuşlar insan üçün həddən artıq dəqiqdir'];
        }

        return ['score' => $score, 'caught' => $caught, 'bombs' => $bombs, 'seconds' => round($end, 1)];
    }
}
