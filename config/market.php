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
| "cost" — neçə xal (kəsrli ola bilər, məs. 2.5), "icon" — Font Awesome ikonu.
*/

return [
    'code_valid_days' => (int) env('MARKET_CODE_DAYS', 30), // alınan kupon neçə gün etibarlıdır
    'per_day' => (int) env('MARKET_PER_DAY', 2),            // bir istifadəçi gündə neçə hədiyyə ala bilər (Bakı vaxtı ilə gün)
    'min_bill' => 10,                                        // hədiyyədən istifadə üçün minimal masa hesabı (AZN) — qaydalarda göstərilir

    // Marketdə ucuzdan bahaya doğru düzülür (eyni qiymətdə — bu siyahıdakı sıra ilə)
    'items' => [
        ['key' => 'market_baklava',     'label' => 'Südlü Paxlava',             'cost' => 1,  'icon' => 'fa-stroopwafel',    'desc' => 'Bir porsiya südlü paxlava.'],
        ['key' => 'market_pepsi_can',   'label' => 'Pepsi (banka)',             'cost' => 3,  'icon' => 'fa-glass-whiskey',  'desc' => 'Bir banka soyuq Pepsi.'],
        ['key' => 'market_tea',         'label' => 'Çaynik çay',                'cost' => 4,  'icon' => 'fa-mug-hot',        'desc' => 'Bir çaynik təzə dəmlənmiş çay.'],
        ['key' => 'market_snickers',    'label' => 'Snikers Tortu',             'cost' => 4,  'icon' => 'fa-birthday-cake',  'desc' => 'Bir dilim Snikers tortu.'],
        ['key' => 'market_honey_cake',  'label' => 'Ballı Tort',                'cost' => 4,  'icon' => 'fa-birthday-cake',  'desc' => 'Bir dilim ballı tort.'],
        ['key' => 'market_pepsi_1l',    'label' => 'Pepsi 1 L',                 'cost' => 4,  'icon' => 'fa-wine-bottle',    'desc' => 'Bir litrlik Pepsi.'],
        ['key' => 'market_chips',       'label' => 'Cips',                      'cost' => 4,  'icon' => 'fa-cookie',         'desc' => 'Bir paket cips.'],
        ['key' => 'market_jam',         'label' => 'Mürəbbə',                   'cost' => 5,  'icon' => 'fa-apple-alt',      'desc' => 'Ev mürəbbəsi — çayla birlikdə.'],
        ['key' => 'market_salad',       'label' => 'Salat',                     'cost' => 5,  'icon' => 'fa-leaf',           'desc' => 'Bir porsiya təzə salat.'],
        ['key' => 'market_chocolate',   'label' => 'Şokolad',                   'cost' => 5,  'icon' => 'fa-cookie-bite',    'desc' => 'Çayın yanına şokolad.'],
        ['key' => 'market_nuts',        'label' => 'Çərəz',                     'cost' => 5,  'icon' => 'fa-seedling',       'desc' => 'Çayın yanına bir boşqab çərəz.'],
        ['key' => 'market_shawarma',    'label' => 'Şaurma',                    'cost' => 5,  'icon' => 'fa-hotdog',         'desc' => 'Bir ədəd şaurma.'],
        ['key' => 'market_chicken_wrap', 'label' => 'Toyuq Dürüm',              'cost' => 5,  'icon' => 'fa-drumstick-bite', 'desc' => 'Bir ədəd toyuq dürüm.'],
        ['key' => 'market_chicken',     'label' => 'Toyuq Yeməyi',              'cost' => 6,  'icon' => 'fa-drumstick-bite', 'desc' => 'Bir porsiya toyuq yeməyi.'],
        ['key' => 'market_meat',        'label' => 'Ət Yeməyi',                 'cost' => 8,  'icon' => 'fa-utensils',       'desc' => 'Bir porsiya ət yeməyi.'],
        ['key' => 'market_business',    'label' => 'Biznes Lunch',              'cost' => 8,  'icon' => 'fa-briefcase',      'desc' => 'Bir nəfərlik biznes lunch.'],
        ['key' => 'market_hookah',      'label' => 'Sadə Qəlyan',               'cost' => 10, 'icon' => 'fa-smoking',        'desc' => 'Bir sadə qəlyan.'],
        ['key' => 'market_khingal_set', 'label' => '2 nəfərlik Xəngəl Seti',    'cost' => 22, 'icon' => 'fa-concierge-bell', 'desc' => 'İki nəfərlik xəngəl seti.'],
    ],
];
