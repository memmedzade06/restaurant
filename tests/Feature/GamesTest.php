<?php

namespace Tests\Feature;

use App\Models\GameResult;
use App\Models\User;
use App\Models\WheelSpin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class GamesTest extends TestCase
{
    use DatabaseTransactions;

    private function user(bool $verified = true): User
    {
        $u = User::create([
            'name' => 'Oyunçu Test',
            'email' => 'g' . uniqid() . '@example.com',
            'phone' => '+99455' . random_int(1000000, 9999999),
            'password' => 'secret123',
        ]);
        if ($verified) {
            $u->forceFill(['phone_verified_at' => now()])->save();
        }

        return $u;
    }

    public function test_pages_open_for_everyone(): void
    {
        $this->get('/oyunlar')->assertOk()->assertSee('Uzun nərd')->assertSee('Bəxt çarxı');
        $this->get('/oyunlar/nerd')->assertOk()->assertSee('data-can-save="0"', false);
        $this->get('/oyunlar/bext-carxi')->assertOk()->assertSee('<b>sınaq</b>', false);
    }

    public function test_guest_spin_is_demo_and_not_saved(): void
    {
        $before = WheelSpin::count();
        $this->postJson('/oyunlar/bext-carxi/firlat')->assertOk()->assertJson(['demo' => true]);
        $this->assertSame($before, WheelSpin::count());
    }

    /** Nəticəni proqnozlaşdırmaq üçün çarxda yalnız bir növ sektor qoyuruq */
    private function onlySegment(bool $prize): void
    {
        config(['games.wheel.attempts_per_day' => 3, 'games.wheel.segments' => [
            $prize
                ? ['key' => 'discount_10', 'label' => '10% endirim', 'color' => '#000', 'weight' => 1, 'prize' => true]
                : ['key' => 'try_again', 'label' => 'Şansını bir daha sına', 'color' => '#000', 'weight' => 1, 'prize' => false],
        ]]);
    }

    public function test_three_attempts_then_blocked(): void
    {
        $this->onlySegment(false);
        $u = $this->user();

        foreach ([2, 1, 0] as $left) {
            $this->actingAs($u)->postJson('/oyunlar/bext-carxi/firlat')->assertOk()
                ->assertJson(['demo' => false, 'prize' => false, 'state' => ['attemptsLeft' => $left]]);
        }
        $this->actingAs($u)->postJson('/oyunlar/bext-carxi/firlat')->assertStatus(429);
        // fırlatmalar bazaya yazılmır — yalnız götürülən hədiyyə yazılır
        $this->assertSame(0, WheelSpin::where('user_id', $u->id)->count());
    }

    public function test_code_is_created_only_when_prize_is_claimed(): void
    {
        $this->onlySegment(true);
        $u = $this->user();

        // fırlatma özü kod yaratmır
        $this->actingAs($u)->postJson('/oyunlar/bext-carxi/firlat')->assertOk()
            ->assertJson(['prize' => true, 'state' => ['attemptsLeft' => 2, 'last' => ['label' => '10% endirim'], 'claimed' => null]]);
        $this->assertSame(0, WheelSpin::where('user_id', $u->id)->whereNotNull('code')->count());

        // oyunçu yenidən fırlatmağı seçir — əvvəlki nəticə itir, hələ də kod yoxdur
        $this->actingAs($u)->postJson('/oyunlar/bext-carxi/firlat')->assertOk()->assertJson(['state' => ['attemptsLeft' => 1]]);

        // "Hədiyyəni götür" — kod yaranır, raund bağlanır
        $r = $this->actingAs($u)->postJson('/oyunlar/bext-carxi/gotur')->assertOk();
        $this->assertMatchesRegularExpression('/^QP-[A-Z0-9]{6}$/', $r->json('state.claimed.code'));
        $this->assertSame(now('Asia/Baku')->format('d.m.Y H:i'), $r->json('state.claimed.claimed_at')); // götürülmə vaxtı
        $this->assertSame(0, $r->json('state.attemptsLeft'));
        $this->assertSame(1, WheelSpin::where('user_id', $u->id)->count()); // 2 fırlatma, bazada 1 sətir
        $this->assertNotNull(WheelSpin::where('user_id', $u->id)->first()->code);

        // götürəndən sonra fırlatmaq olmur, ikinci "götür" eyni kodu qaytarır
        $this->actingAs($u)->postJson('/oyunlar/bext-carxi/firlat')->assertStatus(429);
        $this->actingAs($u)->postJson('/oyunlar/bext-carxi/gotur')->assertOk()->assertJson(['state' => ['claimed' => ['code' => $r->json('state.claimed.code')]]]);

        $this->actingAs($u)->get('/hesabim')->assertSee($r->json('state.claimed.code'));
    }

    public function test_cannot_claim_try_again_or_as_guest(): void
    {
        $this->postJson('/oyunlar/bext-carxi/gotur')->assertUnauthorized();

        $this->onlySegment(false);
        $u = $this->user();
        $this->actingAs($u)->postJson('/oyunlar/bext-carxi/gotur')->assertStatus(422); // heç fırlatmayıb
        $this->actingAs($u)->postJson('/oyunlar/bext-carxi/firlat')->assertOk();
        $this->actingAs($u)->postJson('/oyunlar/bext-carxi/gotur')->assertStatus(422); // "Şansını bir daha sına"
    }

    public function test_stack_game_saves_best_score_for_verified_only(): void
    {
        $this->get('/oyunlar/stekan')->assertOk()->assertSee('data-can-save="0"', false);
        $this->postJson('/oyunlar/stekan/netice', ['score' => 5])->assertUnauthorized();
        $this->actingAs($this->user(false))->postJson('/oyunlar/stekan/netice', ['score' => 5])->assertForbidden();

        $u = $this->user();
        $this->actingAs($u)->postJson('/oyunlar/stekan/netice', ['score' => 12])->assertOk()->assertJson(['best' => 12, 'record' => true]);
        $this->actingAs($u)->postJson('/oyunlar/stekan/netice', ['score' => 7])->assertOk()->assertJson(['best' => 12, 'record' => false]);
        $this->actingAs($u)->postJson('/oyunlar/stekan/netice', ['score' => -1])->assertStatus(422);

        // liderlər cədvəli ən yüksək nəticəni göstərir (cəmi yox)
        $this->get('/oyunlar')->assertSee('Stəkan qülləsi liderləri')->assertSee('12 xal');
        $this->actingAs($u)->get('/oyunlar/stekan')->assertSee('data-best="12"', false);
        $this->actingAs($u)->get('/hesabim')->assertSee('Stəkan qülləsi');
    }

    public function test_unverified_user_gets_demo_spin(): void
    {
        $this->actingAs($this->user(false))->postJson('/oyunlar/bext-carxi/firlat')->assertOk()->assertJson(['demo' => true]);
    }

    public function test_nard_result_saved_only_for_verified(): void
    {
        $this->postJson('/oyunlar/nerd/netice', ['won' => true])->assertUnauthorized();
        $this->actingAs($this->user(false))->postJson('/oyunlar/nerd/netice', ['won' => true])->assertForbidden();

        $u = $this->user();
        $this->actingAs($u)->postJson('/oyunlar/nerd/netice', ['won' => true, 'mars' => true])
            ->assertOk()->assertJson(['saved' => true, 'points' => 2]);
        $this->actingAs($u)->postJson('/oyunlar/nerd/netice', ['won' => false])->assertJson(['points' => 0]);

        $this->assertSame(2, (int) GameResult::where('user_id', $u->id)->sum('points'));
        $this->get('/oyunlar')->assertSee('Test O')->assertSee('2 xal');
        $this->actingAs($u)->get('/hesabim')->assertOk()->assertSee('Hədiyyələrim');
    }

    public function test_games_page_lists_online_users(): void
    {
        $online = $this->user();
        $online->forceFill(['name' => 'Onlayn Oyunçu', 'last_seen_at' => now()->subSeconds(20)])->save();
        $stale = $this->user();
        $stale->forceFill(['name' => 'Köhnə Ziyarətçi', 'last_seen_at' => now()->subMinutes(3)])->save();
        $unverified = $this->user(false);
        $unverified->forceFill(['name' => 'Təsdiqsiz Adam', 'last_seen_at' => now()])->save();

        $this->get('/oyunlar')->assertOk()
            ->assertSee('İndi saytda')
            ->assertSee('Oyunçu O')
            ->assertDontSee('Ziyarətçi K')
            ->assertDontSee('Adam T');
    }

    public function test_visiting_updates_last_seen(): void
    {
        $u = $this->user();
        $this->assertNull($u->last_seen_at);

        $this->actingAs($u)->get('/oyunlar')->assertOk()->assertSee($u->short_name);
        $this->assertNotNull($u->fresh()->last_seen_at);
    }

    public function test_presence_ping_leave_and_logout(): void
    {
        $u = $this->user();
        $u->forceFill(['name' => 'Canlı Oyunçu'])->save();

        $this->actingAs($u)->postJson('/aktiv')->assertNoContent();
        $this->getJson('/oyunlar/onlayn')->assertOk()->assertJsonFragment(['name' => 'Oyunçu C', 'me' => true]);

        // Səhifə bağlandı — qısa müddətdən sonra siyahıdan düşür
        $this->actingAs($u)->postJson('/aktiv/cixis')->assertNoContent();
        $this->travel(15)->seconds();
        $this->assertFalse(User::online()->whereKey($u->id)->exists());
        $this->travelBack();

        // Çıxış edən dərhal düşür
        $this->actingAs($u)->postJson('/aktiv');
        $this->actingAs($u)->post('/cixis');
        $this->assertNull($u->fresh()->last_seen_at);
    }

    public function test_guest_cannot_ping(): void
    {
        $this->postJson('/aktiv')->assertUnauthorized();
    }

    public function test_stack_prize_requires_started_game_and_enough_time(): void
    {
        $u = $this->user();

        // Oyun başlamayıb
        $this->actingAs($u)->postJson('/oyunlar/stekan/hediyye', ['score' => 40])->assertStatus(422);

        // Çox tez — 40 xal üçün ən azı 20 stəkan × 0.8 san
        $this->actingAs($u)->postJson('/oyunlar/stekan/basla')->assertOk();
        $this->actingAs($u)->postJson('/oyunlar/stekan/hediyye', ['score' => 40])->assertStatus(422);
        $this->assertSame(0, WheelSpin::where('user_id', $u->id)->count());
    }

    public function test_stack_prize_gives_tea_then_daily_limit(): void
    {
        $u = $this->user();

        // 39 xal — hədiyyə yoxdur
        $this->actingAs($u)->withSession(['stack_started_at' => microtime(true) - 60])
            ->postJson('/oyunlar/stekan/hediyye', ['score' => 39])->assertStatus(422);

        $r = $this->actingAs($u)->withSession(['stack_started_at' => microtime(true) - 60])
            ->postJson('/oyunlar/stekan/hediyye', ['score' => 45])->assertOk()
            ->assertJsonPath('prize.label', 'Çay Hədiyyə');
        $spin = WheelSpin::where('user_id', $u->id)->first();
        $this->assertSame('stack', $spin->game);
        $this->assertSame($r->json('prize.code'), $spin->code);
        $this->assertSame(45, (int) GameResult::where('user_id', $u->id)->where('game', 'stack')->max('points'));

        // Gündə bir hədiyyə
        $this->actingAs($u)->withSession(['stack_started_at' => microtime(true) - 60])
            ->postJson('/oyunlar/stekan/hediyye', ['score' => 55])->assertStatus(429);

        // Stəkan hədiyyəsi bəxt çarxını bloklamır
        $this->onlySegment(true);
        $this->actingAs($u)->postJson('/oyunlar/bext-carxi/firlat')->assertOk()->assertJson(['demo' => false]);
    }

    public function test_stack_prize_fifty_points_is_hookah(): void
    {
        $u = $this->user();
        $this->actingAs($u)->withSession(['stack_started_at' => microtime(true) - 60])
            ->postJson('/oyunlar/stekan/hediyye', ['score' => 50])->assertOk()
            ->assertJsonPath('prize.label', 'Qəlyan Hədiyyə');
    }

    public function test_stack_prize_sixty_points_is_half_price_bill(): void
    {
        $u = $this->user();
        $this->actingAs($u)->withSession(['stack_started_at' => microtime(true) - 60])
            ->postJson('/oyunlar/stekan/hediyye', ['score' => 60])->assertOk()
            ->assertJsonPath('prize.label', 'Hesaba 50% endirim');
    }

    public function test_stack_prize_needs_verified_user(): void
    {
        $this->postJson('/oyunlar/stekan/hediyye', ['score' => 8])->assertUnauthorized();
        $this->actingAs($this->user(false))->postJson('/oyunlar/stekan/hediyye', ['score' => 8])->assertForbidden();
    }
}
