<?php

/*
|--------------------------------------------------------------------------
| Dəvət (referal) sistemi
|--------------------------------------------------------------------------
| İstifadəçi profilindəki link və ya QR kodu paylaşır. Kimsə həmin link/QR ilə
| qeydiyyatdan keçib hesabını (Telegram, e-poçt və ya Google ilə) təsdiqləyəndə xal yazılır.
| Xal təsdiqdən sonra verilir — saxta e-poçtlarla açılan hesablar xal gətirmir.
|
| Üç səviyyə. Misal: Əli → Vəlini, Vəli → Həsəni, Həsən → Səbinəni dəvət edir.
| Səbinə təsdiqlənəndə: Həsənə 0.5 xal (birbaşa), Vəliyə 0.25, Əliyə 0.125.
| Daha uzaq səviyyələr xal almır. Xallar bazada 3 onluq rəqəmlə saxlanılır (0.125).
*/

return [
    // [1-ci səviyyə (birbaşa dəvət), 2-ci (dostunuzun dəvəti), 3-cü (dostunuzun dostunun dəvəti)]
    'levels' => [
        (float) env('REFERRAL_POINTS_1', 0.5),
        (float) env('REFERRAL_POINTS_2', 0.25),
        (float) env('REFERRAL_POINTS_3', 0.125),
    ],
    'cookie_days' => 30, // linki açıb sonra qeydiyyatdan keçənlər də sayılır (bu qədər gün)
];
