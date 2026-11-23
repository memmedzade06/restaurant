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
            ->assertSee(route('admin.users.edit', $u) . '#xal', false);

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

    public function test_only_admins_can_change_points(): void
    {
        $player = $this->user(false, 1);
        $other = $this->user(false, 1);

        $this->put(route('admin.users.points', $other), ['mode' => 'set', 'amount' => '100'])->assertRedirect(route('login'));
        $this->actingAs($player)->put(route('admin.users.points', $other), ['mode' => 'set', 'amount' => '100'])->assertForbidden();
        $this->assertEquals(1, $other->fresh()->points);
    }
}
