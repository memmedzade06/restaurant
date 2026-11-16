<?php

/*
|--------------------------------------------------------------------------
| Oyunların ayarları
|--------------------------------------------------------------------------
| Bəxt çarxının sektorları buradan dəyişdirilir. "weight" — düşmə ehtimalı
| (böyük ədəd = tez-tez düşür). "prize" => false olan sektor hədiyyə vermir.
| Sektorların sırası çarxdakı sıradır (saat əqrəbi istiqamətində).
*/

return [

    'wheel' => [
        'code_valid_days' => 7,   // hədiyyə kodu neçə gün etibarlıdır
        // Hər istifadəçiyə gündə neçə fırlatma cəhdi verilir. Hər fırlatmadan sonra oyunçu
        // ya "Hədiyyəni götür" basır (kod yaranır, raund bitir), ya da yenidən fırladır.
        'attempts_per_day' => (int) env('WHEEL_ATTEMPTS_PER_DAY', 3),
        'segments' => [
            // Böyük endirimlər daha nadir düşür; "Şansını bir daha sına" hədiyyə vermir
            // Sıra elə seçilib ki, eyni nəticələr nə yan-yana, nə də üzbəüz olmasın
            ['key' => 'discount_10',   'label' => '10% endirim', 'color' => '#7E1A1A', 'weight' => 14, 'prize' => true],
            ['key' => 'discount_15',   'label' => '15% endirim', 'color' => '#C9831F', 'weight' => 8,  'prize' => true],
            ['key' => 'discount_10_b', 'label' => '10% endirim', 'color' => '#7E1A1A', 'weight' => 14, 'prize' => true],
            ['key' => 'free_tea',      'label' => 'Çaynik çay Hədiyyə', 'color' => '#C9831F', 'weight' => 8,  'prize' => true], // qəlyanla üzbəüz
            ['key' => 'discount_20',   'label' => '20% endirim', 'color' => '#3A0D0D', 'weight' => 4,  'prize' => true],
            ['key' => 'try_again',     'label' => 'Şansını bir daha sına', 'color' => '#5B6B73', 'weight' => 30, 'prize' => false],
            ['key' => 'discount_25',   'label' => '25% endirim', 'color' => '#3A0D0D', 'weight' => 2,  'prize' => true],
            ['key' => 'free_hookah',   'label' => 'Qəlyan Hədiyyə', 'color' => '#E9A23B', 'weight' => 1,  'prize' => true], // ən nadir
        ],
    ],

    'stack' => [
        // Bu xala çatanda oyunçuya hədiyyə təklif olunur: ya "Hədiyyəni qəbul et" (kod verilir, oyun bitir),
        // ya da davam edir — qüllə aşsa, hədiyyə yanır.
        // Sonuncu hədəf — finiş: oyun bitir, davam etmək olmur. Test üçün .env-də STACK_FINISH_SCORE=10 yazmaq olar
        // (finişdən yuxarı hədəflər avtomatik çıxarılır).
        'prizes' => (function () {
            $finish = max(1, (int) env('STACK_FINISH_SCORE', 100));
            $prizes = array_filter([
                50 => ['key' => 'stack_tea', 'label' => 'Çay Hədiyyə'],
                60 => ['key' => 'stack_hookah', 'label' => 'Qəlyan Hədiyyə'],
                70 => ['key' => 'stack_discount_30', 'label' => 'Hesaba 30% endirim'],
            ], fn ($min) => $min < $finish, ARRAY_FILTER_USE_KEY);

            return $prizes + [$finish => ['key' => 'stack_coupon_30', 'label' => '30 AZN kupon']];
        })(),
        'prizes_per_day' => (int) env('STACK_PRIZES_PER_DAY', 1), // istifadəçiyə gündə neçə hədiyyə
        // Saxtakarlığa qarşı: oyun başlayandan bəri ən azı (stəkan sayı × bu qədər) saniyə keçməlidir.
        // Hər stəkan 1 xal verdiyi üçün stəkan sayı = xal.
        'min_seconds_per_glass' => 0.8,
    ],

    // Stəkan qülləsində Bakının indiki havasına uyğun effektlər (yağış, külək, günəş...).
    // Hava Open-Meteo-dan götürülür; STACK_WEATHER=false ilə söndürmək olar.
    'weather' => [
        'enabled' => (bool) env('STACK_WEATHER', true),
        'lat' => 40.4093,
        'lon' => 49.8671,
        'cache_minutes' => 20,
    ],

    'nard' => [
        'points_win' => 1,   // adi qələbə
        'points_mars' => 2,  // mars (rəqib heç daş çıxara bilməyib)
    ],

];
