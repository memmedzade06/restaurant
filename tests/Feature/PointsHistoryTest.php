<?php

namespace Tests\Feature;

use App\Http\Controllers\Frontend\GameController;
use App\Http\Controllers\Frontend\QuizController;
use App\Models\PointLog;
use App\Models\User;
use App\Services\Referrals;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Xal tarixçəsi: balans hər dəyişəndə qeyd olunur; admin və istifadəçi necə yığıldığını görür */
class PointsHistoryTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $name = 'Tarixçə Testi', ?User $parent = null, bool $admin = false, float $points = 0): User
    {
        $u = User::create([
            'name' => $name,
            'email' => 'ph' . uniqid() . '@example.com',
            'phone' => '+99450' . random_int(1000000, 9999999),
            'password' => 'secret123',
        ]);
        $u->forceFill(['phone_verified_at' => now(), 'is_admin' => $admin, 'points' => $points, 'referred_by' => $parent?->id])->save();

        return $u->fresh();
    }

    private function invoke(object $obj, string $method, ...$args)
    {
        $m = new \ReflectionMethod($obj, $method);
        $m->setAccessible(true);

        return $m->invoke($obj, ...$args);
    }

    public function test_every_points_change_is_logged_and_shown(): void
    {
        Cache::flush();
        config(['referral.levels' => [0.5, 0.25, 0.125], 'quiz.points_from_stage' => 3, 'quiz.stage_points' => 0.25, 'quiz.stage_points_step' => 0.25]);
        config(['games.stack.bonus' => ['tiers' => [40 => 0.5]]]);
        config(['market.items' => [['key' => 'market_tea', 'label' => 'Çaynik çay', 'cost' => 1, 'icon' => 'fa-mug-hot', 'desc' => 'Çay']], 'market_menu' => ['categories' => [], 'items' => []]]);

        $top = $this->user('Böyük Dəvətçi');
        $me = $this->user('Nicat Məmmədov', $top);
        $friend = $this->user('Nigar Hüseynova', $me);
        $friend->forceFill(['referral_rewarded_at' => null])->save();

        // 1) dəvət: dost təsdiqlədi — mənə 1-ci, yuxarıdakına 2-ci səviyyə
        Referrals::reward($friend);
        $this->assertDatabaseHas('point_logs', ['user_id' => $me->id, 'source' => 'referral', 'related_user_id' => $friend->id, 'amount' => 0.5]);
        $this->assertDatabaseHas('point_logs', ['user_id' => $top->id, 'source' => 'referral', 'amount' => 0.25]);

        // 2) Bilik Yarışı: 3-cü mərhələ
        $this->invoke(app(QuizController::class), 'awardStage', $me->fresh(), 3);
        // 3) Stəkan qülləsi: 40 stəkan bonusu
        $this->invoke(app(GameController::class), 'awardStackBonus', $me->fresh(), 42);
        // 4) Market: 1 xallıq çay
        $this->actingAs($me)->post('/hediyye-marketi/market_tea')->assertSessionHas('market_bought');
        $code = \App\Models\WheelSpin::where('user_id', $me->id)->where('game', 'market')->value('code');
        // 5) Admin: +2 xal, səbəb ilə
        $admin = $this->user('Baş Admin', null, true);
        $this->actingAs($admin)->put(route('admin.users.points', $me), ['mode' => 'add', 'amount' => '2', 'reason' => 'Turnir mükafatı']);

        // balans = qeydlərin cəmi: 0.5 + 0.25 + 0.5 − 1 + 2
        $me = $me->fresh();
        $this->assertEqualsWithDelta(2.25, $me->points, 0.0001);
        $this->assertEqualsWithDelta(2.25, PointLog::where('user_id', $me->id)->sum('amount'), 0.0001);
        $this->assertSame(['admin', 'market', 'quiz', 'referral', 'stack'], PointLog::where('user_id', $me->id)->orderBy('source')->pluck('source')->all());

        // admin: tam adlar, kupon kodu, adminin adı və səbəb
        $this->actingAs($admin)->get(route('admin.users.edit', $me))->assertOk()
            ->assertSee('Xal tarixçəsi')
            ->assertSee('Nigar Hüseynova')->assertSee('3-cü mərhələ keçildi')->assertSee('40 stəkan mərhələsi — bonus')
            ->assertSee('Çaynik çay · ' . $code)->assertSee('Baş Admin — Turnir mükafatı')
            ->assertDontSee('Əvvəlki xallar');

        // istifadəçinin özü: qısa ad, kupon kodu və adminin adı görünmür
        $this->actingAs($me)->get('/hesabim')->assertOk()
            ->assertSee('Xal tarixçəsi')
            ->assertSee('Hüseynova N')->assertDontSee('Nigar Hüseynova')
            ->assertSee('Çaynik çay')->assertDontSee('Çaynik çay · ' . $code)
            ->assertSee('Turnir mükafatı')->assertDontSee('Baş Admin');
    }

    public function test_points_from_before_history_are_shown_as_earlier_points(): void
    {
        $u = $this->user('Köhnə Oyunçu', null, false, 3.5); // tarixçədən əvvəl qazanılmış xallar
        $this->actingAs($u)->get('/hesabim')->assertOk()->assertSee('Əvvəlki xallar')->assertSee('+3,5');

        $empty = $this->user('Yeni Oyunçu');
        $this->actingAs($empty)->get('/hesabim')->assertOk()->assertSee('Hələ xal hərəkəti yoxdur');
    }
}
