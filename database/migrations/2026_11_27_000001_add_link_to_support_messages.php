<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mesajda link düyməsi: admin hamıya mesaj göndərəndə link və düymənin yazısını əlavə edə bilər
 * (məs. "/hediyye-marketi" — "Hədiyyə marketinə keç"). İstifadəçi söhbətdə düyməni basıb həmin səhifəyə keçir.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['support_messages', 'support_broadcasts'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->string('link_url', 500)->nullable()->after('body');
                $table->string('link_label', 60)->nullable()->after('link_url');
            });
        }
    }

    public function down(): void
    {
        foreach (['support_messages', 'support_broadcasts'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->dropColumn(['link_url', 'link_label']);
            });
        }
    }
};
