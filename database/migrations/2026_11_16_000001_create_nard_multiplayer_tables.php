<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * İki nəfərlik nərd: saytdakı oyunçular bir-birinə dəvət göndərir, qəbul olunanda oyun yaranır.
     * Oyunun vəziyyəti serverdə saxlanılır: zərləri server atır, hər gedişi server yoxlayır.
     */
    public function up(): void
    {
        Schema::create('nard_matches', function (Blueprint $table) {
            $table->id();
            // oyunçu 0 (ağ daşlar) və oyunçu 1 (qara daşlar)
            $table->foreignId('player0_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('player1_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('playing'); // playing | finished
            $table->json('state');                              // lövhə, növbə, zərlər — App\Services\NardRules formatında
            $table->unsignedInteger('version')->default(1);     // hər dəyişiklikdə artır — brauzer yalnız yenilik olanda yeniləyir
            $table->unsignedTinyInteger('winner')->nullable();  // 0 / 1
            $table->boolean('mars')->default(false);
            $table->string('end_reason', 20)->nullable();       // finished | resigned | left
            $table->timestamp('player0_seen_at')->nullable();   // oyunçu səhifədən çıxıbmı (rəqib gözləməsin deyə)
            $table->timestamp('player1_seen_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'player0_id']);
            $table->index(['status', 'player1_id']);
        });

        Schema::create('nard_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending'); // pending | accepted | declined | cancelled
            $table->foreignId('match_id')->nullable()->constrained('nard_matches')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['to_user_id', 'status']);
            $table->index(['from_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nard_invites');
        Schema::dropIfExists('nard_matches');
    }
};
