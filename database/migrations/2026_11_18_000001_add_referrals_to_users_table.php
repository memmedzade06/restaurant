<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dəvət (referal) sistemi: hər istifadəçinin öz dəvət kodu var (link və QR kodda).
     * Bu kodla qeydiyyatdan keçən və nömrəsini Telegram ilə təsdiqləyən hər yeni istifadəçi
     * üçün dəvət edənin balansına xal yazılır (iki səviyyə: dəvət edənə 1, onu dəvət edənə 0.5).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code', 16)->nullable()->unique()->after('username');
            $table->foreignId('referred_by')->nullable()->after('referral_code')->constrained('users')->nullOnDelete();
            $table->timestamp('referral_rewarded_at')->nullable()->after('referred_by'); // dəvət edənə xal yazılıb (bir dəfə)
            // kəsrli ola bilər: dostunuzun dəvət etdiyi hər kəs üçün 0.5 xal
            $table->decimal('points', 10, 2)->unsigned()->default(0)->after('referral_rewarded_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by');
            $table->dropUnique(['referral_code']);
            $table->dropColumn(['referral_code', 'referral_rewarded_at', 'points']);
        });
    }
};
