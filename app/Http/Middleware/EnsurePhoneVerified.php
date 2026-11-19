<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hesabı (Telegram və ya e-poçt ilə) təsdiqlənməmiş istifadəçini təsdiq səhifəsinə göndərir.
 */
class EnsurePhoneVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->isVerified()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Hesab təsdiqlənməyib.'], 403)
                : redirect()->route($request->user()->verificationRoute());
        }

        return $next($request);
    }
}
