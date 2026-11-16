<?php

namespace App\Console\Commands;

use App\Services\NardLeaderboard;
use Illuminate\Console\Command;

/**
 * Nərd: bitmiş günə yekun vurur — qaliblərə endirim kodu, günün nəticələrinin silinməsi.
 * Səhifə açılanda da avtomatik işləyir; cron varsa (schedule:run) hər gecə 00:01-də də işləyir.
 */
class NardFinalizeDay extends Command
{
    protected $signature = 'nard:finalize-day';

    protected $description = 'Nərd: bitmiş günün qaliblərinə endirim kodu verir və günün nəticələrini silir';

    public function handle(): int
    {
        NardLeaderboard::finalizePastDays();

        $winners = NardLeaderboard::yesterdayWinners();
        if ($winners->isEmpty()) {
            $this->info('Dünən xal toplayan olmayıb.');
        }
        foreach ($winners as $w) {
            $this->line("{$w->place}. {$w->user?->name} — {$w->points} xal — " . ($w->prize?->code ?? 'kodsuz'));
        }

        return self::SUCCESS;
    }
}
