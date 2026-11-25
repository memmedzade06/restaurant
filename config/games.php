<?php

/*
|--------------------------------------------------------------------------
| Oyunların ayarları
|--------------------------------------------------------------------------
| Çarx oyununun sektorları buradan dəyişdirilir. Çarx məharət oyunudur: dayanmadan fırlanır,
| oyunçu "Dayan" basır, göstəricinin altındakı sektor düşür. "size" — sektorun eni (dərəcə;
| cəmi 360 olmalıdır, olmasa mütənasib bölünür): dar sektora düşmək çətindir.
| "prize" => false olan sektor hədiyyə vermir. "short" — dar sektorda görünən qısa yazı.
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
            // Böyük hədiyyə — dar sektor. İki "Çaynik çay" sektoru düz üzbəüzdür (mərkəzləri 42.5° və 222.5°):
            // ölçüləri dəyişəndə iki çaynik arasındakı sektorların cəmi hər tərəfdə 145° qalmalıdır.
            // Üç "Şansını bir daha sına" çarxa yayılıb (mərkəzləri 85°, 180°, 310°). 50% və qəlyan boz sektorun
            // yanındadır — azca səhv edən boşa düşür. Cəmi 360°. Hər hədiyyənin öz rəngi var (eyni rəng — eyni nəticə; boz — boş).
            ['key' => 'discount_10',   'label' => '10% endirim', 'color' => '#7E1A1A', 'size' => 50, 'prize' => true],     // şərab
            ['key' => 'free_tea',      'label' => 'Çaynik çay Hədiyyə', 'color' => '#2E6B4F', 'size' => 35, 'prize' => true], // çay yaşılı
            ['key' => 'try_again',     'label' => 'Şansını bir daha sına', 'short' => 'Yenə sına', 'color' => '#5B6B73', 'size' => 50, 'prize' => false],
            ['key' => 'free_nuts',     'label' => 'Çərəz Hədiyyə', 'color' => '#8A5A2B', 'size' => 25, 'prize' => true],   // qoz qəhvəyisi
            ['key' => 'discount_25',   'label' => '25% endirim', 'short' => '25%', 'color' => '#3A0D0D', 'size' => 13, 'prize' => true], // tünd qəhvəyi
            ['key' => 'free_hookah',   'label' => 'Qəlyan Hədiyyə', 'short' => 'Qəlyan', 'color' => '#5E2750', 'size' => 7, 'prize' => true], // gavalı; dar — nadir düşür
            ['key' => 'try_again_b',   'label' => 'Şansını bir daha sına', 'short' => 'Yenə sına', 'color' => '#5B6B73', 'size' => 50, 'prize' => false],
            ['key' => 'free_tea_b',    'label' => 'Çaynik çay Hədiyyə', 'color' => '#2E6B4F', 'size' => 35, 'prize' => true], // birinci çaynikin üzbəüzü
            ['key' => 'free_jam',      'label' => 'Mürəbbə Hədiyyə', 'color' => '#A8325E', 'size' => 42, 'prize' => true], // moruq
            ['key' => 'discount_50',   'label' => '50% endirim', 'short' => '50%', 'color' => '#D93A2B', 'size' => 3, 'prize' => true], // parlaq qırmızı; ən dar — ən çətin
            ['key' => 'try_again_c',   'label' => 'Şansını bir daha sına', 'short' => 'Yenə sına', 'color' => '#5B6B73', 'size' => 50, 'prize' => false],
        ],

        // Bütün oyunçular üçün gündəlik limit: bu qədər götürüləndən sonra sektor gün sonunadək "bağlı" olur
        // (ora düşən hədiyyə almır). Yaxşı oyunçular çox olsa da, böyük hədiyyələrin sayı nəzarətdə qalır.
        'daily_caps' => [
            'discount_50' => (int) env('WHEEL_CAP_50', 1),
            'discount_25' => (int) env('WHEEL_CAP_25', 3),
            'free_hookah' => (int) env('WHEEL_CAP_HOOKAH', 3),
        ],

        // Fırlanma: sürət (dərəcə/saniyə) hər növbəti cəhddə artır; üstəlik sürət "nəfəs alır"
        // (±wobble), ona görə sabit ritmlə basmaq olmur. Hər fırlatmanın başlanğıc bucağını,
        // istiqamətini və ritmini server təsadüfi seçir.
        'speeds' => [480, 600, 720],
        'speed_jitter' => 0.12,        // sürət ±12% təsadüfi dəyişir
        'wobble' => 0.35,              // sürət ±35% artıb-azalır
        'wobble_period' => [1.4, 2.6], // bir "nəfəs" neçə saniyə çəkir
        'max_seconds' => 5,            // bu müddətdə "Dayan" basılmasa çarx özü dayanır — hədiyyə yoxdur, cəhd uğursuzdur
        // İstiqamət dəyişməsi: çarx vaxtaşırı yavaşlayıb bir anlıq dayanır və əks tərəfə fırlanır.
        // Yavaşlama gözlə görünür — diqqətli oyunçu dönüşü qabaqcadan görür (məharət), ritmi əzbərləmək isə olmur.
        // Dönüş anlarını hər fırlatmada server təsadüfi seçir. 'reversal_gap' => [0, 0] — dönüşsüz.
        'reversal_first' => [1.0, 3.0], // ilk dönüş fırlatmadan neçə saniyə sonra
        'reversal_gap' => [1.5, 4.0],   // dönüşlər arası neçə saniyə
        'reversal_ms' => 500,           // dönüş (yavaşlama + əks tərəfə sürətlənmə) nə qədər çəkir
    ],

    // Hədiyyə Kranı: tutulan şarın içindən hədiyyə çıxır. Gündə bu qədər cəhd; hər şar açılandan sonra
    // oyunçu ya "Hədiyyəni götür" basır (kod yaranır, gün bağlanır), ya da yenidən oynayır (əvvəlki hədiyyə itir).
    // Şarın içindəkini server seçir — "weight" düşmə ehtimalıdır (cəmi 812; şans = weight / 812). Boş şar da ola bilər.
    'claw' => [
        // Müvəqqəti bağlıdır: oyunlar səhifəsində kartı görünmür, ünvanları 404 qaytarır.
        // Geri açmaq üçün .env-də CLAW_ENABLED=true
        'enabled' => (bool) env('CLAW_ENABLED', false),
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
                50 => ['key' => 'stack_tea', 'label' => 'Çaynik Çay Hədiyyə'],
                60 => ['key' => 'stack_hookah', 'label' => 'Qəlyan Hədiyyə'],
                70 => ['key' => 'stack_discount_30', 'label' => 'Hesaba 30% endirim'],
            ], fn ($min) => $min < $finish, ARRAY_FILTER_USE_KEY);

            return $prizes + [$finish => ['key' => 'stack_coupon_30', 'label' => '30 AZN kupon']];
        })(),
        'prizes_per_day' => (int) env('STACK_PRIZES_PER_DAY', 1), // istifadəçiyə gündə neçə hədiyyə
        // Bonus mərhələləri: [stəkan sayı => xal]. Təsdiqlənmiş qonaq bir oyunda mərhələyə çatanda balansına xal yazılır.
        // Hər mərhələ gündə BİR dəfə: bu gün 30 stəkana görə alıbsa, həmin gün 30-a görə yox, yalnız yuxarı mərhələlərə görə alır.
        // Bir oyunda bir neçə mərhələ keçilsə — hamısı (məs. 55 stəkan: 0,3 + 0,5). Gündə ən çox — cəmi (0,8).
        // .env: STACK_BONUS_TIERS="30:0.3,50:0.5"
        'bonus' => [
            'tiers' => (function () {
                $tiers = [];
                foreach (explode(',', (string) env('STACK_BONUS_TIERS', '30:0.3,50:0.5')) as $pair) {
                    [$score, $points] = array_map('trim', explode(':', $pair) + [1 => 0]);
                    if ((int) $score > 0 && (float) $points > 0) {
                        $tiers[(int) $score] = (float) $points;
                    }
                }
                ksort($tiers);

                return $tiers;
            })(),
        ],
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
        // Müvəqqəti gizlədilib: kartı, liderlər cədvəli, nəticələri və dəvətləri görünmür, ünvanları 404 qaytarır.
        // Kod və məlumatlar silinmir. Geri açmaq üçün .env-də NARD_ENABLED=true
        'enabled' => (bool) env('NARD_ENABLED', false),
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
