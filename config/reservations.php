<?php

/*
|--------------------------------------------------------------------------
| Masa rezervasiyası
|--------------------------------------------------------------------------
| Masaların düzülüşü restoranın kassa ekranındakı plana uyğundur (səliqəli sütunlarla).
| x, y, w, h — planın eninə/hündürlüyünə görə faizlə (0–100).
| Açar ("1-k1", "2-m25") bazada saxlanılır — dəyişdirməyin.
*/

// kind: table — 4 nəfərlik masa, sofa — divan, okey — okey masası, cabin — kabinet
$t = fn (string $label, float $x, float $y, float $w, float $h, string $kind = 'table') => compact('label', 'x', 'y', 'w', 'h', 'kind');

return [
    // neçə gün qabağa rezerv etmək olar
    'days_ahead' => (int) env('RESERVATION_DAYS_AHEAD', 30),
    // gəliş saatları (Bakı vaxtı) və addım (dəqiqə)
    'open' => env('RESERVATION_OPEN', '10:00'),
    'last' => env('RESERVATION_LAST', '22:00'),
    'step' => 30,
    // bir rezervasiya masanı neçə saat tutur (bu müddətdə eyni masaya başqa rezerv olmur)
    'hours' => (float) env('RESERVATION_HOURS', 2),
    'timezone' => 'Asia/Baku',

    // masa növləri: planda və pəncərədə göstərilir
    'kinds' => [
        'table' => ['name' => '4 nəfərlik masa', 'short' => '4 nəfər', 'icon' => 'fa-chair'],
        'sofa' => ['name' => 'Divanlı masa (üzbəüz divanlar)', 'short' => 'Divanlı', 'icon' => 'fa-couch'],
        'okey' => ['name' => 'Okey masası (4 nəfər)', 'short' => 'Okey', 'icon' => 'fa-dice'],
        'cabin' => ['name' => 'Kabinet', 'short' => 'Kabinet', 'icon' => 'fa-door-closed'],
    ],

    // kabinetlərin içində nə var: sofa — üzbəüz divanlar və masa, table — stullu masa
    'cabin_furniture' => ['1-k1' => 'sofa', '1-k2' => 'table'],

    'floors' => [
        1 => [
            'name' => '1-ci mərtəbə',
            'ratio' => 0.91, // en / hündürlük (masalar böyüdükcə plan da uzanır)
            'max_vh' => 78,
            'tables' => [
                '1-k1' => $t('Kabinet 1', 6.27, 4, 37.95, 16, 'cabin'),
                '1-k2' => $t('Kabinet 2', 55.77, 4, 37.95, 16, 'cabin'),
                '1-m4' => $t('Masa 4', 7.15, 28, 18.7, 14),
                '1-m7' => $t('Masa 7', 40.65, 45.5, 18.7, 14),
                '1-m11' => $t('Masa 11', 74.15, 28, 18.7, 14),
                '1-m3' => $t('Masa 3', 7.15, 45.5, 18.7, 14),
                '1-m10' => $t('Masa 10', 74.15, 45.5, 18.7, 14),
                '1-m6' => $t('Masa 6', 40.65, 63, 18.7, 14),
                '1-m2' => $t('Masa 2', 7.15, 63, 18.7, 14),
                '1-m9' => $t('Masa 9', 74.15, 63, 18.7, 14),
                '1-m5' => $t('Masa 5', 40.65, 80.5, 18.7, 14),
                '1-m1' => $t('Masa 1', 7.15, 80.5, 18.7, 14),
                '1-m8' => $t('Masa 8', 74.15, 80.5, 18.7, 14),
            ],
        ],
        2 => [
            'name' => '2-ci mərtəbə',
            'ratio' => 1.29,
            'max_vh' => 112, // plan ekranda əvvəlki enində qalsın (pilləkən üçün aşağıdan uzadılıb)
            // rezerv olunmayan əşyalar: ən solda monitor, Masa 21–25 onun qabağı boyunca düzülüb
            'decor' => [
                ['label' => 'Monitor', 'type' => 'screen', 'x' => 0, 'y' => 30, 'w' => 3, 'h' => 40], // Masa 21–25-in ortasına görə
                // beton sütun (kvadrat: hündürlük = en × plan nisbəti)
                ['label' => 'SÜTUN', 'type' => 'pillar', 'x' => 46, 'y' => 18.78, 'w' => 5.5, 'h' => 7.12],
                ['label' => 'SÜTUN', 'type' => 'pillar', 'x' => 46, 'y' => 57.41, 'w' => 5.5, 'h' => 7.12], // Masa 17 ilə eyni xətdə (yuxarıdakı Masa 20 ilə)
                // 1-ci mərtəbədən qalxan pilləkən (Masa 21 və 17-nin altında)
                ['label' => 'Pilləkən', 'type' => 'stairs', 'x' => 22, 'y' => 82.6, 'w' => 22, 'h' => 12.05],
            ],
            'tables' => [
                '2-m25' => $t('Masa 25', 2, 1.98, 11, 14.83),
                '2-m14' => $t('Masa 14', 62.52, 1.98, 10.45, 11.37),
                '2-m7' => $t('Masa 7', 77.2, 1.98, 17.6, 12.36, 'sofa'),
                '2-m24' => $t('Masa 24', 2, 22.29, 11, 14.83),
                '2-m20' => $t('Masa 20', 24.2, 16.16, 17.6, 12.36, 'sofa'),
                '2-m13' => $t('Masa 13', 62.52, 15.72, 10.45, 11.37),
                '2-m6' => $t('Masa 6', 77.2, 15.72, 17.6, 12.36, 'sofa'),
                '2-m16' => $t('Masa 16', 43.52, 29.53, 10.45, 11.37),
                '2-m12' => $t('Masa 12', 62.52, 29.47, 10.45, 11.37),
                '2-m5' => $t('Masa 5', 77.2, 29.47, 17.6, 12.36, 'sofa'),
                '2-m23' => $t('Masa 23', 2, 42.6, 11, 14.83),
                '2-m19' => $t('Masa 19', 24.2, 29.91, 17.6, 12.36, 'sofa'),
                '2-m11' => $t('Masa 11', 62.52, 43.21, 10.45, 11.37),
                '2-m4' => $t('Masa 4', 77.2, 43.21, 17.6, 12.36, 'sofa'),
                '2-m15' => $t('Masa 15', 43.52, 43.72, 10.45, 11.37),
                '2-m3' => $t('Masa 3', 77.2, 56.96, 17.6, 12.36, 'sofa'),
                '2-m18' => $t('Masa 18', 24.2, 43.66, 17.6, 12.36, 'sofa'),
                '2-m10' => $t('Masa 10', 62.52, 56.96, 10.45, 11.37),
                '2-m22' => $t('Masa 22', 2, 62.91, 11, 14.83),
                '2-m2' => $t('Masa 2', 77.2, 70.71, 17.6, 12.36, 'sofa'),
                '2-m9' => $t('Masa 9', 62.52, 70.21, 10.45, 11.37, 'okey'),
                '2-m17' => $t('Masa 17', 24.2, 57.41, 17.6, 12.36, 'sofa'),
                '2-m21' => $t('Masa 21', 2, 83.22, 11, 14.83),
                '2-m8' => $t('Masa 8', 62.52, 84.45, 10.45, 12.36, 'sofa'),
                '2-m1' => $t('Masa 1', 77.2, 84.45, 17.6, 12.36, 'sofa'),
            ],
        ],
    ],
];
