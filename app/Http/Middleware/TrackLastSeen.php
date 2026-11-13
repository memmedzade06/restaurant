<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Daxil olmuş istifadəçinin "son görünmə" vaxtını yeniləyir.
 * Bazanı hər sorğuda yükləməmək üçün ən çox 20 saniyədə bir dəfə yazır.
 */
class TrackLastSeen
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (! $user->last_seen_at || $user->last_seen_at->lt(now()->subSeconds(20)))) {
            // updated_at-a toxunmadan birbaşa yazırıq
            $user->newQuery()->whereKey($user->id)->update(['last_seen_at' => now()]);
            $user->last_seen_at = now();
            $user->syncOriginalAttribute('last_seen_at');
        }

        return $next($request);
    }
}
