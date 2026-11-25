<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WheelSpin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PrizeAdminTest extends TestCase
{
    use DatabaseTransactions;

    private function user(bool $admin = false): User
    {
        $u = User::create([
            'name' => 'Test İstifadəçi',
            'email' => 'p' . uniqid() . '@example.com',
            'phone' => '+99450' . random_int(1000000, 9999999),
            'password' => 'secret123',
        ]);
        $u->forceFill(['phone_verified_at' => now(), 'is_admin' => $admin])->save();

        return $u;
    }

    private function prize(User $u, array $attrs = []): WheelSpin
    {
        return WheelSpin::create($attrs + [
            'user_id' => $u->id,
            'prize_key' => 'discount_10',
            'prize_label' => '10% endirim',
            'code' => 'QP-' . strtoupper(substr(uniqid(), -6)),
            'spun_on' => now()->toDateString(),
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function test_admin_sees_registered_users(): void
    {
        $admin = $this->user(true);
        $player = $this->user();
        $player->forceFill(['name' => 'Siyahıdakı Oyunçu', 'last_seen_at' => now(), 'points' => 2.375])->save();
        $new = User::create(['name' => 'Təsdiqsiz Yeni', 'email' => 'n' . uniqid() . '@example.com', 'phone' => '+994517778899', 'password' => 'secret123']);
        $this->prize($player);

        // adi istifadəçi və qonaq görə bilmir
        $this->get('/admin/istifadeciler')->assertRedirect();
        $this->actingAs($player)->get('/admin/istifadeciler')->assertForbidden();
        $this->actingAs($player)->get('/hesabim')->assertOk()->assertDontSee('İdarəetmə')->assertDontSee(route('admin.users'));

        $this->actingAs($admin)->get('/admin/istifadeciler')->assertOk()
            ->assertSee('Siyahıdakı Oyunçu')->assertSee('Təsdiqsiz Yeni')->assertSee('1 kod')->assertSee('indi saytda')
            // e-poçt sütunu yoxdur, yerində xal balansı var
            ->assertSee('Xallar <i class="fa fa-sort"></i>', false)->assertDontSee('<th>E-poçt</th>', false)->assertSee('2,375');
        $this->actingAs($admin)->get('/admin/istifadeciler?filter=unverified')->assertOk()
            ->assertSee('Təsdiqsiz Yeni')->assertDontSee('Siyahıdakı Oyunçu');
        $this->actingAs($admin)->get('/admin/istifadeciler?q=7778899')->assertOk()
            ->assertSee('Təsdiqsiz Yeni')->assertDontSee('Siyahıdakı Oyunçu');

        // admin paneli yuxarı menyuda yox, "Hesabım" səhifəsindədir: hədiyyə kodları və istifadəçilər
        $this->actingAs($admin)->get('/hesabim')->assertOk()
            ->assertSee('İdarəetmə')->assertSee('Cəmi qeydiyyat')
            ->assertSee(route('admin.prizes'))->assertSee(route('admin.users'));
        $this->actingAs($admin)->get('/oyunlar')->assertOk()->assertDontSee('href="' . route('admin.prizes') . '"', false);
        $this->assertNotNull($new);
    }

    public function test_admin_deletes_user_with_their_data(): void
    {
        $admin = $this->user(true);
        $player = $this->user();
        $prize = $this->prize($player);
        \App\Models\GameResult::create(['user_id' => $player->id, 'game' => 'stack', 'won' => false, 'points' => 12]);
        // oyunçunun dəvət etdiyi istifadəçi qalır, sadəcə "dəvət edən" boşalır
        $invited = $this->user();
        $invited->forceFill(['referred_by' => $player->id])->save();

        // siyahıda silmə düyməsi var (öz hesabı üçün yoxdur)
        $this->actingAs($admin)->get('/admin/istifadeciler')->assertOk()
            ->assertSee('action="' . route('admin.users.destroy', $player) . '"', false)
            ->assertDontSee('action="' . route('admin.users.destroy', $admin) . '"', false);

        $this->actingAs($admin)->from('/admin/istifadeciler')->delete("/admin/istifadeciler/{$player->id}")
            ->assertRedirect('/admin/istifadeciler')->assertSessionHas('success');

        $this->assertNull(User::find($player->id));
        $this->assertNull(WheelSpin::find($prize->id));
        $this->assertSame(0, \App\Models\GameResult::where('user_id', $player->id)->count());
        $this->assertNotNull($invited->fresh());
        $this->assertNull($invited->fresh()->referred_by);
    }

    public function test_user_delete_is_admin_only_and_protects_admins(): void
    {
        $admin = $this->user(true);
        $otherAdmin = $this->user(true);
        $player = $this->user();
        $victim = $this->user();

        // qonaq və adi istifadəçi silə bilməz
        $this->delete("/admin/istifadeciler/{$victim->id}")->assertRedirect();
        $this->actingAs($player)->delete("/admin/istifadeciler/{$victim->id}")->assertForbidden();
        $this->assertNotNull($victim->fresh());

        // admin özünü və başqa admini silə bilməz
        $this->actingAs($admin)->delete("/admin/istifadeciler/{$admin->id}")->assertSessionHas('error');
        $this->actingAs($admin)->delete("/admin/istifadeciler/{$otherAdmin->id}")->assertSessionHas('error');
        $this->assertNotNull($admin->fresh());
        $this->assertNotNull($otherAdmin->fresh());

        // olmayan istifadəçi
        $this->actingAs($admin)->delete('/admin/istifadeciler/999999999')->assertNotFound();
    }

    public function test_new_users_are_guests(): void
    {
        $u = $this->user();
        $this->assertSame(User::TYPE_GUEST, $u->fresh()->type);
        $this->assertFalse($u->fresh()->is_admin);
        // köhnə yol (admin:make) tipi də dəyişir
        $u->forceFill(['is_admin' => true])->save();
        $this->assertSame(User::TYPE_ADMIN, $u->fresh()->type);
    }

    public function test_admin_changes_user_type(): void
    {
        $admin = $this->user(true);
        $player = $this->user();

        // siyahıda düzəliş düyməsi və tip görünür
        $this->actingAs($admin)->get('/admin/istifadeciler')->assertOk()
            ->assertSee(route('admin.users.edit', $player))->assertSee('Qonaq');
        $this->actingAs($admin)->get(route('admin.users.edit', $player))->assertOk()
            ->assertSee('Ofisiant')->assertSee('Yadda saxla');

        // qonaq → ofisiant
        $this->actingAs($admin)->put(route('admin.users.update', $player), ['type' => 'waiter'])
            ->assertRedirect(route('admin.users'))->assertSessionHas('success');
        $this->assertSame(User::TYPE_WAITER, $player->fresh()->type);
        $this->assertFalse($player->fresh()->is_admin);

        // ofisiant → admin: admin panelə girişi açılır
        $this->actingAs($admin)->put(route('admin.users.update', $player), ['type' => 'admin']);
        $this->assertTrue($player->fresh()->is_admin);
        $this->actingAs($player->fresh())->get('/admin/istifadeciler')->assertOk();

        // admin → qonaq: giriş bağlanır
        $this->actingAs($admin)->put(route('admin.users.update', $player), ['type' => 'guest']);
        $this->assertFalse($player->fresh()->is_admin);
        $this->actingAs($player->fresh())->get('/admin/istifadeciler')->assertForbidden();

        // naməlum tip qəbul olunmur
        $this->actingAs($admin)->put(route('admin.users.update', $player), ['type' => 'boss'])->assertSessionHasErrors('type');
        $this->assertSame(User::TYPE_GUEST, $player->fresh()->type);
    }

    public function test_users_can_be_sorted_by_points_high_to_low(): void
    {
        $admin = $this->user(true);
        $low = $this->user();
        $low->forceFill(['name' => 'Az Xallı', 'points' => 1])->save();
        $high = $this->user();
        $high->forceFill(['name' => 'Çox Xallı', 'points' => 9999])->save();
        $mid = $this->user();
        $mid->forceFill(['name' => 'Orta Xallı', 'points' => 50])->save();

        // susmaya görə ən yeni birinci; ?sort=points — çoxdan aza
        $this->actingAs($admin)->get('/admin/istifadeciler?sort=points')->assertOk()
            ->assertSeeInOrder(['Çox Xallı', 'Orta Xallı', 'Az Xallı'])
            ->assertSee('fa-sort-amount-down', false);
        $this->actingAs($admin)->get('/admin/istifadeciler')->assertOk()
            ->assertSeeInOrder(['Orta Xallı', 'Çox Xallı', 'Az Xallı']);
    }

    public function test_user_type_change_is_admin_only_and_not_for_self(): void
    {
        $admin = $this->user(true);
        $player = $this->user();

        $this->put(route('admin.users.update', $player), ['type' => 'admin'])->assertRedirect();
        $this->actingAs($player)->put(route('admin.users.update', $player), ['type' => 'admin'])->assertForbidden();
        $this->actingAs($player)->get(route('admin.users.edit', $player))->assertForbidden();
        $this->assertSame(User::TYPE_GUEST, $player->fresh()->type);

        // admin öz tipini dəyişə bilməz
        $this->actingAs($admin)->put(route('admin.users.update', $admin), ['type' => 'guest'])->assertSessionHas('error');
        $this->assertTrue($admin->fresh()->is_admin);
    }

    public function test_scanning_prize_qr_redeems_it_for_admin_only(): void
    {
        $admin = $this->user(true);
        $player = $this->user();
        $p = $this->prize($player);
        $url = route('admin.prizes.qr', $p->code);

        // oyunçunun səhifəsində QR üçün ünvan şablonu və aktiv kodun QR işarəsi var
        $this->actingAs($player)->get('/hediyyelerim')->assertOk()
            ->assertSee(route('admin.prizes.qr', '__CODE__'), false)
            ->assertSee('data-prize-qr="' . $p->code . '"', false)
            ->assertSee('js/prize-qr.js', false);

        // qonaq — girişə yönləndirilir, oyunçu — 403; kod istifadə olunmur
        auth()->logout();
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($player)->get($url)->assertForbidden();
        $this->assertNull($p->fresh()->redeemed_at);

        // admin oxudanda kod istifadə olunur, hədiyyə kodları səhifəsində nəticə görünür
        $this->actingAs($admin)->get($url)
            ->assertRedirect(route('admin.prizes', ['q' => $p->code]))
            ->assertSessionHas('success', fn ($m) => str_contains($m, $p->code));
        $this->assertNotNull($p->fresh()->redeemed_at);
        $this->assertSame($admin->id, $p->fresh()->redeemed_by);

        // ikinci dəfə — xəta, kiçik hərflə də tanınır; olmayan kod — xəta
        $this->actingAs($admin)->get(route('admin.prizes.qr', strtolower($p->code)))->assertSessionHas('error');
        $this->actingAs($admin)->get(route('admin.prizes.qr', 'QP-NOPE00'))->assertSessionHas('error');

        // istifadə olunmuş kodun QR-ı oyunçu səhifəsində göstərilmir
        $this->actingAs($player)->get('/hediyyelerim')->assertOk()->assertDontSee('data-prize-qr="' . $p->code . '"', false);
    }

    public function test_only_admin_can_open_panel(): void
    {
        $this->get('/admin/hediyyeler')->assertRedirect();
        $this->actingAs($this->user())->get('/admin/hediyyeler')->assertForbidden();
        $this->actingAs($this->user(true))->get('/admin/hediyyeler')->assertOk()->assertSee('Hədiyyə kodları');
    }

    public function test_admin_finds_code_and_redeems_it_once(): void
    {
        $admin = $this->user(true);
        $p = $this->prize($this->user());

        // Prefikssiz, kiçik hərflə axtarış da tapır
        $this->actingAs($admin)->get('/admin/hediyyeler?q=' . strtolower(substr($p->code, 3)))
            ->assertOk()->assertSee($p->code)->assertSee('İstifadə olundu');

        // İstifadə olunan kod silinmir — "istifadə olunub" kimi qeyd olunur (vaxt və admin)
        $this->actingAs($admin)->post("/admin/hediyyeler/{$p->id}/istifade")
            ->assertSessionHas('success', fn ($m) => str_contains($m, $p->code) && str_contains($m, 'İstifadə olunub'));
        $this->assertNotNull($p->fresh()->redeemed_at);
        $this->assertSame($admin->id, $p->fresh()->redeemed_by);
        // avtomatik təmizləmə də onu silmir
        WheelSpin::pruneExpired();
        $this->assertNotNull($p->fresh());

        // İkinci dəfə istifadə etmək olmur — aydın mesaj
        $this->actingAs($admin)->post("/admin/hediyyeler/{$p->id}/istifade")
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'artıq') && str_contains($m, 'istifadə olunub'));

        // Bölmələr ayrıdır: susmaya görə "Aktiv" (kod orada yoxdur), "İstifadə olunub"-da var
        $this->actingAs($admin)->get('/admin/hediyyeler')->assertOk()
            ->assertSee('status=used', false)->assertDontSee('<td><code>' . $p->code . '</code></td>', false);
        $this->actingAs($admin)->get('/admin/hediyyeler?status=used')->assertOk()
            ->assertSee($p->code)->assertSee($admin->name)->assertDontSee('>İstifadə olundu</button>', false);
        // axtarışda istifadə olunmuş kod da tapılır
        $this->actingAs($admin)->get('/admin/hediyyeler?q=' . $p->code)->assertOk()->assertSee($p->code)->assertSee('İstifadə olunub');
    }

    public function test_expired_code_cannot_be_redeemed(): void
    {
        $p = $this->prize($this->user(), ['expires_at' => now()->subDay()]);

        $this->actingAs($this->user(true))->post("/admin/hediyyeler/{$p->id}/istifade")->assertSessionHas('error');
        // istifadə olunmayıb; vaxtı keçmiş kupon sorğudan sonra bazadan avtomatik silinir
        $this->assertNull($p->fresh()?->redeemed_at);
        $this->assertNull($p->fresh());
    }

    public function test_non_admin_cannot_redeem(): void
    {
        $p = $this->prize($this->user());

        $this->actingAs($this->user())->post("/admin/hediyyeler/{$p->id}/istifade")->assertForbidden();
        $this->assertNull($p->fresh()->redeemed_at);
    }

    public function test_player_sees_only_own_active_prizes(): void
    {
        $u = $this->user();
        $active = $this->prize($u);
        $used = $this->prize($u, ['prize_label' => '20% endirim', 'redeemed_at' => now()]); // köhnə qayda ilə qalmış
        $other = $this->prize($this->user());

        $this->actingAs($u)->get('/hediyyelerim')->assertOk()
            ->assertSee($active->code)
            ->assertDontSee($other->code);

        // istifadə olunmuş kod silinmir, oyunçu onu "İstifadə olunmuş kuponlar"da görür
        \App\Models\WheelSpin::pruneExpired();
        $this->assertNotNull($used->fresh());
        $this->assertNotNull($active->fresh());
        $this->actingAs($u)->get('/hediyyelerim')->assertSee($used->code)->assertSee('İstifadə olunmuş kuponlar');
    }

    public function test_player_sees_used_coupons_but_not_expired_ones(): void
    {
        $u = $this->user();
        $active = $this->prize($u, ['prize_label' => 'Aktiv çay']);
        $used = $this->prize($u, ['prize_label' => 'İşlənmiş mürəbbə', 'redeemed_at' => now()->subHour(), 'expires_at' => now()->subMinute()]);
        // vaxtı keçib, hələ silinməyib (silinmə dəqiqədə bir dəfə olur) — yenə də göstərilməməlidir
        $expired = $this->prize($u, ['prize_label' => 'Köhnə qəlyan', 'expires_at' => now()->subMinute()]);

        $this->actingAs($u)->get('/hediyyelerim')->assertOk()
            ->assertSeeInOrder(['Aktiv hədiyyələr', $active->code, 'İstifadə olunmuş kuponlar', $used->code])
            ->assertDontSee($expired->code)->assertDontSee('Köhnə qəlyan')
            ->assertDontSee('Vaxtı keçmiş');

        // profildə: istifadə olunmuş kupon görünür və bölməyə keçid var, vaxtı keçmiş yox
        $this->actingAs($u)->get('/hesabim')->assertOk()
            ->assertSee($used->code)->assertSee($active->code)
            ->assertDontSee($expired->code)
            ->assertSee(route('prizes') . '#istifade-olunub', false);
    }

    public function test_make_admin_command(): void
    {
        $u = $this->user();

        $this->artisan('admin:make', ['phone' => $u->phone])->assertSuccessful();
        $this->assertTrue($u->fresh()->is_admin);

        $this->artisan('admin:make', ['phone' => $u->phone, '--remove' => true])->assertSuccessful();
        $this->assertFalse($u->fresh()->is_admin);

        $this->artisan('admin:make', ['phone' => '0559999999'])->assertFailed();
    }

    public function test_admin_logs_in_with_username(): void
    {
        $this->artisan('admin:create', ['username' => 'testadmin', '--password' => 'secret999'])->assertSuccessful();
        $admin = User::where('username', 'testadmin')->first();
        $this->assertTrue($admin->is_admin);
        $this->assertNull($admin->phone);

        $this->post('/giris', ['login' => 'testadmin', 'password' => 'wrong'])->assertSessionHasErrors('login');
        $this->assertGuest();

        $this->post('/giris', ['login' => 'TestAdmin', 'password' => 'secret999'])->assertRedirect('/admin/hediyyeler');
        $this->assertAuthenticatedAs($admin);
        $this->get('/admin/hediyyeler')->assertOk();

        // Telefonsuz admin "İndi saytda" siyahısında görünmür
        $this->assertFalse(User::online()->whereKey($admin->id)->exists());
    }
}
