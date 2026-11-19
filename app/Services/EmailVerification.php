<?php

namespace App\Services;

use App\Mail\EmailVerificationLink;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Qeydiyyatın e-poçt ilə təsdiqi: e-poçta "Təsdiqlə" düyməli məktub göndərilir.
 * Düymədəki link imzalıdır (dəyişdirilə bilməz) və müəyyən müddət etibarlıdır.
 */
class EmailVerification
{
    public const LINK_TTL_MINUTES = 60;
    public const RESEND_SECONDS = 60;

    /** Bu yaxınlarda məktub göndərilməyibsə, göndərir */
    public function sendIfNeeded(User $user): void
    {
        if (! Cache::has($this->key($user))) {
            $this->send($user);
        }
    }

    public function send(User $user): void
    {
        Mail::to($user->email)->send(new EmailVerificationLink($user, $this->link($user)));

        // uğurlu göndərişdən sonra — "yenidən göndər" üçün gözləmə və təkrar göndərişin qarşısı
        Cache::put($this->key($user), now()->timestamp, now()->addMinutes(self::LINK_TTL_MINUTES));
    }

    /** Yenidən göndərmək üçün neçə saniyə gözləmək lazımdır (0 — göndərmək olar) */
    public function resendWait(User $user): int
    {
        $sentAt = Cache::get($this->key($user));

        return $sentAt ? max(0, $sentAt + self::RESEND_SECONDS - now()->timestamp) : 0;
    }

    /** İmzalı təsdiq linki; hash e-poçta bağlıdır — e-poçt dəyişsə, köhnə link işləməz */
    public function link(User $user): string
    {
        return URL::temporarySignedRoute('verify.email.confirm', now()->addMinutes(self::LINK_TTL_MINUTES), [
            'user' => $user->id,
            'hash' => sha1((string) $user->email),
        ]);
    }

    /** Linkdəki hash bu istifadəçinin indiki e-poçtuna uyğundursa, hesabı təsdiqləyir */
    public function confirm(User $user, string $hash): bool
    {
        if (! $user->email || ! hash_equals(sha1($user->email), $hash)) {
            return false;
        }

        if (! $user->isVerified()) {
            $user->forceFill(['email_verified_at' => now()])->save();
            Referrals::reward($user); // dəvət linki ilə gəlibsə — dəvət edənə xal
        }
        Cache::forget($this->key($user));

        return true;
    }

    private function key(User $user): string
    {
        return 'email_verify_sent:' . $user->id;
    }
}
