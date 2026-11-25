<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ad günü hədiyyəsi: istifadəçi doğum tarixini profildə bir dəfə yazır (sonra yalnız admin dəyişə bilər).
 * birth_date_set_at — tarixin yazıldığı an (kupon yalnız tarix çoxdan yazılıbsa verilir, config/birthday.php),
 * birthday_coupon_year — kuponun götürüldüyü ad gününün ili (ildə bir dəfə).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->after('phone');
            $table->timestamp('birth_date_set_at')->nullable()->after('birth_date');
            $table->unsignedSmallInteger('birthday_coupon_year')->nullable()->after('birth_date_set_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['birth_date', 'birth_date_set_at', 'birthday_coupon_year']);
        });
    }
};
