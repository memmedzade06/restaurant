<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WheelSpin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Hədiyyə marketi: xallar balansdır, hədiyyə alınanda qiyməti çıxılır və kupon kodu yaranır.
 */
class MarketTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['market.items' => [
            ['key' => 'market_tea', 'label' => 'Çaynik çay', 'cost' => 5, 'icon' => 'fa-mug-hot', 'desc' => 'Çay'],
            ['key' => 'market_hookah', 'label' => 'Qəlyan', 'cost' => 2.5, 'icon' => 'fa-smoking', 'desc' => 'Qəlyan'],
        ], 'market.code_valid_days' => 30]);
    }

    private function user(float $points, bool $verified = true): User
    {
        $u = User::create([
            'name' => 'Market Test',
            'email' => 'm' . uniqid() . '@example.com',
            'phone' => '+99455' . random_int(1000000, 9999999),
            'password' => 'secret123',
        ]);
        $u->forceFill(['points' => $points, 'phone_verified_at' => $verified ? now() : null])->save();

        return $u;
    }

    public function test_market_page_lists_gifts_with_prices(): void
    {
        // baxmaq hamıya açıqdır, almaq üçün daxil olmaq lazımdır
        $this->get('/hediyye-marketi')->assertOk()->assertSee('Çaynik çay')->assertSee('5 xal')->assertSee('Daxil olun');

        $u = $this->user(3);
        $this->actingAs($u)->get('/hediyye-marketi')->assertOk()
            ->assertSee('Balansınız: 3 xal')
            ->assertSee('2 xal çatmır')            // çay: 5 − 3
            ->assertSee(route('market.buy', 'market_hookah')); // qəlyan (2.5) almaq olar

        // oyunlar səhifəsində və profildə marketə keçid var
        $this->get('/oyunlar')->assertSee('Hədiyyə marketi')->assertSee(route('market'));
        $this->actingAs($u)->get('/hesabim')->assertSee(route('market'));
    }

    public function test_buying_deducts_points_and_gives_coupon(): void
    {
        $u = $this->user(7.5);

        $this->actingAs($u)->post('/hediyye-marketi/market_tea')->assertRedirect(route('market'));
        $this->assertEquals(2.5, $u->fresh()->points);

        $coupon = WheelSpin::where('user_id', $u->id)->where('game', 'market')->firstOrFail();
        $this->assertSame('market_tea', $coupon->prize_key);
        $this->assertSame('Çaynik çay', $coupon->prize_label);
        $this->assertMatchesRegularExpression('/^QP-[A-Z0-9]{6}$/', $coupon->code);
        $this->assertTrue($coupon->isActive());
        $this->assertEqualsWithDelta(now()->addDays(30)->timestamp, $coupon->expires_at->timestamp, 60);

        // alışdan sonra kupon kodu göstərilir, Hədiyyələrim-də də var
        $this->actingAs($u)->get('/hediyye-marketi')->assertSee($coupon->code)->assertSee('Təbriklər');
        $this->actingAs($u)->get('/hediyyelerim')->assertSee($coupon->code)->assertSee('Hədiyyə marketi');

        // kəsrli qiymət: 2.5 xallıq qəlyan — balans 0 olur
        $this->actingAs($u)->post('/hediyye-marketi/market_hookah')->assertRedirect(route('market'));
        $this->assertEquals(0, $u->fresh()->points);
        $this->assertSame(2, WheelSpin::where('user_id', $u->id)->where('game', 'market')->count());
    }

    public function test_cannot_buy_without_enough_points(): void
    {
        $u = $this->user(4);
        $this->actingAs($u)->post('/hediyye-marketi/market_tea')
            ->assertRedirect(route('market'))->assertSessionHas('market_error');
        $this->assertEquals(4, $u->fresh()->points);
        $this->assertSame(0, WheelSpin::where('user_id', $u->id)->count());
    }

    public function test_guests_unverified_and_unknown_gifts_are_rejected(): void
    {
        $this->post('/hediyye-marketi/market_tea')->assertRedirect(route('login'));

        $unverified = $this->user(100, false);
        $this->actingAs($unverified)->post('/hediyye-marketi/market_tea')->assertRedirect(route('verify.telegram'));
        $this->assertEquals(100, $unverified->fresh()->points);

        $u = $this->user(100);
        $this->actingAs($u)->post('/hediyye-marketi/market_yoxdur')->assertNotFound();
        $this->assertEquals(100, $u->fresh()->points);
    }
}
