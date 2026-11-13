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

        $this->actingAs($admin)->post("/admin/hediyyeler/{$p->id}/istifade")->assertSessionHas('success');
        $p->refresh();
        $this->assertNotNull($p->redeemed_at);
        $this->assertSame($admin->id, $p->redeemed_by);
        $this->assertSame(WheelSpin::USED, $p->status());

        // İkinci dəfə istifadə etmək olmur
        $first = $p->redeemed_at;
        $this->actingAs($admin)->post("/admin/hediyyeler/{$p->id}/istifade")->assertSessionHas('error');
        $this->assertEquals($first, $p->fresh()->redeemed_at);
    }

    public function test_expired_code_cannot_be_redeemed(): void
    {
        $p = $this->prize($this->user(), ['expires_at' => now()->subDay()]);

        $this->actingAs($this->user(true))->post("/admin/hediyyeler/{$p->id}/istifade")->assertSessionHas('error');
        $this->assertNull($p->fresh()->redeemed_at);
    }

    public function test_non_admin_cannot_redeem(): void
    {
        $p = $this->prize($this->user());

        $this->actingAs($this->user())->post("/admin/hediyyeler/{$p->id}/istifade")->assertForbidden();
        $this->assertNull($p->fresh()->redeemed_at);
    }

    public function test_player_sees_active_and_used_prizes(): void
    {
        $u = $this->user();
        $active = $this->prize($u);
        $used = $this->prize($u, ['prize_label' => '20% endirim', 'redeemed_at' => now()]);
        $other = $this->prize($this->user());

        $this->actingAs($u)->get('/hediyyelerim')->assertOk()
            ->assertSee($active->code)
            ->assertSee($used->code)
            ->assertSee('Qüvvədən düşüb')
            ->assertDontSee($other->code);
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
