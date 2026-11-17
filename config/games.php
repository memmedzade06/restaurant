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
        // Çarx restoranda, masada oynanılır və endirim həmin masanın cari hesabına tətbiq olunur,
        // ona görə kod yalnız bu ziyarət üçündür: bu qədər dəqiqədən sonra avtomatik qüvvədən düşür
        // (müddəti bitmiş kuponlar — istifadə olunmuşlar da — bazadan avtomatik silinir — WheelSpin::pruneExpired).
        'code_valid_minutes' => 10,
        // Hər istifadəçiyə gündə neçə fırlatma cəhdi verilir. Hər fırlatmadan sonra oyunçu
        // ya "Hədiyyəni götür" basır (kod yaranır, raund bitir), ya da yenidən fırladır.
        'attempts_per_day' => (int) env('WHEEL_ATTEMPTS_PER_DAY', 3),
        'segments' => [
            // Böyük endirimlər daha nadir düşür; "Şansını bir daha sına" hədiyyə vermir
            // Sıra elə seçilib ki, eyni nəticələr nə yan-yana, nə də üzbəüz olmasın
            // Çəkilər tam ədəd olmalıdır; cəmi 812 — sektorun şansı = weight / 812
            ['key' => 'discount_10',   'label' => '10% endirim', 'color' => '#7E1A1A', 'weight' => 140, 'prize' => true], // ~17%
            ['key' => 'discount_15',   'label' => '15% endirim', 'color' => '#C9831F', 'weight' => 80,  'prize' => true], // ~10%
            ['key' => 'discount_10_b', 'label' => '10% endirim', 'color' => '#7E1A1A', 'weight' => 140, 'prize' => true], // ~17%
            ['key' => 'free_tea',      'label' => 'Çaynik çay Hədiyyə', 'color' => '#C9831F', 'weight' => 80, 'prize' => true], // ~10%
            ['key' => 'discount_20',   'label' => '20% endirim', 'color' => '#3A0D0D', 'weight' => 40,  'prize' => true], // ~5%
            ['key' => 'try_again',     'label' => 'Şansını bir daha sına', 'color' => '#5B6B73', 'weight' => 300, 'prize' => false], // ~37%
            ['key' => 'discount_50',   'label' => '50% endirim', 'color' => '#B3261E', 'weight' => 2,   'prize' => true], // ~0.25% — ən nadir (təxminən 400 fırlatmada bir)
            ['key' => 'discount_25',   'label' => '25% endirim', 'color' => '#3A0D0D', 'weight' => 20,  'prize' => true], // ~2.5%
            ['key' => 'free_hookah',   'label' => 'Qəlyan Hədiyyə', 'color' => '#E9A23B', 'weight' => 10,  'prize' => true], // ~1.2%
        ],
    ],

    // Hədiyyə Kranı: tutulan şarın içindən hədiyyə çıxır. Gündə bu qədər cəhd; hər şar açılandan sonra
    // oyunçu ya "Hədiyyəni götür" basır (kod yaranır, gün bağlanır), ya da yenidən oynayır (əvvəlki hədiyyə itir).
    // Şarın içindəkini server seçir — "weight" düşmə ehtimalıdır (cəmi 812; şans = weight / 812). Boş şar da ola bilər.
    'claw' => [
        'attempts_per_day' => (int) env('CLAW_ATTEMPTS_PER_DAY', 3),
        'code_valid_minutes' => 10,
        'min_seconds_per_attempt' => 1.5,   // çəngəl enib-qalxıb çıxışa çatana qədər ən az bu qədər vaxt keçir
        'prizes' => [
            ['key' => 'claw_empty',  'label' => 'Boş şar',            'weight' => 300, 'prize' => false], // ~36.9%
            ['key' => 'claw_tea',    'label' => 'Çaynik çay Hədiyyə', 'weight' => 280, 'prize' => true],  // ~34.5% — ən çox düşən hədiyyə
            ['key' => 'claw_10',     'label' => '10% endirim',        'weight' => 80,  'prize' => true],  // ~9.9%
            ['key' => 'claw_jam',    'label' => 'Mürəbbə Hədiyyə',    'weight' => 80,  'prize' => true],  // ~9.9%
            ['key' => 'claw_nuts',   'label' => 'Çərəz Hədiyyə',      'weight' => 40,  'prize' => true],  // ~4.9%
            ['key' => 'claw_hookah', 'label' => 'Qəlyan Hədiyyə',     'weight' => 20,  'prize' => true],  // ~2.5%
            ['key' => 'claw_25',     'label' => '25% endirim',        'weight' => 10,  'prize' => true],  // ~1.2%
            ['key' => 'claw_50',     'label' => '50% endirim',        'weight' => 2,   'prize' => true],  // ~0.25% (təxminən 400 şarda bir)
        ],
    ],

    'stack' => [
        // Bu xala çatanda oyunçuya hədiyyə təklif olunur: ya "Hədiyyəni qəbul et" (kod verilir, oyun bitir),
        // ya da davam edir — qüllə aşsa, hədiyyə yanır.
        // Sonuncu hədəf — finiş: ən böyük hədiyyə; götürdükdən sonra da rekord üçün oynamağa davam etmək olur.
        // Test üçün .env-də STACK_FINISH_SCORE=10 yazmaq olar
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
        'code_valid_days' => 1,   // hədiyyə kodu neçə gün etibarlıdır — 24 saatdan sonra avtomatik qüvvədən düşür
        // Botlara qarşı: oyun hər qonan stəkanı [vaxt ms, sapma px] kimi göndərir, server yoxlayır.
        'min_glass_interval_ms' => 300,  // iki stəkan arasında ən az fasilə (stəkanın düşmə vaxtı)
        'max_offset_px' => 26,           // stəkanın aşmadan ala biləcəyi ən böyük sapma
        'bot_check_from' => 20,          // bu xaldan yuxarı "insan kimi oynayırmı" yoxlanır:
        'min_rhythm_spread' => 0.05,     //   fasilələrin səpələnməsi bundan azdırsa — maşın ritmi
        'max_exact_share' => 0.5,        //   stəkanların bu hissəsindən çoxu 0.3 px-dən dəqiqdirsə — bot
    ],

    // Stəkan qülləsində Bakının indiki havasına uyğun effektlər (yağış, külək, günəş...).
    // Hava Open-Meteo-dan götürülür; STACK_WEATHER=false ilə söndürmək olar.
    'weather' => [
        'enabled' => (bool) env('STACK_WEATHER', true),
        'lat' => 40.4093,
        'lon' => 49.8671,
        'cache_minutes' => 20,
    ],

    // Nərd: xal YALNIZ dostla (iki nəfərlik) oyunda verilir, kompüterə qarşı oyun xalsızdır.
    // Gün gecə 00:00-da (Bakı vaxtı) başlayır; günün ən çox xal toplayanları qalib olur.
    'nard' => [
        'points_win' => 1,        // adi qələbə
        'points_mars' => 2,       // mars (rəqib heç daş çıxara bilməyib)
        'pair_daily_limit' => 3,  // eyni iki oyunçu arasında gündə neçə oyun xal verir (iki hesabla xal yığmağın qarşısını alır)
        // Günün qaliblərinə avtomatik hədiyyə kodu verilir (Hədiyyələrim səhifəsində görünür).
        // Gecə 00:00-da günün nəticələri silinir, qaliblər nard_daily_winners cədvəlində qalır.
        'daily_prizes' => [
            1 => ['key' => 'nard_day_discount_20', 'label' => '20% endirim kuponu hədiyyə'],
            2 => ['key' => 'nard_day_hookah', 'label' => 'Qəlyan hədiyyə'],
            3 => ['key' => 'nard_day_tea', 'label' => 'Çaynik çay hədiyyə'],
        ],
        'prize_valid_days' => 1,  // qalib kodu neçə gün etibarlıdır — verildikdən 24 saat sonra avtomatik qüvvədən düşür
    ],

];
