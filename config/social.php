<?php

/*
|--------------------------------------------------------------------------
| Sosial şəbəkələr
|--------------------------------------------------------------------------
| Instagram səhifəmizi izləyənə bir dəfəlik xal: istifadəçi Hesabım səhifəsində Instagram adını yazır,
| admin (Admin panel → Instagram) izləyicilər siyahısında yoxlayıb təsdiqləyir — xal balansa yazılır.
*/

return [
    'instagram' => [
        'url' => env('INSTAGRAM_URL', 'https://www.instagram.com/50qepik_elmler/'),
        'handle' => env('INSTAGRAM_HANDLE', '50qepik_elmler'),
        'points' => (float) env('INSTAGRAM_FOLLOW_POINTS', 1), // təsdiqlənəndə neçə xal
    ],
];
