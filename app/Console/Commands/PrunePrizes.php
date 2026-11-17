<?php

namespace App\Console\Commands;

use App\Models\WheelSpin;
use Illuminate\Console\Command;

/**
 * Vaxtı keçmiş, istifadə olunmamış hədiyyə kuponlarını bazadan silir.
 * Cədvəldə hər dəqiqə işləyir; cron olmasa da sayt özü (PruneExpiredPrizes) dəqiqədə bir dəfə silir.
 */
class PrunePrizes extends Command
{
    protected $signature = 'prizes:prune';

    protected $description = 'Vaxtı keçmiş, istifadə olunmamış hədiyyə kuponlarını silir';

    public function handle(): int
    {
        $this->info('Silinən kupon: ' . WheelSpin::pruneExpired());

        return self::SUCCESS;
    }
}
