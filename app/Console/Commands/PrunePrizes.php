<?php

namespace App\Console\Commands;

use App\Models\WheelSpin;
use Illuminate\Console\Command;

/**
 * Müddəti bitmiş bütün hədiyyə kuponlarını (istifadə olunmuşlar da) bazadan silir.
 * Cədvəldə hər dəqiqə işləyir; cron olmasa da sayt özü (PruneExpiredPrizes) dəqiqədə bir dəfə silir.
 */
class PrunePrizes extends Command
{
    protected $signature = 'prizes:prune';

    protected $description = 'Müddəti bitmiş bütün hədiyyə kuponlarını silir (istifadə olunmuşlar da)';

    public function handle(): int
    {
        $this->info('Silinən kupon: ' . WheelSpin::pruneExpired());

        return self::SUCCESS;
    }
}
