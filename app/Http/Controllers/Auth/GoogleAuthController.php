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

    /**
     * Daxil olmuş istifadəçi Google hesabını öz profilinə bağlayır:
     *  - təsdiqlənməmiş profil — həm də təsdiqlənir ("Google ilə təsdiqlə");
     *  - təsdiqlənmiş profil (məs. Telegram ilə) — sonra "Google ilə davam et" ilə eyni hesaba daxil ola bilsin.
     */
    public function link(Request $request): SymfonyRedirect
    {
        abort_unless(self::enabled(), 404);

        if ($request->user()->google_id) {
            return redirect()->route('account');
        }

        $request->session()->put('google_link', true);

        return $this->driver()->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless(self::enabled(), 404);

        // Daxil olubsa — bu, Google hesabını profilə bağlamaqdır ("Google ilə təsdiqlə" / "Google hesabını bağla")
        $current = $request->user();
        $linking = (bool) $request->session()->pull('google_link');
        if ($current && ($current->google_id || ($current->isVerified() && ! $linking))) {
            return redirect()->route($current->isVerified() ? 'account' : 'games.index');
        }
        $back = ! $current ? route('login') : ($current->isVerified() ? route('account') : route($current->verificationRoute()));
        $errorKey = $current ? 'google' : 'login';

        // İstifadəçi Google pəncərəsində "Ləğv et" basıb
        if ($request->has('error')) {
            return redirect($back);
        }

        try {
            $google = $this->driver()->user();
        } catch (\Throwable $e) {
            Log::warning('Google ilə giriş alınmadı: ' . $e->getMessage());

            return redirect($back)->withErrors([$errorKey => 'Google ilə əlaqə alınmadı. Yenidən cəhd edin.']);
        }

        $email = strtolower((string) $google->getEmail());
        if ($email === '' || ! ($google->user['email_verified'] ?? true)) {
            return redirect($back)->withErrors([$errorKey => 'Google hesabınızın e-poçtu təsdiqlənməyib.']);
        }

        if ($current) {
            return $this->linkToCurrent($request, $current, $google->getId(), $email);
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

    /**
     * Google hesabını daxil olmuş istifadəçinin profilinə bağlayır (təsdiqlənməmişsə — həm də təsdiqləyir).
     * Bu Google hesabı / e-poçt başqa profildədirsə — bağlamırıq (iki hesab qarışmasın).
     */
    private function linkToCurrent(Request $request, User $user, string $googleId, string $email): RedirectResponse
    {
        $wasVerified = $user->isVerified();
        $back = $wasVerified ? route('account') : route($user->verificationRoute());

        $taken = User::whereKeyNot($user->id)
            ->where(fn ($q) => $q->where('google_id', $googleId)->orWhere('email', $email))
            ->exists();

        if ($taken) {
            return redirect($back)->withErrors(['google' => $wasVerified
                ? 'Bu Google hesabı başqa profilə bağlıdır. Başqa Google hesabı seçin.'
                : 'Bu Google hesabı başqa profilə bağlıdır. Başqa Google hesabı seçin və ya hesabınızı əvvəlki üsulla təsdiqləyin.']);
        }

        $data = ['google_id' => $googleId];
        // Profildə təsdiqlənmiş e-poçt yoxdursa (nömrə ilə qeydiyyat və ya təsdiqlənməmiş ünvan) —
        // Google-un təsdiqlədiyi ünvan yazılır. Təsdiqlənmiş öz e-poçtu varsa, o qalır.
        if (! $user->email || ! $user->email_verified_at) {
            $data['email'] = $email;
            $data['email_verified_at'] = now();
        }
        $user->forceFill($data)->save();

        if ($wasVerified) {
            return redirect()->route('account')
                ->with('status', 'Google hesabınız bağlandı. İndi "Google ilə davam et" ilə də daxil ola bilərsiniz.');
        }

        Referrals::reward($user); // dəvət linki ilə gəlibsə — dəvət edənə xal
        $request->session()->flash('registered', true);

        return redirect()->route('games.index');
    }

    /** Qayıdış ünvanı cari domenə görə (lokal və real sayt üçün eyni kod) */
    private function driver()
    {
        return Socialite::driver('google')
            ->redirectUrl(route('google.callback'))
            ->scopes(['openid', 'profile', 'email']);
    }
}
