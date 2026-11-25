<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hamıya mesaj: admin bir mesajı seçilmiş istifadəçilərin hamısının söhbətinə göndərir.
 * Hər istifadəçinin söhbətinə ayrıca support_messages sətri yazılır (broadcast_id ilə bağlı),
 * support_broadcasts — göndərişlərin tarixçəsi (kim, nə vaxt, kimə, neçə nəfərə).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->string('audience', 20);               // all | active30
            $table->unsignedInteger('recipients')->default(0);
            $table->timestamps();
        });

        Schema::table('support_messages', function (Blueprint $table) {
            $table->foreignId('broadcast_id')->nullable()->after('from_admin')
                ->constrained('support_broadcasts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('broadcast_id');
        });
        Schema::dropIfExists('support_broadcasts');
    }
};
