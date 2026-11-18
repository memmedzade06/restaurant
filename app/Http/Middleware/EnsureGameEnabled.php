<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Müvəqqəti bağlanmış oyun: config("games.{oyun}.enabled") false-dursa, oyunun bütün ünvanları 404 qaytarır
 * (səhifə də, oyun sorğuları da). Məs. route-da: ->middleware('game.enabled:claw')
 */
class EnsureGameEnabled
{
    public function handle(Request $request, Closure $next, string $game): Response
    {
        abort_unless(config("games.{$game}.enabled", true), 404);

        return $next($request);
    }
}
