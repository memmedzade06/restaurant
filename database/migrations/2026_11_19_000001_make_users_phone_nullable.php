<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * E-poçt ilə qeydiyyatda nömrə soruşulmur — telefon boş ola bilər.
     * Unikal indeks qalır: MySQL-də bir neçə NULL icazəlidir.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE users MODIFY phone VARCHAR(20) NULL');
    }

    public function down(): void
    {
        // geri qaytarmaq mümkün deyil, əgər nömrəsiz hesablar varsa — onda heç nə etmirik
        if (! DB::table('users')->whereNull('phone')->exists()) {
            DB::statement('ALTER TABLE users MODIFY phone VARCHAR(20) NOT NULL');
        }
    }
};
