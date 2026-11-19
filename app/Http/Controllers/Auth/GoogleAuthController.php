<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Referrals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

/**
 * "Google ilə davam et": Google e-poçtu özü təsdiqləyir — hesab dərhal təsdiqlənmiş olur.
 *  - bu Google hesabı və ya bu e-poçtla hesab varsa — ona daxil olur;
 *  - yoxdursa — Google-dakı ad və e-poçtla yeni hesab yaradılır.
 * Şifrə təsadüfi qoyulur; istəsə "Şifrəmi unutdum" (e-poçt) ilə öz şifrəsini təyin edə bilər.
 */
class GoogleAuthController extends Controller
{
    /** Açarlar .env-də yazılıbmı (yoxsa düymə göstərilmir) */
    public static function enabled(): bool
    {
        return (bool) config('services.google.client_id') && (bool) config('services.google.client_secret');
    }

    public function redirect(): SymfonyRedirect
    {
        abort_unless(self::enabled(), 404);

        return $this->driver()->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless(self::enabled(), 404);

        // İstifadəçi Google pəncərəsində "Ləğv et" basıb
        if ($request->has('error')) {
            return redirect()->route('login');
        }

        try {
            $google = $this->driver()->user();
        } catch (\Throwable $e) {
            Log::warning('Google ilə giriş alınmadı: ' . $e->getMessage());

            return redirect()->route('login')->withErrors(['login' => 'Google ilə giriş alınmadı. Yenidən cəhd edin.']);
        }

        $email = strtolower((string) $google->getEmail());
        if ($email === '' || ! ($google->user['email_verified'] ?? true)) {
            return redirect()->route('login')->withErrors(['login' => 'Google hesabınızın e-poçtu təsdiqlənməyib.']);
        }

        $user = User::where('google_id', $google->getId())->first()
            ?? User::where('email', $email)->first();
        $isNew = ! $user;

        if ($isNew) {
            $user = User::create([
                'name' => Str::limit(trim((string) $google->getName()) ?: Str::before($email, '@'), 100, ''),
                'email' => $email,
                'password' => Str::random(40),
            ]);
            Referrals::attach($user, $request); // dəvət linki ilə gəlibsə — dəvət edəni yadda saxla
        }

        $wasVerified = $user->isVerified();
        if (! $isNew && ! $wasVerified) {
            // Təsdiqlənməmiş hesabı bu e-poçtla başqası açmış ola bilər — onun şifrəsi ilə girişi bağlayırıq
            $user->forceFill(['password' => Str::random(40), 'remember_token' => Str::random(60)]);
        }
        $user->forceFill([
            'google_id' => $google->getId(),
            // Google e-poçtun sahibini təsdiqləyib
            'email_verified_at' => $user->email === $email ? ($user->email_verified_at ?? now()) : $user->email_verified_at,
        ])->save();

        if (! $wasVerified && $user->isVerified()) {
            Referrals::reward($user); // dəvət edənə xal
            $request->session()->flash('registered', true);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        if (! $user->isVerified()) {
            return redirect()->route($user->verificationRoute());
        }

        return redirect()->intended(route('games.index'));
    }

    /** Qayıdış ünvanı cari domenə görə (lokal və real sayt üçün eyni kod) */
    private function driver()
    {
        return Socialite::driver('google')
            ->redirectUrl(route('google.callback'))
            ->scopes(['openid', 'profile', 'email']);
    }
}
