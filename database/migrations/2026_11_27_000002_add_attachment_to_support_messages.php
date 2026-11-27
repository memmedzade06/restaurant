<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mesaja fayl: admin həm konkret istifadəçiyə, həm hamıya mesajda fayl (şəkil, PDF, sənəd) göndərə bilər.
 * Fayl storage/app/support/ altında saxlanılır (açıq deyil) — yalnız mesajın sahibi və admin açır.
 * Hamıya mesajda fayl bir dəfə saxlanılır, bütün mesajlar eyni fayla istinad edir.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['support_messages', 'support_broadcasts'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->string('attachment_path')->nullable()->after('link_label');
                $table->string('attachment_name')->nullable()->after('attachment_path'); // istifadəçiyə görünən ad
                $table->string('attachment_mime', 100)->nullable()->after('attachment_name');
                $table->unsignedInteger('attachment_size')->nullable()->after('attachment_mime'); // bayt
            });
        }
    }

    public function down(): void
    {
        foreach (['support_messages', 'support_broadcasts'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->dropColumn(['attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size']);
            });
        }
    }
};
