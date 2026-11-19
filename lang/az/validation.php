<?php

/*
| Yoxlama (validation) mesajları — Azərbaycan dilində.
| Burada olmayan qayda üçün Laravel-in ingiliscə mesajı göstərilir (fallback_locale).
*/

return [

    'accepted' => ':attribute qəbul edilməlidir.',
    'active_url' => ':attribute düzgün ünvan deyil.',
    'after' => ':attribute :date tarixindən sonra olmalıdır.',
    'after_or_equal' => ':attribute :date tarixi və ya ondan sonra olmalıdır.',
    'alpha' => ':attribute yalnız hərflərdən ibarət olmalıdır.',
    'alpha_dash' => ':attribute yalnız hərf, rəqəm, tire və alt xətdən ibarət olmalıdır.',
    'alpha_num' => ':attribute yalnız hərf və rəqəmlərdən ibarət olmalıdır.',
    'array' => ':attribute siyahı olmalıdır.',
    'before' => ':attribute :date tarixindən əvvəl olmalıdır.',
    'before_or_equal' => ':attribute :date tarixi və ya ondan əvvəl olmalıdır.',
    'between' => [
        'array' => ':attribute :min ilə :max element arasında olmalıdır.',
        'file' => ':attribute :min ilə :max KB arasında olmalıdır.',
        'numeric' => ':attribute :min ilə :max arasında olmalıdır.',
        'string' => ':attribute :min ilə :max simvol arasında olmalıdır.',
    ],
    'boolean' => ':attribute bəli və ya xeyr olmalıdır.',
    'confirmed' => ':attribute təkrarı ilə eyni deyil.',
    'current_password' => 'Şifrə yanlışdır.',
    'date' => ':attribute düzgün tarix deyil.',
    'date_format' => ':attribute :format formatında olmalıdır.',
    'different' => ':attribute və :other fərqli olmalıdır.',
    'digits' => ':attribute :digits rəqəmdən ibarət olmalıdır.',
    'digits_between' => ':attribute :min ilə :max rəqəm arasında olmalıdır.',
    'email' => ':attribute düzgün e-poçt ünvanı olmalıdır.',
    'exists' => 'Seçilmiş :attribute tapılmadı.',
    'file' => ':attribute fayl olmalıdır.',
    'filled' => ':attribute boş ola bilməz.',
    'gt' => [
        'numeric' => ':attribute :value-dən böyük olmalıdır.',
        'string' => ':attribute :value simvoldan uzun olmalıdır.',
    ],
    'gte' => [
        'numeric' => ':attribute ən azı :value olmalıdır.',
        'string' => ':attribute ən azı :value simvol olmalıdır.',
    ],
    'image' => ':attribute şəkil olmalıdır.',
    'in' => 'Seçilmiş :attribute yanlışdır.',
    'integer' => ':attribute tam ədəd olmalıdır.',
    'ip' => ':attribute düzgün IP ünvanı olmalıdır.',
    'json' => ':attribute düzgün JSON olmalıdır.',
    'lt' => [
        'numeric' => ':attribute :value-dən kiçik olmalıdır.',
        'string' => ':attribute :value simvoldan qısa olmalıdır.',
    ],
    'lte' => [
        'numeric' => ':attribute ən çox :value olmalıdır.',
        'string' => ':attribute ən çox :value simvol olmalıdır.',
    ],
    'max' => [
        'array' => ':attribute ən çox :max elementdən ibarət ola bilər.',
        'file' => ':attribute ən çox :max KB ola bilər.',
        'numeric' => ':attribute ən çox :max ola bilər.',
        'string' => ':attribute ən çox :max simvol ola bilər.',
    ],
    'mimes' => ':attribute bu növ fayl olmalıdır: :values.',
    'min' => [
        'array' => ':attribute ən azı :min elementdən ibarət olmalıdır.',
        'file' => ':attribute ən azı :min KB olmalıdır.',
        'numeric' => ':attribute ən azı :min olmalıdır.',
        'string' => ':attribute ən azı :min simvol olmalıdır.',
    ],
    'not_in' => 'Seçilmiş :attribute yanlışdır.',
    'numeric' => ':attribute rəqəm olmalıdır.',
    'password' => [
        'letters' => ':attribute ən azı bir hərf olmalıdır.',
        'mixed' => ':attribute ən azı bir böyük və bir kiçik hərf olmalıdır.',
        'numbers' => ':attribute ən azı bir rəqəm olmalıdır.',
        'symbols' => ':attribute ən azı bir simvol olmalıdır.',
        'uncompromised' => 'Bu :attribute sızdırılmış şifrələr siyahısındadır. Başqa şifrə seçin.',
    ],
    'regex' => ':attribute düzgün formatda deyil.',
    'required' => ':attribute yazılmalıdır.',
    'required_if' => ':other :value olduqda :attribute yazılmalıdır.',
    'required_with' => ':values olduqda :attribute yazılmalıdır.',
    'required_without' => ':values olmadıqda :attribute yazılmalıdır.',
    'same' => ':attribute və :other eyni olmalıdır.',
    'size' => [
        'array' => ':attribute :size elementdən ibarət olmalıdır.',
        'file' => ':attribute :size KB olmalıdır.',
        'numeric' => ':attribute :size olmalıdır.',
        'string' => ':attribute :size simvol olmalıdır.',
    ],
    'string' => ':attribute mətn olmalıdır.',
    'unique' => 'Bu :attribute artıq istifadə olunur.',
    'url' => ':attribute düzgün link olmalıdır.',

    'custom' => [],

    // Sahə adları — mesajlarda "name" əvəzinə "Ad, soyad" görünsün
    'attributes' => [
        'name' => 'Ad, soyad',
        'password' => 'Şifrə',
        'password_confirmation' => 'Şifrənin təkrarı',
        'phone' => 'Mobil nömrə',
        'email' => 'E-poçt',
        'login' => 'Nömrə və ya e-poçt',
        'method' => 'Təsdiq üsulu',
        'code' => 'Kod',
    ],

];
