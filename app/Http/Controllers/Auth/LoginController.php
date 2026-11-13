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

    /** Telefon, e-poçt və ya istifadəçi adı (admin) + şifrə ilə giriş */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $login = trim($request->input('login'));
        $phone = Phone::normalize($login);
        $credentials = match (true) {
            str_contains($login, '@') => ['email' => $login],
            $phone !== null => ['phone' => $phone],
            default => ['username' => strtolower($login)],
        };

        if (! Auth::attempt($credentials + ['password' => $request->input('password')], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['login' => 'Telefon/e-poçt və ya şifrə yanlışdır.']);
        }

        $request->session()->regenerate();

        return redirect()->intended($request->user()->is_admin ? route('admin.prizes') : route('account'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        // Çıxış edən istifadəçi "İndi saytda" siyahısından dərhal düşsün
        $request->user()?->forceFill(['last_seen_at' => null])->saveQuietly();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
