<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Admin paneldə istifadəçinin xal balansını dəyişmək */
class AdminUserPointsTest extends TestCase
{
    use DatabaseTransactions;

    private function user(bool $admin = false, float $points = 0): User
    {
        $u = User::create([
            'name' => 'Xal Testi',
            'email' => 'ap' . uniqid() . '@example.com',
            'phone' => '+99450' . random_int(1000000, 9999999),
            'password' => 'secret123',
        ]);
        $u->forceFill(['phone_verified_at' => now(), 'is_admin' => $admin, 'points' => $points])->save();

        return $u;
    }

    private function change(User $admin, User $u, array $data)
    {
        return $this->actingAs($admin)->put(route('admin.users.points', $u), $data);
    }

    public function test_admin_can_add_subtract_and_set_points(): void
    {
        $admin = $this->user(true);
        $u = $this->user(false, 5);

        // düzəliş səhifəsində xal bölməsi var, siyahıdakı xal ora aparır
        $this->actingAs($admin)->get(route('admin.users.edit', $u))->assertOk()
            ->assertSee('Xal balansı')->assertSee(route('admin.users.points', $u));
        $this->actingAs($admin)->get(route('admin.users'))->assertOk()
            ->assertSee(route('admin.users.edit', $u) . '#tarixce', false);

        $this->change($admin, $u, ['mode' => 'add', 'amount' => '2.5', 'reason' => 'Turnir'])
            ->assertRedirect(route('admin.users.edit', $u))->assertSessionHas('success');
        $this->assertEquals(7.5, $u->fresh()->points);

        $this->change($admin, $u, ['mode' => 'sub', 'amount' => '0,125']); // vergüllə də olur
        $this->assertEquals(7.375, $u->fresh()->points);

        $this->change($admin, $u, ['mode' => 'set', 'amount' => '10']);
        $this->assertEquals(10, $u->fresh()->points);

        $this->change($admin, $u, ['mode' => 'set', 'amount' => '0']);
        $this->assertEquals(0, $u->fresh()->points);
    }

    public function test_balance_cannot_go_negative_and_input_is_validated(): void
    {
        $admin = $this->user(true);
        $u = $this->user(false, 3);

        $this->change($admin, $u, ['mode' => 'sub', 'amount' => '5'])->assertSessionHas('points_error');
        $this->assertEquals(3, $u->fresh()->points);

        foreach (['-1', 'abc', '1.2345', ''] as $bad) {
            $this->change($admin, $u, ['mode' => 'add', 'amount' => $bad])->assertSessionHasErrors('amount');
        }
        $this->change($admin, $u, ['mode' => 'multiply', 'amount' => '2'])->assertSessionHasErrors('mode');
        $this->assertEquals(3, $u->fresh()->points);
    }

    public function test_admin_sees_users_invite_pyramid_with_verified_profiles_only(): void
    {
        $admin = $this->user(true);
        $owner = $this->user();
        $mk = function (string $name, User $parent, bool $verified = true) {
            $u = User::create(['name' => $name, 'email' => 'pt' . uniqid() . '@example.com', 'phone' => '+99451' . random_int(1000000, 9999999), 'password' => 'secret123']);
            $u->forceFill(['referred_by' => $parent->id, 'phone_verified_at' => $verified ? now() : null, 'referral_rewarded_at' => $verified ? now() : null])->save();

            return $u;
        };
        $a = $mk('Aqil Birinci', $owner);
        $mk('Gizli Təsdiqsiz', $owner, false);       // təsdiqləməyib — admin piramidasında görünmür
        $b = $mk('Bəhruz İkinci', $a);
        $mk('Cavid Üçüncü', $b);
        $mk('Dörd Dördüncü', User::where('name', 'Cavid Üçüncü')->first()); // 4-cü səviyyə — göstərilmir

        $this->actingAs($admin)->get(route('admin.users.edit', $owner))->assertOk()
            ->assertSee('Dəvət piramidası')
            ->assertSee('Aqil Birinci')->assertSee('Bəhruz İkinci')->assertSee('Cavid Üçüncü')   // tam ad
            ->assertSee(\App\Support\Phone::format($a->phone))                                     // əlaqə
            ->assertSee(route('admin.users.edit', $a))                                             // istifadəçiyə keçid
            ->assertDontSee('Gizli Təsdiqsiz')->assertDontSee('Dörd Dördüncü')
            ->assertSee('1 nəfər', false);

        // dəvəti olmayan istifadəçi
        $this->actingAs($admin)->get(route('admin.users.edit', $this->user()))
            ->assertOk()->assertSee('profilini təsdiqləyən hələ yoxdur');

        // istifadəçinin öz profilində əvvəlki kimi: qısa ad, təsdiq gözləyənlər də görünür
        $this->actingAs($owner)->get('/hesabim')->assertOk()
            ->assertSee('Birinci A')->assertSee('Təsdiqsiz G')->assertDontSee('Aqil Birinci');
    }

    public function test_only_admins_can_change_points(): void
    {
        $player = $this->user(false, 1);
        $other = $this->user(false, 1);

        $this->put(route('admin.users.points', $other), ['mode' => 'set', 'amount' => '100'])->assertRedirect(route('login'));
        $this->actingAs($player)->put(route('admin.users.points', $other), ['mode' => 'set', 'amount' => '100'])->assertForbidden();
        $this->assertEquals(1, $other->fresh()->points);
    }
}
