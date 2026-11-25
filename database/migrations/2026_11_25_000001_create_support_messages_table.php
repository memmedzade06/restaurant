<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adminlə yazışma: hər istifadəçinin adminlə bir söhbəti var (user_id — söhbətin sahibi).
 * İstifadəçi sual yazır, admin panelindən cavab verilir.
 * read_at — mesajı qarşı tərəf (istifadəçinin mesajını admin, adminin cavabını istifadəçi) oxuyanda yazılır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();                 // söhbətin sahibi
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete(); // yazan (istifadəçi və ya admin)
            $table->boolean('from_admin')->default(false);
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'from_admin', 'read_at']);
            $table->index(['from_admin', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
    }
};
