<?php

namespace App\Services;

use App\Mail\PasswordResetLink;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * "Şifrəmi unutdum" — e-poçt ilə (e-poçtla qeydiyyatdan keçənlər üçün):
 * e-poçta imzalı "Yeni şifrə təyin et" linki göndərilir.
 * Linkdəki hash cari şifrəyə bağlıdır — şifrə dəyişəndən sonra köhnə link işləmir (birdəfəlik).
 */
class EmailPasswordReset
{
    public const TTL_MINUTES = 30;
    public const RESEND_SECONDS = 60;

    /** E-poçtla təsdiqlənmiş hesab — bu yolla bərpa edilə bilər */
    public function findUser(string $email): ?User
    {
        return User::where('email', strtolower(trim($email)))->whereNotNull('email_verified_at')->first();
    }

    /** Məktub göndərir; qısa müddətdə təkrar sorğuları sakitcə ötürür */
    public function send(User $user): void
    {
        $key = 'pw_reset_mail:' . $user->id;
        if (Cache::has($key)) {
            return;
        }

        $url = URL::temporarySignedRoute('password.email.confirm', now()->addMinutes(self::TTL_MINUTES), [
            'user' => $user->id,
            'hash' => $this->hash($user),
        ]);

        Mail::to($user->email)->send(new PasswordResetLink($user, $url));
        Cache::put($key, true, now()->addSeconds(self::RESEND_SECONDS));
    }

    public function valid(User $user, string $hash): bool
    {
        return $user->email && hash_equals($this->hash($user), $hash);
    }

    private function hash(User $user): string
    {
        return sha1($user->email . '|' . $user->password);
    }
}
