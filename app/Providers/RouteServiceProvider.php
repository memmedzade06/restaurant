<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/hesabim';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Diqqət: "throttle:40,1" kimi limitlər istifadənin BÜTÜN ünvanlarına birlikdə sayılır.
        // Səhifələrin tez-tez soruşduğu ünvanlar (onlayn siqnal, nərd dəvətləri və oyunun vəziyyəti)
        // bir-birinin limitini yeməsin deyə hər ünvanın öz sayğacı var.
        $perRoute = fn (int $perMinute) => fn (Request $request) => Limit::perMinute($perMinute)
            ->by(($request->route()?->getName() ?? $request->path()) . '|' . ($request->user()?->id ?: $request->ip()));
        RateLimiter::for('poll', $perRoute(90));        // avtomatik soruşmalar (hər bir neçə saniyədən bir)
        RateLimiter::for('game-action', $perRoute(60)); // oyunçunun hərəkətləri: zər, gediş, dəvət

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
