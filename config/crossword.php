<?php

/*
|--------------------------------------------------------------------------
| Krossvord: sözlər və izahları
|--------------------------------------------------------------------------
| Hər gün bu siyahıdan təsadüfi sözlər seçilib krossvord avtomatik qurulur.
| Söz BÖYÜK hərflə, boşluqsuz yazılır (İ — nöqtəli, I — nöqtəsiz). Yeni söz əlavə etmək
| üçün siyahıya ['SÖZ', 'izah'] sətri yazmaq kifayətdir — 3-dən 10 hərfə qədər olsun.
*/

return [

    'words' => [
        // Mətbəx
        ['PLOV', 'Azərbaycan süfrəsinin şahı: düyü, ət və qurudulmuş meyvə ilə'],
        ['DOVĞA', 'Qatıq və göyərti ilə bişirilən şorba'],
        ['QUTAB', 'Ət və ya göyərti ilə doldurulub sacda bişirilən nazik xəmir'],
        ['DOLMA', 'Yarpağa və ya tərəvəzə bükülən qiyməli yemək'],
        ['PİTİ', 'Şəkinin saxsı qabda bişirilən məşhur yeməyi'],
        ['LƏVƏNGİ', 'Qoz və alça turşusu ilə doldurulmuş toyuq və ya balıq'],
        ['KÜFTƏ', 'Döyülmüş ətdən hazırlanan iri kürəciklər'],
        ['BOZBAŞ', 'Noxud, kartof və ətlə bişirilən sulu yemək'],
        ['QOVURMA', 'Ətin öz yağında qovrulması ilə hazırlanan yemək'],
        ['LAVAŞ', 'Təndirdə və ya sacda bişirilən nazik çörək'],
        ['BADIMCAN', 'Dolması da bişirilən bənövşəyi tərəvəz'],
        ['GÖYƏRTİ', 'Keşniş, şüyüd, reyhanın ümumi adı'],
        ['REYHAN', 'Ətirli bənövşəyi göyərti'],
        ['ZƏFƏRAN', 'Plova sarı rəng və ətir verən ədviyyat'],
        ['PENDİR', 'Səhər yeməyinin ağ, duzlu dadı'],
        ['QAZAN', 'Plov bişirilən iri qab'],
        ['SAC', 'Üzərində qutab və lavaş bişirilən qabarıq dəmir'],
        ['TƏNDİR', 'Torpaqda qurulan çörək sobası'],

        // İçkilər və çay süfrəsi
        ['ÇAY', 'Armudu stəkanda içilən isti içki'],
        ['ARMUDU', 'Çay içilən incə belli stəkan'],
        ['AYRAN', 'Qatıqla suyun qarışığından hazırlanan sərinləşdirici içki'],
        ['QATIQ', 'Südün mayalanması ilə alınan turş süd məhsulu'],
        ['ŞƏRBƏT', 'Meyvə və ya gül suyundan hazırlanan şirin içki'],
        ['SAMOVAR', 'Çay suyunu qaynadan ənənəvi qab'],
        ['MÜRƏBBƏ', 'Çayın yanında verilən meyvə cemi'],
        ['LİMON', 'Çaya dilim-dilim qoyulan turş meyvə'],
        ['NABAT', 'Çubuğa yığılmış kristal şəkər'],

        // Şirniyyat və Novruz
        ['PAXLAVA', 'Qoz və şərbətli laylı Novruz şirniyyatı'],
        ['ŞƏKƏRBURA', 'Kənarı naxışlı, içi qozlu yarımay şəkilli şirniyyat'],
        ['QOĞAL', 'Novruzda bişirilən laylı, yumru çörək'],
        ['HALVA', 'Şəkinin rişte torundan hazırlanan şirniyyatı'],
        ['FINDIQ', 'Şəki halvasına qoyulan kiçik qoz'],
        ['BADAM', 'Şəkərburanın içinə də qoyulan uzunsov qoz'],
        ['NOVRUZ', 'Yazın gəlişi bayramı'],
        ['SƏMƏNİ', 'Novruzda cücərdilən buğda'],
        ['XONÇA', 'Bayramda şirniyyat və səməni ilə bəzədilən sini'],
        ['SİNİ', 'Xonça üçün iri dəyirmi məcməyi'],
        ['NAR', 'Göyçayın məşhur qırmızı meyvəsi'],

        // Mədəniyyət və oyunlar
        ['NƏRD', 'Zər atılaraq oynanan taxta oyunu'],
        ['ZƏR', 'Nərddə atılan altı üzlü kub'],
        ['XALÇA', 'Quba, Qarabağ və Şirvanın məşhur toxuma sənəti'],
        ['TAR', 'Azərbaycanın milli simli musiqi aləti'],
        ['KAMANÇA', 'Kamanla çalınan simli musiqi aləti'],
        ['MUĞAM', 'Azərbaycanın klassik musiqi sənəti'],
        ['BALABAN', 'Tut ağacından hazırlanan nəfəs aləti'],
        ['QƏLYAN', 'Tüstüsü su ilə süzülən ənənəvi alət'],

        // Şəhərlər və təbiət
        ['BAKI', 'Azərbaycanın paytaxtı'],
        ['ŞƏKİ', 'Xan sarayı və halvası ilə məşhur şəhər'],
        ['QUBA', 'Alması və xalçası ilə məşhur şəhər'],
        ['XƏZƏR', 'Bakının sahilində yerləşən dəniz'],
        ['QƏBƏLƏ', 'Tufandağı ilə məşhur dağ şəhəri'],

        // Restoran
        ['SÜFRƏ', 'Yeməklərin düzüldüyü masa örtüyü'],
        ['AŞPAZ', 'Yemək bişirən usta'],
        ['MƏTBƏX', 'Yemək bişirilən yer'],
        ['QONAQ', 'Evə və ya restorana dəvət olunan şəxs'],
        ['MENYU', 'Restoranda yeməklərin siyahısı'],
    ],

    // Krossvordda neçə söz olsun və şəbəkə ən çox neçə xana enində/hündürlüyündə olsun
    'target_words' => 12,
    'max_size' => 13,
];
