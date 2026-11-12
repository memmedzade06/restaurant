<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Oyunlar: nərd nəticələri (rekordlar cədvəli) və bəxt çarxı fırlatmaları (hədiyyələr).
     * Hər ikisi yalnız təsdiqlənmiş istifadəçilər üçün saxlanılır.
     */
    public function up(): void
    {
        Schema::create('game_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('game', 30);                 // 'nard'
            $table->boolean('won');
            $table->boolean('mars')->default(false);     // rəqib heç daş çıxarmayıbsa — ikiqat qələbə
            $table->unsignedSmallInteger('points')->default(0);
            $table->timestamps();

            $table->index(['game', 'user_id']);
        });

        Schema::create('wheel_spins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('prize_key', 40);
            $table->string('prize_label', 100);
            $table->string('code', 20)->nullable()->unique(); // hədiyyə kodu (boş — hədiyyə yoxdur)
            $table->date('spun_on');                          // Bakı vaxtı ilə gün
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('redeemed_at')->nullable();     // restoranda istifadə olunanda
            $table->timestamps();

            $table->unique(['user_id', 'spun_on']);           // gündə bir dəfə
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wheel_spins');
        Schema::dropIfExists('game_results');
    }
};
