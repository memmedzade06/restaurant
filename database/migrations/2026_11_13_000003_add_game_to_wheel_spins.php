<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hədiyyə kodları artıq bir neçə oyundan gəlir: bəxt çarxı və stəkan qülləsi.
     */
    public function up(): void
    {
        Schema::table('wheel_spins', function (Blueprint $table) {
            $table->string('game', 20)->default('wheel')->after('user_id');
            $table->index(['game', 'user_id', 'spun_on']);
        });
    }

    public function down(): void
    {
        Schema::table('wheel_spins', function (Blueprint $table) {
            $table->dropIndex(['game', 'user_id', 'spun_on']);
            $table->dropColumn('game');
        });
    }
};
