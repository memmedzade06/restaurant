<?php

namespace Tests\Feature;

use App\Models\PointLog;
use App\Models\User;
use App\Models\WheelSpin;
use App\Support\Birthday;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Ad günü hədiyyəsi: doğum tarixi (bir dəfə, +1 xal) və ad günü gələndə masaya 50% endirim kuponu */
class BirthdayCouponTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function at(string $bakuDateTime): void
    {
        $t = Carbon::parse($bakuDateTime, 'Asia/Baku');
        Carbon::setTestNow($t);
        CarbonImmutable::setTestNow($t);
    }

    private function user(array $attrs = []): User
    {
        $u = User::create([
            'name' => 'Ad Günü Testi',
            'email' => 'bd' . uniqid() . '@example.com',
            'phone' => '+99450' . random_int(1000000, 9999999),
            'password' => 'secret123',
        ]);
        $u->forceFill(['phone_verified_at' => now()] + $attrs)->save();

        return $u->fresh();
    }

    public function test_user_sets_birth_date_once_and_gets_one_point(): void
    {
        $this->at('2026-06-01 12:00');
        $u = $this->user(['points' => 2]);

        $this->actingAs($u)->get(route('account'))->assertOk()
            ->assertSee('Ad günü hədiyyəsi')->assertSee('+1 xal')->assertSee(route('account.birthday'));

        $this->actingAs($u)->post(route('account.birthday'), ['birth_date' => '1995-08-14'])
            ->assertRedirect(route('account') . '#ad-gunu')->assertSessionHas('birthday_saved', 1.0);
        $u->refresh();
        $this->assertSame('1995-08-14', $u->birth_date->toDateString());
        $this->assertEquals(3, $u->points);
        $this->assertSame(1, PointLog::where('user_id', $u->id)->where('source', 'birthday')->count());

        // ikinci dəfə dəyişmək olmur, xal da yenidən verilmir
        $this->actingAs($u)->post(route('account.birthday'), ['birth_date' => '1995-06-01'])->assertSessionHasErrors('birthday');
        $this->assertSame('1995-08-14', $u->fresh()->birth_date->toDateString());
        $this->assertEquals(3, $u->fresh()->points);

        // profildə: ad günü tarixi və qalan günlər
        $this->actingAs($u)->get(route('account'))->assertSee('14 avqust')->assertSee('74 gün')->assertDontSee(route('account.birthday') . '"', false);
    }

    public function test_coupon_only_on_birthday_and_only_once_a_year(): void
    {
        $this->at('2026-03-10 10:00');
        $u = $this->user(['birth_date' => '1990-03-14', 'birth_date_set_at' => now()->subDays(60)]);

        // ad günü hələ gəlməyib
        $this->assertSame(Birthday::WAIT, Birthday::status($u)['state']);
        $this->actingAs($u)->post(route('account.birthday.claim'))->assertSessionHasErrors('birthday');
        $this->assertSame(0, WheelSpin::where('user_id', $u->id)->count());

        // ad günü: profildə düymə, digər səhifələrdə təbrik zolağı
        $this->at('2026-03-14 18:00');
        $this->assertSame(Birthday::AVAILABLE, Birthday::status($u->fresh())['state']);
        $this->actingAs($u)->get(route('account'))->assertSee('Ad gününüz mübarək!')->assertSee(route('account.birthday.claim'));
        $this->actingAs($u)->get(route('home'))->assertSee('id="bdayBar"', false);

        $this->actingAs($u)->post(route('account.birthday.claim'))->assertRedirect(route('prizes'));
        $coupon = WheelSpin::where('user_id', $u->id)->where('game', 'birthday')->sole();
        $this->assertStringContainsString('50%', $coupon->prize_label);
        $this->assertTrue($coupon->isActive());
        $this->assertTrue($coupon->expires_at->between(now()->addDays(7)->subMinute(), now()->addDays(7)->addMinute()));
        $this->assertSame(2026, (int) $u->fresh()->birthday_coupon_year);
        $this->actingAs($u)->get(route('prizes'))->assertSee($coupon->code)->assertSee('Ad günü hədiyyəsi');

        // ikinci dəfə — yox; zolaq da çıxmır
        $this->actingAs($u)->post(route('account.birthday.claim'))->assertSessionHasErrors('birthday');
        $this->assertSame(1, WheelSpin::where('user_id', $u->id)->count());
        $this->actingAs($u)->get(route('home'))->assertDontSee('id="bdayBar"', false);

        // yalnız ad günü: gecə 23:59-a qədər olar, ertəsi gün — artıq yox (gələn ilə qədər gözləyir)
        $other = $this->user(['birth_date' => '1990-03-14', 'birth_date_set_at' => now()->subDays(60)]);
        $this->at('2026-03-13 23:30');
        $this->assertSame(Birthday::WAIT, Birthday::status($other)['state']);
        $this->at('2026-03-14 23:50');
        $this->assertSame(Birthday::AVAILABLE, Birthday::status($other)['state']);
        $this->actingAs($other)->get(route('account'))->assertSee('Yalnız bu gün götürmək olar');
        $this->at('2026-03-15 00:10');
        $this->assertSame(Birthday::WAIT, Birthday::status($other)['state']);
        $this->assertSame(2027, Birthday::status($other)['birthday']->year);
        $this->actingAs($other)->post(route('account.birthday.claim'))->assertSessionHasErrors('birthday');
        $this->assertSame(0, WheelSpin::where('user_id', $other->id)->count());

        // gələn il yenə olur
        $this->at('2027-03-14 12:00');
        $this->assertSame(Birthday::AVAILABLE, Birthday::status($u->fresh())['state']);
    }

    public function test_freshly_entered_birth_date_gives_no_coupon_this_year(): void
    {
        $this->at('2026-03-14 12:00');
        $u = $this->user(['birth_date' => '1990-03-14', 'birth_date_set_at' => now()->subDays(5)]);

        $this->assertSame(Birthday::TOO_NEW, Birthday::status($u)['state']);
        $this->actingAs($u)->post(route('account.birthday.claim'))->assertSessionHasErrors('birthday');
        $this->actingAs($u)->get(route('account'))->assertSee('növbəti ad gününüzdə');
    }

    public function test_leap_day_and_new_year_window(): void
    {
        // 29 fevral — uzun olmayan ildə 28 fevral
        $this->at('2027-02-28 12:00');
        $leap = $this->user(['birth_date' => '2000-02-29', 'birth_date_set_at' => now()->subDays(90)]);
        $this->assertSame(Birthday::AVAILABLE, Birthday::status($leap)['state']);
        $this->assertSame('2027-02-28', Birthday::status($leap)['birthday']->toDateString());

        // 31 dekabr ad günü: həmin gün olar (il — 2026), 1 yanvar — artıq gələn ilin ad gününü gözləyir
        $this->at('2026-12-31 22:00');
        $dec = $this->user(['birth_date' => '1992-12-31', 'birth_date_set_at' => now()->subDays(90)]);
        $this->assertSame(Birthday::AVAILABLE, Birthday::status($dec)['state']);
        $this->assertNotNull(Birthday::claim($dec));
        $this->assertSame(2026, (int) $dec->fresh()->birthday_coupon_year);
        $this->at('2027-01-01 00:30');
        $this->assertSame(Birthday::WAIT, Birthday::status($dec->fresh())['state']);
        $this->assertSame(2027, Birthday::status($dec->fresh())['birthday']->year);
    }

    public function test_staff_get_no_birthday_gift(): void
    {
        $this->at('2026-03-14 12:00');
        $waiter = $this->user(['birth_date' => '1990-03-14', 'birth_date_set_at' => now()->subDays(60)]);
        $waiter->forceFill(['type' => User::TYPE_WAITER])->save();

        $this->assertSame(Birthday::NOT_ELIGIBLE, Birthday::status($waiter->fresh())['state']);
        $this->actingAs($waiter->fresh())->post(route('account.birthday.claim'))->assertSessionHasErrors('birthday');
        $this->assertSame(0, WheelSpin::where('user_id', $waiter->id)->count());
    }

    public function test_admin_fixes_birth_date_without_giving_extra_points(): void
    {
        $this->at('2026-06-01 12:00');
        $u = $this->user();
        $admin = $this->user(['is_admin' => true]);

        $this->actingAs($u)->post(route('account.birthday'), ['birth_date' => '1995-08-14']);
        $setAt = $u->fresh()->birth_date_set_at;

        // istifadəçi admin səhifəsinə daxil ola bilməz
        $this->actingAs($u)->put(route('admin.users.birthday', $u), ['birth_date' => '1995-06-01'])->assertForbidden();

        // admin düzəldir — "yazılma vaxtı" dəyişmir
        $this->at('2026-06-10 12:00');
        $this->actingAs($admin)->get(route('admin.users.edit', $u))->assertOk()->assertSee('14.08.1995');
        $this->actingAs($admin)->put(route('admin.users.birthday', $u), ['birth_date' => '1995-08-15'])->assertSessionHas('success');
        $this->assertSame('1995-08-15', $u->fresh()->birth_date->toDateString());
        $this->assertTrue($u->fresh()->birth_date_set_at->eq($setAt));

        // admin silir, istifadəçi yenidən yazır — tarix yazılır, amma xal ikinci dəfə verilmir
        $this->actingAs($admin)->put(route('admin.users.birthday', $u), ['birth_date' => '']);
        $this->assertNull($u->fresh()->birth_date);
        $points = $u->fresh()->points;
        $this->actingAs($u->fresh())->post(route('account.birthday'), ['birth_date' => '1995-08-15'])->assertSessionHas('birthday_saved', 0.0);
        $this->assertEquals($points, $u->fresh()->points);
    }
}
