<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Phone;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Dəvət sistemi: profildəki link/QR → qeydiyyat → Telegram təsdiqi → dəvət edənə xal.
 */
class ReferralTest extends TestCase
{
    use DatabaseTransactions;

    private const SECRET = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.telegram.bot_token' => 'TEST',
            'services.telegram.bot_username' => 'QepikTestBot',
            'services.telegram.webhook_secret' => self::SECRET,
            'referral.levels' => [0.25, 0.125, 0.06],
        ]);
        $this->app->forgetInstance(\App\Services\TelegramBot::class);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    }

    private function verifiedUser(): User
    {
        return User::create([
            'name' => 'Dəvət Edən',
            'email' => 'r' . uniqid() . '@example.com',
            'phone' => '+99455' . random_int(1000000, 9999999),
            'password' => 'secret123',
        ])->forceFill(['phone_verified_at' => now()]);
    }

    /** Dəvət cookie-si ilə qeydiyyat */
    private function registerWith(?string $code, string $phone): User
    {
        // testdə əvvəlki sorğunun girişi yaddaşda qalır — qeydiyyatı qonaq edir
        if (auth()->check()) {
            auth()->logout();
        }
        $req = $code ? $this->withCookie('ref', $code) : $this;
        $req->post('/qeydiyyat', [
            'name' => 'Yeni Qonaq',
            'phone' => $phone,
            'email' => 'n' . uniqid() . '@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertRedirect(route('verify.telegram'));

        return User::where('phone', Phone::normalize($phone))->firstOrFail();
    }

    /** Yeni istifadəçi nömrəsini Telegram ilə təsdiqləyir */
    private function verifyViaTelegram(User $user, string $phone, int $tg): void
    {
        $this->actingAs($user)->get('/tesdiq')->assertOk();
        $send = fn (array $extra) => $this->postJson('/api/telegram/webhook', [
            'update_id' => random_int(1, 1e6),
            'message' => ['message_id' => 1, 'from' => ['id' => $tg], 'chat' => ['id' => $tg, 'type' => 'private']] + $extra,
        ], ['X-Telegram-Bot-Api-Secret-Token' => self::SECRET])->assertOk();

        $send(['text' => '/start ' . $user->fresh()->telegram_token]);
        $send(['contact' => ['phone_number' => $phone, 'user_id' => $tg]]);
    }

    public function test_profile_shows_link_qr_and_points(): void
    {
        $u = $this->verifiedUser();
        $u->save();
        $page = $this->actingAs($u)->get('/hesabim')->assertOk()
            ->assertSee('Xallar')
            ->assertSee('id="refQr"', false)
            ->assertSee('qrcode-generator', false);

        $code = $u->fresh()->referral_code;
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{8}$/', $code);
        $page->assertSee(route('referral', $code));
        // kod bir dəfə yaranır, sonra dəyişmir
        $this->actingAs($u)->get('/hesabim');
        $this->assertSame($code, $u->fresh()->referral_code);
    }

    /** Ağac üçün istifadəçi: $parent-in linki ilə gəlib, $rewarded — profilini təsdiqləyib, xal yazılıb */
    private function invited(string $name, ?User $parent, bool $rewarded = true): User
    {
        $u = User::create([
            'name' => $name,
            'email' => 't' . uniqid() . '@example.com',
            'phone' => '+99451' . random_int(1000000, 9999999),
            'password' => 'secret123',
        ]);
        $u->forceFill(['referred_by' => $parent?->id, 'referral_rewarded_at' => $rewarded ? now() : null, 'phone_verified_at' => now()])->save();

        return $u;
    }

    public function test_profile_shows_invite_pyramid_three_levels_deep(): void
    {
        $me = $this->invited('Mən Özüm', null);
        $b = $this->invited('Bəhram Babayev', $me);           // 1-ci səviyyə
        $c = $this->invited('Cəmilə Cəfərova', $me, false);   // 1-ci, təsdiq gözləyir
        $d = $this->invited('Dadaş Dadaşov', $b);             // 2-ci
        $e = $this->invited('Elnur Eyvazov', $d);             // 3-cü
        $this->invited('Fərid Fərzəliyev', $e);               // 4-cü — göstərilmir (xal 3 səviyyəyə qədər)
        $this->invited('Kənar Adam', null);                    // başqasının dəvəti deyil

        $tree = \App\Services\Referrals::tree($me);
        $this->assertSame([2, 1, 1], array_column($tree['levels'], 'count'));
        $this->assertSame([1, 1, 1], array_column($tree['levels'], 'verified'));
        $this->assertEqualsWithDelta(0.25, $tree['levels'][0]['earned'], 0.0001);
        $this->assertSame(['Babayev B', 'Cəfərova C'], array_column($tree['nodes'], 'name'));
        $this->assertSame('Dadaşov D', $tree['nodes'][0]['children'][0]['name']);
        $this->assertSame('Eyvazov E', $tree['nodes'][0]['children'][0]['children'][0]['name']);
        $this->assertSame([], $tree['nodes'][0]['children'][0]['children'][0]['children']);

        // dövr olsa belə (məs. baza əl ilə dəyişdirilib) ağac sonsuz getmir
        $me->forceFill(['referred_by' => $e->id])->save();
        $this->assertSame([2, 1, 1], array_column(\App\Services\Referrals::tree($me->fresh())['levels'], 'count'));

        $this->actingAs($me)->get('/hesabim')->assertOk()
            ->assertSee('Dəvət piramidam')
            ->assertSee('Babayev B')->assertSee('Cəfərova C')->assertSee('Dadaşov D')->assertSee('Eyvazov E')
            ->assertDontSee('Fərzəliyev')->assertDontSee('Kənar')
            ->assertSee('3-cü səviyyə')->assertSee('gözləyir');
    }

    public function test_points_only_for_verified_invited_users_and_go_only_to_verified_inviters(): void
    {
        $top = $this->invited('Yuxarı Adam', null);
        $mid = $this->invited('Orta Adam', $top);
        $mid->forceFill(['phone_verified_at' => null, 'email_verified_at' => null])->save(); // təsdiqlənməmiş dəvət edən
        $new = $this->invited('Yeni Adam', $mid, false);
        $new->forceFill(['phone_verified_at' => null, 'email_verified_at' => null])->save();

        // dəvət olunan hələ təsdiqləməyib — heç kimə xal yoxdur, sonra da verilə bilər
        \App\Services\Referrals::reward($new);
        $this->assertNull($new->fresh()->referral_rewarded_at);
        $this->assertEquals(0, $mid->fresh()->points);
        $this->assertEquals(0, $top->fresh()->points);

        // təsdiqlədi: təsdiqlənməmiş "Orta" xal almır, zəncir yuxarı davam edir — "Yuxarı" 2-ci səviyyə xalını alır
        $new->forceFill(['phone_verified_at' => now()])->save();
        \App\Services\Referrals::reward($new);
        $this->assertNotNull($new->fresh()->referral_rewarded_at);
        $this->assertEquals(0, $mid->fresh()->points);
        $this->assertEqualsWithDelta(0.125, (float) $top->fresh()->points, 0.0001);

        // ikinci dəfə xal yazılmır
        \App\Services\Referrals::reward($new);
        $this->assertEqualsWithDelta(0.125, (float) $top->fresh()->points, 0.0001);
    }

    public function test_empty_pyramid_shows_hint(): void
    {
        $me = $this->invited('Tək Adam', null);
        $this->actingAs($me)->get('/hesabim')->assertOk()
            ->assertSee('Dəvət piramidam')
            ->assertSee('Hələ heç kim sizin linkinizlə qeydiyyatdan keçməyib');
    }

    public function test_link_remembers_code_and_redirects_to_registration(): void
    {
        $u = $this->verifiedUser();
        $u->save();
        $code = $u->referralCode();

        // olmayan kod yadda saxlanmır
        $this->get('/devet/ZZZZ9999')->assertRedirect(route('register'))->assertCookieMissing('ref');
        $this->get("/devet/{$code}")->assertRedirect(route('register'))->assertCookie('ref', $code);
        // kiçik hərflə yazılsa da işləyir
        $this->get('/devet/' . strtolower($code))->assertCookie('ref', $code);
        // qeydiyyat səhifəsində kim dəvət edib görünür
        $this->withCookie('ref', $code)->get('/qeydiyyat')->assertOk()->assertSee($u->short_name)->assertSee('dəvət edib');
        // daxil olmuş istifadəçi üçün sadəcə ana səhifə
        $this->actingAs($u)->get("/devet/{$code}")->assertRedirect(route('home'));
    }

    public function test_point_is_added_after_invited_user_verifies_phone(): void
    {
        $referrer = $this->verifiedUser();
        $referrer->save();
        $code = $referrer->referralCode();

        $new = $this->registerWith($code, '050 765 43 21');
        $this->assertSame($referrer->id, $new->referred_by);
        // qeydiyyat özü xal vermir — nömrə hələ təsdiqlənməyib
        $this->assertEquals(0, $referrer->fresh()->points);
        $this->actingAs($referrer->fresh())->get('/hesabim')->assertSee('Təsdiq gözləyir');

        $this->verifyViaTelegram($new, '994507654321', 4242);
        $this->assertTrue($new->fresh()->hasVerifiedPhone());
        $this->assertEquals(0.25, $referrer->fresh()->points);
        $this->assertNotNull($new->fresh()->referral_rewarded_at);

        // xal yalnız bir dəfə yazılır
        \App\Services\Referrals::reward($new->fresh());
        $this->assertEquals(0.25, $referrer->fresh()->points);

        // ikinci dəvət — balans artır
        $second = $this->registerWith($code, '070 555 66 77');
        $this->verifyViaTelegram($second, '994705556677', 4343);
        $this->assertEquals(0.5, $referrer->fresh()->points);

        $this->actingAs($referrer->fresh())->get('/hesabim')->assertOk()
            ->assertSee('2 nəfər')->assertSee('+0,25 xal')->assertDontSee('Təsdiq gözləyir');
    }

    public function test_registration_without_link_or_with_unverified_referrer_gives_nothing(): void
    {
        $plain = $this->registerWith(null, '051 111 22 33');
        $this->assertNull($plain->referred_by);

        // təsdiqlənməmiş istifadəçinin kodu etibarlı deyil
        $unverified = User::create([
            'name' => 'Təsdiqsiz', 'email' => 'u' . uniqid() . '@example.com',
            'phone' => '+99477' . random_int(1000000, 9999999), 'password' => 'secret123',
        ]);
        $code = $unverified->referralCode();
        $this->post('/cixis');
        $other = $this->registerWith($code, '055 222 33 44');
        $this->assertNull($other->referred_by);
    }

    public function test_points_flow_up_three_levels(): void
    {
        // Əli → Vəli → Həsən → Səbinə → Nərmin (hər biri növbətini dəvət edib)
        $ali = $this->verifiedUser();
        $ali->save();
        $chain = [$ali];
        foreach (['Vəli', 'Həsən', 'Səbinə', 'Nərmin'] as $name) {
            $u = $this->verifiedUser();
            $u->forceFill(['name' => $name, 'referred_by' => end($chain)->id])->save();
            $chain[] = $u;
        }
        [$ali, $veli, $hasan, $sabina, $narmin] = $chain;

        // Vəli təsdiqlənir: yalnız Əli +0.25 (yuxarıda başqa heç kim yoxdur)
        \App\Services\Referrals::reward($veli);
        $this->assertEquals(0.25, $ali->fresh()->points);

        // Həsən: Vəli +0.25, Əli +0.125
        \App\Services\Referrals::reward($hasan);
        $this->assertEquals(0.25, $veli->fresh()->points);
        $this->assertEquals(0.375, $ali->fresh()->points);

        // Səbinə: Həsən +0.25, Vəli +0.125, Əli +0.06
        \App\Services\Referrals::reward($sabina);
        $this->assertEquals(0.25, $hasan->fresh()->points);
        $this->assertEquals(0.375, $veli->fresh()->points);
        $this->assertEquals(0.435, $ali->fresh()->points);

        // Nərmin: Səbinə +0.25, Həsən +0.125, Vəli +0.06 — Əli üçün 4-cü səviyyədir, xal yoxdur
        \App\Services\Referrals::reward($narmin);
        $this->assertEquals(0.25, $sabina->fresh()->points);
        $this->assertEquals(0.375, $hasan->fresh()->points);
        $this->assertEquals(0.435, $veli->fresh()->points);
        $this->assertEquals(0.435, $ali->fresh()->points);

        // təkrar çağırış heç nə dəyişmir
        \App\Services\Referrals::reward($narmin->fresh());
        $this->assertEquals(0.25, $sabina->fresh()->points);

        // Əlinin profili: kəsrli balans (3 onluq rəqəm) və səviyyələr üzrə saylar
        $this->actingAs($ali->fresh())->get('/hesabim')->assertOk()
            ->assertSee('0,435')
            ->assertSee('Dostlarınızın dəvət etdikləri')
            ->assertSee('+0,25')
            ->assertSee('+0,125')
            ->assertSee('+0,06');
    }

    public function test_default_points_per_level(): void
    {
        // config/referral.php: dosta görə 0.25, dostun dostuna 0.125, 3-cü səviyyəyə 0.06
        $levels = require base_path('config/referral.php');
        $this->assertEquals([0.25, 0.125, 0.06], $levels['levels']);
    }

    public function test_points_are_formatted(): void
    {
        $f = [\App\Services\Referrals::class, 'format'];
        $this->assertSame('3', $f(3));
        $this->assertSame('1,5', $f(1.5));
        $this->assertSame('0,25', $f(0.25));
        $this->assertSame('0', $f(0));
        $this->assertSame('12,75', $f(12.75));
        $this->assertSame('0,125', $f(0.125));
        $this->assertSame('10,875', $f(10.875));
    }
}
