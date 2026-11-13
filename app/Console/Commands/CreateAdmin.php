<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * İstifadəçi adı və şifrə ilə admin hesabı yaradır (və ya mövcudun şifrəsini yeniləyir).
 *   php artisan admin:create admin
 *   php artisan admin:create admin --password=yeniŞifrə
 */
class CreateAdmin extends Command
{
    protected $signature = 'admin:create {username : Giriş üçün istifadəçi adı} {--password= : Şifrə (verilməsə soruşulur)}';

    protected $description = 'İstifadəçi adı + şifrə ilə admin hesabı yaradır';

    public function handle(): int
    {
        $username = strtolower(trim($this->argument('username')));
        $password = $this->option('password') ?: $this->secret('Şifrə');

        if (strlen((string) $password) < 6) {
            $this->error('Şifrə ən azı 6 simvol olmalıdır.');

            return self::FAILURE;
        }

        $email = $username . '@admin.local';
        $user = User::where('username', $username)->orWhere('email', $email)->first() ?? new User();
        $created = ! $user->exists;

        $user->forceFill([
            'username' => $username,
            'name' => $user->name ?: 'Admin',
            'email' => $user->email ?: $email,
            'password' => $password,
            'is_admin' => true,
            'phone_verified_at' => $user->phone_verified_at ?: now(),
        ])->save();

        $this->info($created ? "Admin hesabı yaradıldı: {$username}" : "Admin hesabının şifrəsi yeniləndi: {$username}");

        return self::SUCCESS;
    }
}
