<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Nərd: gün bitəndə qaliblərə endirim kodu və nəticələrin silinməsi (səhifə açılanda da işləyir)
        $schedule->command('nard:finalize-day')->dailyAt('00:01')->timezone('Asia/Baku');
        // Müddəti bitmiş bütün hədiyyə kuponlarının silinməsi (sayt açılanda da işləyir)
        $schedule->command('prizes:prune')->everyMinute()->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
