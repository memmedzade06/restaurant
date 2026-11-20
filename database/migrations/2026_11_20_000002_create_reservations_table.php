<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Masa rezervasiyaları: qonaq saytda masanı seçib tarix, saat, ad-soyad və nömrəsini yazır.
 * table_key — config/reservations.php-dəki masa açarı ("1-k1", "2-m25").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('table_key', 20);
            $table->date('date');
            $table->string('time', 5); // "19:30", Bakı vaxtı
            $table->string('name', 120); // ad, soyad — bir xanada
            $table->string('phone', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('active')->index(); // active | cancelled
            $table->timestamps();

            $table->index(['date', 'table_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
