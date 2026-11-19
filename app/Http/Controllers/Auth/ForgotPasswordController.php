<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmailPasswordReset;
use App\Services\TelegramPasswordReset;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Şifrəmi unutdum: nömrə → Telegram ilə təsdiq → yeni şifrə;
 * e-poçt → məktubdakı imzalı link → yeni şifrə (e-poçtla qeydiyyatdan keçənlər üçün).
 * Bərpa kodu bu brauzerin sessiyasında saxlanılır — başqa cihazdan yeni şifrə təyin etmək olmur.
 */
class ForgotPasswordController extends Controller
{
    private const SESSION_KEY = 'pw_reset_token';

    public function __construct(
        private readonly TelegramPasswordReset $reset,
        private readonly EmailPasswordReset $emailReset,
    )
    {
    }

    /** 1-ci addım: nömrə */
    public function show(): View
    {
        return view('frontend.auth.forgot');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['login' => ['required', 'string']], ['login.required' => 'Nömrənizi və ya e-poçtunuzu yazın.']);
        $login = trim($request->input('login'));

        // E-poçt: e-poçtla qeydiyyatdan keçənlərə bərpa linki göndərilir
        if (str_contains($login, '@')) {
            $user = $this->emailReset->findUser($login);
            if (! $user) {
                throw ValidationException::withMessages(['login' => 'Bu e-poçt ilə təsdiqlənmiş hesab tapılmadı.']);
            }

            try {
                $this->emailReset->send($user);
            } catch (\Throwable $e) {
                Log::error('Şifrə bərpası məktubu göndərilmədi: ' . $e->getMessage());
                throw ValidationException::withMessages(['login' => 'Məktub göndərilmədi. Bir az sonra yenidən cəhd edin.']);
            }

            return redirect()->route('password.email.sent')->with('reset_email', $user->email);
        }

        // Nömrə: Telegram ilə bərpa
        $phone = Phone::normalize($login);
        $user = $phone ? User::where('phone', $phone)->whereNotNull('phone_verified_at')->first() : null;

        if (! $user) {
            throw ValidationException::withMessages([
                'login' => $phone ? 'Bu nömrə ilə təsdiqlənmiş hesab tapılmadı.' : 'Nömrəni (məs. 050 123 45 67) və ya e-poçtu düzgün yazın.',
            ]);
        }

        $request->session()->put(self::SESSION_KEY, $this->reset->start($user));

        return redirect()->route('password.telegram');
    }

    /** E-poçt: "məktub göndərildi" səhifəsi */
    public function emailSent(Request $request): View|RedirectResponse
    {
        $email = $request->session()->get('reset_email');

        return $email ? view('frontend.auth.forgot-email', ['email' => $email]) : redirect()->route('password.request');
    }

    /** E-poçtdakı imzalı link: təsdiqlənmiş bərpa kodu yaradılır və yeni şifrə formu açılır */
    public function emailConfirm(Request $request, User $user, string $hash): RedirectResponse
    {
        if (! $this->emailReset->valid($user, $hash)) {
            return redirect()->route('password.request')
                ->withErrors(['login' => 'Bu link artıq istifadə olunub və ya etibarsızdır. Yenidən cəhd edin.']);
        }

        $request->session()->put(self::SESSION_KEY, $this->reset->startConfirmed($user));

        return redirect()->route('password.reset');
    }

    /** 2-ci addım: Telegram ilə təsdiq */
    public function telegram(Request $request): View|RedirectResponse
    {
        $token = $request->session()->get(self::SESSION_KEY);
        $data = $this->reset->get($token);
        if (! $data) {
            return $this->expired();
        }
        if ($data['confirmed']) {
            return redirect()->route('password.reset');
        }

        return view('frontend.auth.forgot-telegram', [
            'phone' => User::find($data['user_id'])?->phone,
            'link' => $this->reset->link($token),
            'botReady' => (bool) config('services.telegram.bot_token') && (bool) config('services.telegram.bot_username'),
        ]);
    }

    /** Səhifə bunu yoxlayır — bot təsdiqləyəndə yeni şifrə formuna keçir */
    public function status(Request $request): JsonResponse
    {
        $data = $this->reset->get($request->session()->get(self::SESSION_KEY));

        if (! $data) {
            return response()->json(['confirmed' => false, 'expired' => true, 'redirect' => route('password.request')]);
        }

        return response()->json(['confirmed' => (bool) $data['confirmed'], 'redirect' => route('password.reset')]);
    }

    /** 3-cü addım: yeni şifrə */
    public function edit(Request $request): View|RedirectResponse
    {
        $user = $this->reset->confirmedUser($request->session()->get(self::SESSION_KEY));
        if (! $user) {
            return $this->expired();
        }

        return view('frontend.auth.reset', [
            'label' => $user->phone ? Phone::format($user->phone) : $user->email,
            'viaEmail' => ! $user->phone,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $token = $request->session()->get(self::SESSION_KEY);
        $user = $this->reset->confirmedUser($token);
        if (! $user) {
            return $this->expired();
        }

        $request->validate(
            ['password' => ['required', 'confirmed', Password::min(8)]],
            [
                'password.required' => 'Yeni şifrəni yazın.',
                'password.confirmed' => 'Şifrələr eyni deyil.',
                'password.min' => 'Şifrə ən azı 8 simvol olmalıdır.',
            ]
        );

        // "Məni xatırla" ilə açıq qalmış köhnə girişlər də etibarsız olur
        $user->forceFill(['password' => $request->input('password'), 'remember_token' => Str::random(60)])->save();

        $this->reset->finish($token, $user);
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('login')
            ->withInput(['login' => $user->phone ? Phone::format($user->phone) : $user->email])
            ->with('status', 'Şifrəniz dəyişdirildi. İndi yeni şifrə ilə daxil olun.');
    }

    private function expired(): RedirectResponse
    {
        session()->forget(self::SESSION_KEY);

        return redirect()->route('password.request')
            ->withErrors(['login' => 'Bərpa linkinin vaxtı keçib. Yenidən cəhd edin.']);
    }
}
