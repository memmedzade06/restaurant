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

    /** Daxil olmuş, amma təsdiqlənməmiş istifadəçi: Google hesabını öz profilinə bağlayıb təsdiqləyir */
    public function link(Request $request): SymfonyRedirect
    {
        abort_unless(self::enabled(), 404);

        if ($request->user()->isVerified()) {
            return redirect()->route('games.index');
        }

        return $this->driver()->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless(self::enabled(), 404);

        $current = $request->user(); // daxil olubsa — bu, "Google ilə təsdiqlə"dir
        if ($current?->isVerified()) {
            return redirect()->route('games.index');
        }
        $back = $current ? route($current->verificationRoute()) : route('login');
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
     * Google hesabını daxil olmuş istifadəçinin profilinə bağlayır və profili təsdiqləyir.
     * Bu Google hesabı / e-poçt başqa profildədirsə — bağlamırıq (iki hesab qarışmasın).
     */
    private function linkToCurrent(Request $request, User $user, string $googleId, string $email): RedirectResponse
    {
        $taken = User::whereKeyNot($user->id)
            ->where(fn ($q) => $q->where('google_id', $googleId)->orWhere('email', $email))
            ->exists();

        if ($taken) {
            return redirect()->route($user->verificationRoute())
                ->withErrors(['google' => 'Bu Google hesabı başqa profilə bağlıdır. Başqa Google hesabı seçin və ya hesabınızı əvvəlki üsulla təsdiqləyin.']);
        }

        // Nömrə ilə qeydiyyatda e-poçt yox idi; e-poçtla qeydiyyatda isə yazılan ünvan heç vaxt təsdiqlənməyib —
        // hər iki halda profilə Google-un təsdiqlədiyi ünvan yazılır
        $user->forceFill([
            'google_id' => $googleId,
            'email' => $email,
            'email_verified_at' => now(),
        ])->save();

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
