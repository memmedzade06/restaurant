<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('frontend.auth.login');
    }

    /** Telefon və ya e-poçt + şifrə ilə giriş */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $login = trim($request->input('login'));
        $credentials = str_contains($login, '@')
            ? ['email' => $login]
            : ['phone' => Phone::normalize($login) ?? $login];

        if (! Auth::attempt($credentials + ['password' => $request->input('password')], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['login' => 'Telefon/e-poçt və ya şifrə yanlışdır.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('account'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
