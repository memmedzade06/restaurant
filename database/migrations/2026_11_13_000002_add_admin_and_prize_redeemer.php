<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin rolu və hədiyyə kodunu kimin "istifadə olundu" kimi qeyd etdiyi.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('phone_verified_at');
        });

        Schema::table('wheel_spins', function (Blueprint $table) {
            $table->foreignId('redeemed_by')->nullable()->after('redeemed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wheel_spins', function (Blueprint $table) {
            $table->dropConstrainedForeignId('redeemed_by');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
