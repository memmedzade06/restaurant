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
        ]]);
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
        $this->assertEqualsWithDelta(now()->addDay()->timestamp, $coupon->expires_at->timestamp, 60);

        // alışdan sonra kupon kodu göstərilir, Hədiyyələrim-də də var
        $this->actingAs($u)->get('/hediyye-marketi')->assertSee($coupon->code)->assertSee('Təbriklər');
        $this->actingAs($u)->get('/hediyyelerim')->assertSee($coupon->code)->assertSee('Hədiyyə marketi');

        // kəsrli qiymət: 2.5 xallıq qəlyan — balans 0 olur
        $this->actingAs($u)->post('/hediyye-marketi/market_hookah')->assertRedirect(route('market'));
        $this->assertEquals(0, $u->fresh()->points);
        $this->assertSame(2, WheelSpin::where('user_id', $u->id)->where('game', 'market')->count());

        // kupon 1 gün (24 saat) etibarlıdır — qaydalarda da belə yazılıb
        $this->actingAs($u)->get('/hediyye-marketi')->assertSee('1 gün (24 saat)');
        $this->travel(25)->hours();
        $this->assertFalse($coupon->fresh()->isActive());
    }

    public function test_only_two_gifts_per_day(): void
    {
        config(['market.per_day' => 2]);
        $u = $this->user(100);

        $this->actingAs($u)->get('/hediyye-marketi')->assertSee('Bu gün: <b>2</b> / 2', false)
            ->assertSee('minimal masa hesabı <b>10 AZN</b>', false)->assertSee('Gündə ən çox <b>2 hədiyyə</b>', false);

        $this->actingAs($u)->post('/hediyye-marketi/market_tea')->assertSessionHas('market_bought');
        $this->actingAs($u)->post('/hediyye-marketi/market_hookah')->assertSessionHas('market_bought');
        $this->assertEquals(92.5, $u->fresh()->points);

        // üçüncü hədiyyə bu gün olmur — xal çıxılmır, kupon yaranmır
        $this->actingAs($u)->post('/hediyye-marketi/market_tea')
            ->assertRedirect(route('market'))->assertSessionHas('market_error');
        $this->assertEquals(92.5, $u->fresh()->points);
        $this->assertSame(2, WheelSpin::where('user_id', $u->id)->where('game', 'market')->count());
        $this->actingAs($u)->get('/hediyye-marketi')->assertSee('Bu gün: <b>0</b> / 2', false)->assertSee('Bu günün limiti bitib');

        // kupon istifadə olunub bazadan silinsə də, limit sıfırlanmır
        WheelSpin::where('user_id', $u->id)->delete();
        $this->actingAs($u)->post('/hediyye-marketi/market_tea')->assertSessionHas('market_error');

        // sabah yenə 2 hədiyyə
        $this->travel(1)->days();
        $this->actingAs($u)->post('/hediyye-marketi/market_tea')->assertSessionHas('market_bought');
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
