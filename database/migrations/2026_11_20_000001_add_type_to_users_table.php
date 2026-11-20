<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * İstifadəçi tipi: guest (qonaq — qeydiyyatdan keçənlərin hamısı), waiter (ofisiant), admin.
 * Mövcud adminlər (is_admin = 1) "admin" tipini alır; is_admin sütunu model tərəfindən tiplə sinxron saxlanılır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('type', 20)->default('guest')->index()->after('is_admin');
        });

        DB::table('users')->where('is_admin', true)->update(['type' => 'admin']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
