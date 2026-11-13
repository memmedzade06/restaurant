<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Phone;
use Illuminate\Console\Command;

/**
 * İstifadəçini telefon nömrəsinə görə admin edir (və ya adminlikdən çıxarır).
 *   php artisan admin:make 0501234567
 *   php artisan admin:make 0501234567 --remove
 */
class MakeAdmin extends Command
{
    protected $signature = 'admin:make {phone : İstifadəçinin telefon nömrəsi} {--remove : Adminlikdən çıxar}';

    protected $description = 'Qeydiyyatdan keçmiş istifadəçini admin edir';

    public function handle(): int
    {
        $phone = Phone::normalize($this->argument('phone'));
        $user = $phone ? User::where('phone', $phone)->first() : null;

        if (! $user) {
            $this->error('Bu nömrə ilə istifadəçi tapılmadı. Əvvəlcə saytda qeydiyyatdan keçin.');

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => ! $this->option('remove')])->save();

        $this->info($user->name . ($user->is_admin ? ' indi admindir.' : ' artıq admin deyil.'));

        return self::SUCCESS;
    }
}
