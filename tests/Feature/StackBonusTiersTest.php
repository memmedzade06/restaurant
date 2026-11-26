<?php

namespace Tests\Feature;

use App\Http\Controllers\Frontend\GameController;
use App\Models\PointLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Stəkan qülləsi: mərhələ bonusları (30 → 0,3, 50 → 0,5), hər mərhələ gündə bir dəfə — saytın öz ayarı ilə */
class StackBonusTiersTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(): User
    {
        $u = User::create([
            'name' => 'Stəkan Testi',
            'email' => 'sb' . uniqid() . '@example.com',
            'phone' => '+99450' . random_int(1000000, 9999999),
            'password' => 'secret123',
        ]);
        $u->forceFill(['phone_verified_at' => now(), 'points' => 0])->save();

        return $u->fresh();
    }

    private function award(User $u, int $score): float
    {
        $m = new \ReflectionMethod(GameController::class, 'awardStackBonus');
        $m->setAccessible(true);

        return (float) $m->invoke(app(GameController::class), $u->fresh(), $score);
    }

    public function test_each_tier_once_per_48_hours_and_all_reached_tiers_in_one_game(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-25 12:00', 'Asia/Baku'));
        $u = $this->user();

        // saytın ayarı: yalnız iki mərhələ
        $this->assertSame([30 => 0.3, 50 => 0.5], config('games.stack.bonus.tiers'));

        $this->assertSame(0.0, $this->award($u, 29));                 // mərhələyə çatmayıb
        $this->assertSame(0.3, $this->award($u, 35));                 // 30 mərhələsi
        $this->assertSame(0.0, $this->award($u, 33));                 // 30 bu gün alınıb — yox
        $this->assertSame(0.0, $this->award($u, 45));                 // 31–49: yeni mərhələ yoxdur
        $this->assertSame(0.5, $this->award($u, 55));                 // yalnız yuxarı mərhələ: 50
        $this->assertSame(0.0, $this->award($u, 100));                // hamısı alınıb (60 mərhələsi yoxdur)
        $this->assertEqualsWithDelta(0.8, $u->fresh()->points, 0.0001);
        $this->assertSame([30, 50], PointLog::where('user_id', $u->id)->where('source', 'stack')->orderBy('id')->get()->map(fn ($l) => $l->meta['tier'])->all());

        // bir oyunda birdən 70 stəkan — keçilən bütün mərhələlər: 0,3 + 0,5
        $fresh = $this->user();
        $this->assertSame(0.8, $this->award($fresh, 70));

        // ertəsi gün — hələ 48 saat keçməyib, eyni mərhələyə görə xal verilmir
        Carbon::setTestNow(Carbon::parse('2026-11-26 09:00', 'Asia/Baku'));
        $this->assertSame(0.0, $this->award($u, 31));
        // 48 saatdan sonra — mərhələ yenidən açılır
        Carbon::setTestNow(Carbon::parse('2026-11-27 12:01', 'Asia/Baku'));
        $this->assertSame(0.3, $this->award($u, 31));
        $this->assertEqualsWithDelta(1.1, $u->fresh()->points, 0.0001);
    }

    public function test_game_page_shows_tiers_and_what_is_done_today(): void
    {
        $u = $this->user();
        $this->award($u, 35);

        $this->actingAs($u)->get('/oyunlar/stekan')->assertOk()
            ->assertDontSee('gündə bir dəfə') // gözləmə qaydası oyunçuya göstərilmir
            ->assertSee('30 stəkan — +0,3 xal')->assertSee('50 stəkan — +0,5 xal')->assertDontSee('60 stəkan — +0,75 xal')
            ->assertSee('"score":30,"points":0.3,"done":true', false)
            ->assertSee('"score":50,"points":0.5,"done":false', false);

        // xal tarixçəsində mərhələ görünür
        $this->actingAs($u)->get(route('account'))->assertSee('30 stəkan mərhələsi — bonus');
    }

    public function test_staff_get_no_tier_bonus(): void
    {
        $waiter = $this->user();
        $waiter->forceFill(['type' => User::TYPE_WAITER])->save();

        $this->assertSame(0.0, $this->award($waiter, 55));
        $this->assertEqualsWithDelta(0.0, $waiter->fresh()->points, 0.0001);
    }
}
