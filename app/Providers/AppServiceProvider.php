<?php

namespace App\Providers;

use App\Repository\IProductRepository;
use App\Repository\ProductRepository;
use App\Services\TelegramBot;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(IProductRepository::class,ProductRepository::class);

        $this->app->singleton(TelegramBot::class, fn () => new TelegramBot(config('services.telegram.bot_token')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
