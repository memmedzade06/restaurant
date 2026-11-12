<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gündəlik limit artıq bazada yox, kodda yoxlanılır (config/games.php → wheel.once_per_day),
     * ona görə "gündə bir fırlatma" unikal indeksi adi indekslə əvəz olunur.
     */
    public function up(): void
    {
        Schema::table('wheel_spins', function (Blueprint $table) {
            $table->index(['user_id', 'spun_on']);          // xarici açar üçün indeks qalsın
            $table->dropUnique(['user_id', 'spun_on']);
        });
    }

    public function down(): void
    {
        Schema::table('wheel_spins', function (Blueprint $table) {
            $table->unique(['user_id', 'spun_on']);
            $table->dropIndex(['user_id', 'spun_on']);
        });
    }
};
