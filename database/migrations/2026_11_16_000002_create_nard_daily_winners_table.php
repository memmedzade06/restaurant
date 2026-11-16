<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nərd: hər günün qalibləri (ilk 3 yer) və onlara verilən endirim kodu.
     * Günün nəticələri (game_results) gecə 00:00-da yekun vurulandan sonra silinir, qaliblər isə burada qalır.
     */
    public function up(): void
    {
        Schema::create('nard_daily_winners', function (Blueprint $table) {
            $table->id();
            $table->date('day');                           // hansı günün qalibi (Bakı vaxtı)
            $table->unsignedTinyInteger('place');          // 1, 2, 3
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('points');
            $table->unsignedInteger('wins');
            $table->foreignId('wheel_spin_id')->nullable()->constrained('wheel_spins')->nullOnDelete(); // verilən endirim kodu
            $table->timestamps();

            $table->unique(['day', 'place']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nard_daily_winners');
    }
};
