<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Xal tarixçəsi: istifadəçinin balansı hər dəyişəndə bir sətir — haradan gəlib (dəvət, Bilik Yarışı,
 * Stəkan qülləsi, market, admin), nə qədər, nə vaxt.
 *
 * Köhnə xallar əvvəllər harada isə qeyd olunmurdu. Bacardığımızı bərpa edirik (restored = true):
 *  - dəvət xalları — kim kimi, nə vaxt dəvət edib bazada var;
 *  - market alışları — bazada qalan kuponlardan.
 * Oyunlardan (Bilik, Stəkan) gələn köhnə xallar heç yerdə saxlanmayıb — tarixçədə "əvvəlki xallar" kimi görünür.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 3);                 // + qazanılan, − xərclənən
            $table->string('source', 20)->index();            // referral | quiz | stack | market | admin
            $table->foreignId('related_user_id')->nullable()->constrained('users')->nullOnDelete(); // dəvət: kimin sayəsində
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();        // admin düzəlişi: kim etdi
            $table->json('meta')->nullable();                 // səviyyə, mərhələ, hədiyyənin adı, kupon kodu...
            $table->string('note', 200)->nullable();          // admin düzəlişinin səbəbi
            $table->boolean('restored')->default(false);      // köhnə məlumatdan bərpa olunub
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['user_id', 'created_at']);
        });

        $this->restoreReferrals();
        $this->restoreMarket();
    }

    /** Dəvət xalları: hər təsdiqlənmiş dəvət üçün zəncir boyunca yuxarı (o vaxtkı qayda ilə) */
    private function restoreReferrals(): void
    {
        $levels = array_values(array_map(fn ($p) => max(0, (float) $p), config('referral.levels', [1])));
        $users = DB::table('users')->select('id', 'referred_by', 'type', 'is_admin', 'referral_rewarded_at')->get()->keyBy('id');
        $rows = [];
        foreach ($users as $u) {
            $rewardedAt = $u->referral_rewarded_at;
            if (! $u->referred_by || ! $rewardedAt) {
                continue;
            }
            $seen = [$u->id => true];
            $upId = $u->referred_by;
            foreach ($levels as $i => $points) {
                if (! $upId || isset($seen[$upId]) || ! isset($users[$upId])) {
                    break;
                }
                $seen[$upId] = true;
                $up = $users[$upId];
                $earns = ! $up->is_admin && in_array($up->type ?? 'guest', ['guest', 'waiter'], true);
                if ($points > 0 && $earns) {
                    $rows[] = [
                        'user_id' => $upId, 'amount' => $points, 'source' => 'referral', 'related_user_id' => $u->id,
                        'meta' => json_encode(['level' => $i + 1]), 'restored' => true, 'created_at' => $rewardedAt,
                    ];
                }
                $upId = $up->referred_by;
            }
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('point_logs')->insert($chunk);
        }
    }

    /** Market alışları: bazada qalan market kuponlarından (qiyməti indiki siyahıdan) */
    private function restoreMarket(): void
    {
        if (! Schema::hasTable('wheel_spins')) {
            return;
        }
        $cost = collect(config('market.items', []))->concat(config('market_menu.items', []))->pluck('cost', 'key');
        $rows = [];
        foreach (DB::table('wheel_spins')->where('game', 'market')->get(['user_id', 'prize_key', 'prize_label', 'code', 'created_at']) as $c) {
            if (! $c->user_id || ! isset($cost[$c->prize_key])) {
                continue;
            }
            $rows[] = [
                'user_id' => $c->user_id, 'amount' => -(float) $cost[$c->prize_key], 'source' => 'market',
                'meta' => json_encode(['label' => $c->prize_label, 'code' => $c->code], JSON_UNESCAPED_UNICODE),
                'restored' => true, 'created_at' => $c->created_at ?? now(),
            ];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('point_logs')->insert($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('point_logs');
    }
};
