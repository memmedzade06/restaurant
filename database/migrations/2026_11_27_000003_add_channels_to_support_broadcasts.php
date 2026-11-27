<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hamıya mesaj: hara göndərilir — sayt söhbəti (chat), Telegram, e-poçt (bir və ya bir neçəsi).
 * Telegram və e-poçt arxa planda göndərilir: *_total — neçə nəfərə göndəriləcək, *_sent — neçəsinə çatdı.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_broadcasts', function (Blueprint $table) {
            $table->string('channels', 40)->default('chat')->after('audience'); // "chat,telegram,email"
            $table->unsignedInteger('telegram_total')->nullable()->after('recipients');
            $table->unsignedInteger('telegram_sent')->nullable()->after('telegram_total');
            $table->unsignedInteger('email_total')->nullable()->after('telegram_sent');
            $table->unsignedInteger('email_sent')->nullable()->after('email_total');
        });
    }

    public function down(): void
    {
        Schema::table('support_broadcasts', function (Blueprint $table) {
            $table->dropColumn(['channels', 'telegram_total', 'telegram_sent', 'email_total', 'email_sent']);
        });
    }
};
