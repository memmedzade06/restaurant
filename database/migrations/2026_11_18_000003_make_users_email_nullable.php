<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Qeydiyyatda e-poçt artıq soruşulmur (giriş telefonla, təsdiq Telegram ilə olur).
     * Köhnə istifadəçilərin e-poçtları qalır; unikal indeks də qalır — MySQL-də bir neçə NULL icazəlidir.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NULL');
    }

    public function down(): void
    {
        // geri qaytarmaq üçün boş e-poçtlar müvəqqəti dəyərlə doldurulur
        DB::statement("UPDATE users SET email = CONCAT('user', id, '@no-email.local') WHERE email IS NULL");
        DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NOT NULL');
    }
};
