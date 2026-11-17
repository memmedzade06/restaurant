<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TelegramPasswordReset;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Şifrəmi unutdum: nömrə → Telegram ilə təsdiq → yeni şifrə.
 * Bərpa kodu bu brauzerin sessiyasında saxlanılır — başqa cihazdan yeni şifrə təyin etmək olmur.
 */
class ForgotPasswordController extends Controller
{
    private const SESSION_KEY = 'pw_reset_token';

    public function __construct(private readonly TelegramPasswordReset $reset)
    {
    }

    /** 1-ci addım: nömrə */
    public function show(): View
    {
        return view('frontend.auth.forgot');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['phone' => ['required', 'string']], ['phone.required' => 'Telefon nömrənizi yazın.']);

        $phone = Phone::normalize($request->input('phone'));
        $user = $phone ? User::where('phone', $phone)->whereNotNull('phone_verified_at')->first() : null;

        if (! $user) {
            throw ValidationException::withMessages([
                'phone' => $phone ? 'Bu nömrə ilə təsdiqlənmiş hesab tapılmadı.' : 'Nömrəni düzgün yazın, məsələn: 050 123 45 67.',
            ]);
        }

        $request->session()->put(self::SESSION_KEY, $this->reset->start($user));

        return redirect()->route('password.telegram');
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

        return view('frontend.auth.reset', ['phone' => $user->phone]);
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
            ->withInput(['login' => Phone::format($user->phone)])
            ->with('status', 'Şifrəniz dəyişdirildi. İndi yeni şifrə ilə daxil olun.');
    }

    private function expired(): RedirectResponse
    {
        session()->forget(self::SESSION_KEY);

        return redirect()->route('password.request')
            ->withErrors(['phone' => 'Bərpa linkinin vaxtı keçib. Nömrənizi yenidən yazın.']);
    }
}
