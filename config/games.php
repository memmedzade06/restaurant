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
            ['key' => 'discount_15_b', 'label' => '15% endirim', 'color' => '#C9831F', 'weight' => 8,  'prize' => true],
            ['key' => 'discount_20',   'label' => '20% endirim', 'color' => '#3A0D0D', 'weight' => 4,  'prize' => true],
            ['key' => 'try_again',     'label' => 'Şansını bir daha sına', 'color' => '#5B6B73', 'weight' => 18, 'prize' => false],
            ['key' => 'discount_25',   'label' => '25% endirim', 'color' => '#3A0D0D', 'weight' => 2,  'prize' => true],
            ['key' => 'free_hookah',   'label' => 'Qəlyan Hədiyyə', 'color' => '#E9A23B', 'weight' => 1,  'prize' => true], // ən nadir
        ],
    ],

    'nard' => [
        'points_win' => 1,   // adi qələbə
        'points_mars' => 2,  // mars (rəqib heç daş çıxara bilməyib)
    ],

];
