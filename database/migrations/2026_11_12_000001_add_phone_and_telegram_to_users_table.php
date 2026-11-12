<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Qeydiyyatda telefon tələb olunur və Telegram bot vasitəsilə təsdiqlənir.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->unique()->after('email');          // +994XXXXXXXXX formatında
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
            $table->string('telegram_chat_id', 32)->nullable()->index()->after('phone_verified_at');
            $table->string('telegram_token', 64)->nullable()->unique()->after('telegram_chat_id'); // bir dəfəlik təsdiq kodu
            $table->timestamp('telegram_token_expires_at')->nullable()->after('telegram_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropUnique(['telegram_token']);
            $table->dropIndex(['telegram_chat_id']);
            $table->dropColumn(['phone', 'phone_verified_at', 'telegram_chat_id', 'telegram_token', 'telegram_token_expires_at']);
        });
    }
};
