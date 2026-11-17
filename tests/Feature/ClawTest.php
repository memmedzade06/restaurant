<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WheelSpin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Hədiyyə Kranı: şarın içindəki hədiyyəni server seçir, gündə 3 cəhd, real kod (Bəxt çarxı kimi).
 */
class ClawTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['games.claw.min_seconds_per_attempt' => 0]); // testdə çəngəlin hərəkətini gözləmirik
    }

    private function user(bool $verified = true): User
    {
        $u = User::create([
            'name' => 'Kran Test',
            'email' => 'k' . uniqid() . '@example.com',
            'phone' => '+99455' . random_int(1000000, 9999999),
            'password' => 'secret123',
        ]);
        if ($verified) {
            $u->forceFill(['phone_verified_at' => now()])->save();
        }

        return $u;
    }

    /** Şarların içində yalnız bu hədiyyə olsun */
    private function only(bool $prize): void
    {
        config(['games.claw.prizes' => [$prize
            ? ['key' => 'claw_10', 'label' => '10% endirim', 'weight' => 1, 'prize' => true]
            : ['key' => 'claw_empty', 'label' => 'Boş şar', 'weight' => 1, 'prize' => false]]]);
    }

    /** Bir cəhd: çəngəl düşür, şar açılır */
    private function attempt(User $u)
    {
        $token = $this->actingAs($u)->postJson('/oyunlar/hediyye-krani/basla')->assertOk()->json('token');

        return $this->actingAs($u)->postJson('/oyunlar/hediyye-krani/ac', ['token' => $token]);
    }

    public function test_page_shows_balls_game(): void
    {
        $this->get('/oyunlar/hediyye-krani')->assertOk()->assertSee('data-verified="0"', false)->assertSee('sınaq');
        $this->actingAs($this->user())->get('/oyunlar/hediyye-krani')->assertOk()
            ->assertSee('data-verified="1"', false)->assertSee('"attemptsLeft":3', false);
        $this->get('/oyunlar')->assertSee('Rəngli şarı tutub');
    }

    public function test_guest_plays_demo_without_code(): void
    {
        $this->only(true);
        $before = WheelSpin::where('game', 'claw')->count();
        $token = $this->postJson('/oyunlar/hediyye-krani/basla')->assertOk()->assertJson(['demo' => true])->json('token');
        $this->postJson('/oyunlar/hediyye-krani/ac', ['token' => $token])->assertOk()
            ->assertJson(['demo' => true, 'prize' => true, 'label' => '10% endirim']);
        // açar bir dəfəlikdir
        $this->postJson('/oyunlar/hediyye-krani/ac', ['token' => $token])->assertStatus(422);
        $this->postJson('/oyunlar/hediyye-krani/gotur')->assertUnauthorized();
        $this->assertSame($before, WheelSpin::where('game', 'claw')->count());
    }

    public function test_prize_is_claimed_as_real_code_and_day_closes(): void
    {
        $this->only(true);
        $u = $this->user();

        $this->attempt($u)->assertOk()->assertJson(['demo' => false, 'prize' => true, 'label' => '10% endirim',
            'state' => ['attemptsLeft' => 2, 'last' => ['label' => '10% endirim', 'prize' => true]]]);

        $r = $this->actingAs($u)->postJson('/oyunlar/hediyye-krani/gotur')->assertOk();
        $code = $r->json('state.claimed.code');
        $this->assertMatchesRegularExpression('/^QP-[A-Z0-9]{6}$/', $code);
        $this->assertSame(0, $r->json('state.attemptsLeft'));

        $spin = WheelSpin::where('code', $code)->first();
        $this->assertSame('claw', $spin->game);
        $this->assertSame('Hədiyyə Kranı', $spin->gameLabel());
        $this->assertEqualsWithDelta(now()->addMinutes(10)->timestamp, $spin->expires_at->timestamp, 60);

        // hədiyyə götürülüb: əyləncə üçün oynamaq olar (cəhd sayılmır), amma yeni hədiyyə yoxdur
        $token = $this->actingAs($u)->postJson('/oyunlar/hediyye-krani/basla')->assertOk()->assertJson(['fun' => true])->json('token');
        $this->actingAs($u)->postJson('/oyunlar/hediyye-krani/ac', ['token' => $token])->assertOk()->assertJson(['fun' => true, 'demo' => false]);
        $this->actingAs($u)->postJson('/oyunlar/hediyye-krani/gotur')->assertOk()->assertJsonPath('state.claimed.code', $code); // eyni kod
        $this->assertSame(1, WheelSpin::where('user_id', $u->id)->count());
        $this->actingAs($u)->get('/hediyyelerim')->assertSee($code);

        // kod istifadə olunub bazadan silinsə də, bu gün yeni hədiyyə olmur — yalnız əyləncə
        $spin->delete();
        $this->actingAs($u)->postJson('/oyunlar/hediyye-krani/basla')->assertOk()
            ->assertJson(['fun' => true])->assertJsonPath('state.claimed.expired', true);
    }

    public function test_prize_only_in_one_game_per_day(): void
    {
        $this->only(true);
        config(['games.wheel.segments' => [['key' => 'discount_10', 'label' => '10% endirim', 'color' => '#000', 'weight' => 1, 'prize' => true]]]);
        $u = $this->user();

        // kranda hədiyyə götürdü
        $this->attempt($u)->assertOk();
        $this->actingAs($u)->postJson('/oyunlar/hediyye-krani/gotur')->assertOk();

        // çarx: oynamaq olar (əyləncə), xəbərdarlıq var, hədiyyə götürmək olmaz
        // xəbərdarlığı səhifədə JS göstərir — vəziyyətdə əyləncə rejimi var
        $this->actingAs($u)->get('/oyunlar/bext-carxi')->assertOk()->assertSee('"fun":true', false);
        $this->actingAs($u)->postJson('/oyunlar/bext-carxi/firlat')->assertOk()
            ->assertJson(['fun' => true, 'state' => ['blockedBy' => 'Hədiyyə Kranında', 'fun' => true]]);
        $this->actingAs($u)->postJson('/oyunlar/bext-carxi/gotur')->assertStatus(422);
        $this->assertSame(0, WheelSpin::where('user_id', $u->id)->where('game', 'wheel')->count());

        // tərsinə: çarxda götürən kranda yalnız əyləncə üçün oynayır
        $v = $this->user();
        $this->actingAs($v)->postJson('/oyunlar/bext-carxi/firlat')->assertOk();
        $this->actingAs($v)->postJson('/oyunlar/bext-carxi/gotur')->assertOk();
        $this->actingAs($v)->get('/oyunlar/hediyye-krani')->assertOk()->assertSee('Bəxt çarxında artıq hədiyyə qazanmısınız');
        $this->attempt($v)->assertOk()->assertJson(['fun' => true, 'state' => ['blockedBy' => 'Bəxt çarxında']]);
        $this->actingAs($v)->postJson('/oyunlar/hediyye-krani/gotur')->assertStatus(422);
        $this->assertSame(0, WheelSpin::where('user_id', $v->id)->where('game', 'claw')->count());
    }

    public function test_three_attempts_per_day_and_playing_again_loses_prize(): void
    {
        $this->only(true);
        $u = $this->user();

        $this->attempt($u)->assertOk();                                  // 1-ci: hədiyyə çıxdı, götürmür
        $this->actingAs($u)->postJson('/oyunlar/hediyye-krani/basla')    // 2-ci cəhd — əvvəlki hədiyyə itir
            ->assertOk()->assertJson(['state' => ['attemptsLeft' => 1, 'last' => null]]);
        $this->actingAs($u)->postJson('/oyunlar/hediyye-krani/gotur')->assertStatus(422);

        $this->attempt($u)->assertOk();                                  // 3-cü
        $this->actingAs($u)->postJson('/oyunlar/hediyye-krani/basla')->assertStatus(429); // 4-cü yoxdur
        // son hədiyyəni hələ götürmək olar
        $this->actingAs($u)->postJson('/oyunlar/hediyye-krani/gotur')->assertOk()->assertJsonPath('state.claimed.label', '10% endirim');
    }

    public function test_empty_ball_gives_nothing(): void
    {
        $this->only(false);
        $u = $this->user();
        $this->attempt($u)->assertOk()->assertJson(['prize' => false, 'label' => 'Boş şar']);
        $this->actingAs($u)->postJson('/oyunlar/hediyye-krani/gotur')->assertStatus(422);
        $this->assertSame(0, WheelSpin::where('user_id', $u->id)->count());
    }

    public function test_ball_cannot_be_opened_without_valid_token_or_too_fast(): void
    {
        $this->only(true);
        $u = $this->user();

        // cəhd başlamadan və ya saxta açarla açmaq olmur
        $this->actingAs($u)->postJson('/oyunlar/hediyye-krani/ac', ['token' => str_repeat('x', 32)])->assertStatus(422);
        $token = $this->actingAs($u)->postJson('/oyunlar/hediyye-krani/basla')->json('token');
        $this->actingAs($u)->postJson('/oyunlar/hediyye-krani/ac', ['token' => str_repeat('y', 32)])->assertStatus(422);

        // çəngəl enib-qalxmamış (çox tez) açmaq olmur
        config(['games.claw.min_seconds_per_attempt' => 30]);
        $this->actingAs($u)->postJson('/oyunlar/hediyye-krani/ac', ['token' => $token])->assertStatus(422);
        config(['games.claw.min_seconds_per_attempt' => 0]);

        // düzgün açar bir dəfə işləyir
        $this->actingAs($u)->postJson('/oyunlar/hediyye-krani/ac', ['token' => $token])->assertOk();
        $this->actingAs($u)->postJson('/oyunlar/hediyye-krani/ac', ['token' => $token])->assertStatus(422);
    }

    public function test_unverified_user_plays_demo(): void
    {
        $u = $this->user(false);
        $this->actingAs($u)->postJson('/oyunlar/hediyye-krani/basla')->assertOk()->assertJson(['demo' => true]);
        $this->actingAs($u)->postJson('/oyunlar/hediyye-krani/gotur')->assertForbidden();
    }
}
