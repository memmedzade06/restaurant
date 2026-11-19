<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Referrals;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Qeydiyyat: ad, şifrə və təsdiq üsulu.
 *  - Telegram: mobil nömrə yazılır, bot ilə təsdiqlənir;
 *  - E-poçt: e-poçt ünvanı yazılır, ora göndərilən 6 rəqəmli kod ilə təsdiqlənir.
 */
class RegisterController extends Controller
{
    public function show(Request $request): View
    {
        // dəvət linki ilə gəlibsə — kim dəvət edib (səhifədə göstərilir)
        $code = strtoupper((string) $request->cookie(Referrals::COOKIE));
        $referrer = $code !== '' ? User::where('referral_code', $code)->verified()->first() : null;

        return view('frontend.auth.register', ['referrer' => $referrer]);
    }

    public function store(Request $request): RedirectResponse
    {
        $method = $request->input('method') === 'email' ? 'email' : 'telegram';

        if ($method === 'telegram') {
            // Nömrəni əvvəlcə vahid formata salırıq ki, unikallıq düzgün yoxlansın
            $request->merge(['phone' => Phone::normalize($request->input('phone')) ?? $request->input('phone')]);
        } else {
            $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'password' => ['required', Password::min(8)],
            'method' => ['required', Rule::in(['telegram', 'email'])],
            'phone' => $method === 'telegram' ? ['required', 'regex:/^\+994\d{9}$/', 'unique:users,phone'] : ['exclude'],
            'email' => $method === 'email' ? ['required', 'email:rfc', 'max:255', 'unique:users,email'] : ['exclude'],
        ], [
            'method.required' => 'Təsdiq üsulunu seçin.',
            'phone.required' => 'Mobil nömrəni yazın.',
            'phone.regex' => 'Nömrəni düzgün yazın, məsələn: 050 123 45 67.',
            'phone.unique' => 'Bu nömrə ilə artıq qeydiyyat var.',
            'email.required' => 'E-poçt ünvanını yazın.',
            'email.email' => 'E-poçt ünvanını düzgün yazın, məsələn: ad@gmail.com.',
            'email.unique' => 'Bu e-poçt ilə artıq qeydiyyat var.',
        ]);
        unset($data['method']);

        $user = User::create($data);
        Referrals::attach($user, $request); // dəvət linki ilə gəlibsə — dəvət edəni yadda saxla

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route($user->verificationRoute());
    }
}
