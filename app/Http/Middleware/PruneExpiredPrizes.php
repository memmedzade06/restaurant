<?php

namespace App\Http\Middleware;

use App\Models\WheelSpin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vaxtı keçmiş kuponları serverdə cron olmasa da silir: cavab brauzerə göndərildikdən sonra,
 * bütün sayt üzrə ən çox dəqiqədə bir dəfə (ziyarətçi gözləmir).
 */
class PruneExpiredPrizes
{
    private const EVERY_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        // Cache::add yalnız açar yoxdursa yazır — dəqiqə ərzində ilk sorğu silir, qalanları keçir
        if (! Cache::add('prizes:pruned', true, self::EVERY_SECONDS)) {
            return;
        }

        try {
            WheelSpin::pruneExpired();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
