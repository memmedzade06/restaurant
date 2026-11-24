<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instagram səhifəmizi izləməyə görə xal: istifadəçi öz Instagram adını yazır,
 * admin izləyicilər siyahısında yoxlayıb təsdiqləyir — xal balansa və xal tarixçəsinə yazılır.
 * Hər istifadəçinin bir sorğusu var (rədd olunsa, yenidən göndərə bilər),
 * eyni Instagram adı iki hesaba yazıla bilməz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instagram_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('username', 30)->unique();           // Instagram adı, @ olmadan, kiçik hərflə
            $table->string('status', 10)->default('pending')->index(); // pending | approved | rejected
            $table->decimal('points', 8, 3)->nullable();        // təsdiqlənəndə yazılan xal
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_follows');
    }
};
