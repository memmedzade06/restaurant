<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Referrals;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Qeydiyyat: ad, nömrə, şifrə (bir dəfə yazılır — formada "şifrəni göstər" düyməsi var).
 * E-poçt soruşulmur. Qeydiyyat yalnız Telegram ilə təsdiqlənir.
 */
class RegisterController extends Controller
{
    public function show(Request $request): View
    {
        // dəvət linki ilə gəlibsə — kim dəvət edib (səhifədə göstərilir)
        $code = strtoupper((string) $request->cookie(Referrals::COOKIE));
        $referrer = $code !== '' ? User::where('referral_code', $code)->whereNotNull('phone_verified_at')->first() : null;

        return view('frontend.auth.register', ['referrer' => $referrer]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Nömrəni əvvəlcə vahid formata salırıq ki, unikallıq düzgün yoxlansın
        $request->merge(['phone' => Phone::normalize($request->input('phone')) ?? $request->input('phone')]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'regex:/^\+994\d{9}$/', 'unique:users,phone'],
            'password' => ['required', Password::min(8)],
        ], [
            'phone.required' => 'Mobil nömrəni yazın.',
            'phone.regex' => 'Nömrəni düzgün yazın, məsələn: 050 123 45 67.',
            'phone.unique' => 'Bu nömrə ilə artıq qeydiyyat var.',
        ]);

        $user = User::create($data);
        Referrals::attach($user, $request); // dəvət linki ilə gəlibsə — dəvət edəni yadda saxla

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('verify.telegram');
    }
}
