<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Telefonu Telegram ilə təsdiqlənməmiş istifadəçini təsdiq səhifəsinə göndərir.
 */
class EnsurePhoneVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->hasVerifiedPhone()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Telefon təsdiqlənməyib.'], 403)
                : redirect()->route('verify.telegram');
        }

        return $next($request);
    }
}
