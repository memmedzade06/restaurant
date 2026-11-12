<?php

namespace App\Providers\Frontend;

use App\Http\Repositories\Frontend\Contracts\MenuRepositoryInterface;
use App\Http\Repositories\Frontend\MenuRepository;
use App\Http\Services\Frontend\Contracts\MenuServiceInterface;
use App\Http\Services\Frontend\MenuService;
use Illuminate\Support\ServiceProvider;

class MenuServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(MenuRepositoryInterface::class, MenuRepository::class);
        $this->app->bind(MenuServiceInterface::class, MenuService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
