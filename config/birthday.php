<?php

/*
|--------------------------------------------------------------------------
| Ad günü hədiyyəsi
|--------------------------------------------------------------------------
| İstifadəçi profildə doğum tarixini yazır; ad günü gələndə masaya endirim kuponu götürə bilir (ildə bir dəfə).
*/

return [

    'enabled' => (bool) env('BIRTHDAY_COUPON_ENABLED', true),

    // Kuponun adı (kartda, "Hədiyyələrim"də və Telegram bildirişində görünür)
    'label' => 'Masaya 50% endirim — ad günü hədiyyəsi',

    // Kuponu ad günündən neçə gün əvvəl və sonra götürmək olar (0 və 0 — yalnız ad günü)
    'days_before' => (int) env('BIRTHDAY_DAYS_BEFORE', 0),
    'days_after' => (int) env('BIRTHDAY_DAYS_AFTER', 0),

    // Götürüləndən sonra kupon neçə gün etibarlıdır
    'valid_days' => (int) env('BIRTHDAY_COUPON_VALID_DAYS', 7),

    // Doğum tarixini profildə yazmağa görə bonus xal (bir dəfə, yalnız qonaq hesabları). 0 — bonus yoxdur
    'set_points' => (float) env('BIRTHDAY_SET_POINTS', 1),

    // Sui-istifadəyə qarşı: doğum tarixi ən azı bu qədər gün əvvəl yazılıbsa kupon verilir
    // (yoxsa qeydiyyatdan keçib "ad günüm bu gündür" yazmaq olardı)
    'min_days_since_set' => (int) env('BIRTHDAY_MIN_DAYS_SINCE_SET', 30),

];
