<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Dəvət xalları: 0.5 / 0.25 / 0.125 — 3-cü səviyyənin xalı üçün 3 onluq rəqəm lazımdır.
     * Mövcud balanslar olduğu kimi qalır (yalnız sütunun dəqiqliyi artır).
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE users MODIFY points DECIMAL(12,3) UNSIGNED NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users MODIFY points DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0');
    }
};
