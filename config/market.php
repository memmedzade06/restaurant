<?php

/*
|--------------------------------------------------------------------------
| Hədiyyə marketi
|--------------------------------------------------------------------------
| Xallar oyunçunun balansıdır: marketdə xalları hədiyyəyə çevirmək olar.
| Hədiyyə seçiləndə qiyməti balansdan çıxılır və oyunçuya kupon kodu verilir
| (Hədiyyələrim səhifəsində görünür, ofisiant admin paneldə "istifadə olundu" edir).
|
| Hədiyyə əlavə etmək, silmək və ya qiymətini dəyişmək üçün aşağıdakı siyahını dəyişin.
| "cost" — neçə xal (kəsrli ola bilər, məs. 2.5), "icon" — Font Awesome ikonu,
| "image" — public/ altında şəkil (yoxdursa ikon göstərilir).
*/

return [
    'code_valid_days' => (int) env('MARKET_CODE_DAYS', 1),  // alınan kupon neçə gün etibarlıdır (1 — alındığı andan 24 saat)
    'per_day' => (int) env('MARKET_PER_DAY', 1),            // bir istifadəçi gündə neçə hədiyyə ala bilər (Bakı vaxtı ilə gün)
    'small_max' => 3,                                        // bu qədər xala qədər olan hədiyyə yarım sayılır: 1 hədiyyənin yerinə 2 dənə seçmək olar
    'min_bill' => 10,                                        // hədiyyədən istifadə üçün minimal masa hesabı (AZN) — qaydalarda göstərilir

    // Menyudan (config/market_menu.php) markete düşməyən kateqoriyalar — adı ilə; sinxronizasiyadan sonra da gizli qalır
    'menu_exclude' => ['Kabablar', 'Kombo Menyu', 'Şorbalar (YARI)', 'Qazan Yemekleri (YARI)'],

    // Menyudan markete düşməyən ayrı-ayrı məhsullar — adı ilə
    'menu_item_exclude' => [
        'Nar Şirəsi', 'Kokteyl',
        // Çay Dəstgahı
        'Biskivit', 'Sansebastian', 'Mürəbbə Snickers', 'Mürəbbə Kakos', 'Murebbe Plombir', 'Mürəbbə Bounty',
        'Banan Şirniyyat', 'Milli Paxlava', 'Fıstıq', 'Qoz Şirniyyat', 'Şokoladlı paxlava', 'Mutaki', 'Ekler',
        'Samovar Çayı (armudu stəkan)',
    ],

    // Menyu kateqoriyasının adını dəyiş və yalnız uyğun məhsulları saxla ("only" — məhsul adına regex);
    // "image" — kateqoriyanın şəklini əvəz edir (menyu sinxronizasiyası bunu dəyişmir)
    'menu_filter' => [
        'Qəlyan Seti' => ['label' => 'Qəlyan', 'only' => '/^Qəlyan/u', 'image' => 'images/market/qelyan.jpg'], // yalnız qəlyanlar, "Set ..." kombolar yox
    ],

    // Seçilmiş hədiyyələr (ayrıca "Seçilmiş hədiyyələr" kateqoriyası). Boşdursa kateqoriya görünmür —
    // marketdə yalnız menyu (config/market_menu.php) olur. Nümunə:
    // ['key' => 'market_tea', 'label' => 'Çaynik çay', 'cost' => 4, 'icon' => 'fa-mug-hot', 'desc' => 'Bir çaynik çay.', 'image' => 'images/market/tea.jpg'],
    'items' => [],
];
