<?php

namespace Tests\Feature;

use App\Models\GameResult;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Oyunlar səhifəsi: Stəkan liderləri (5 + "Hamısını göstər") və Xal liderləri */
class LeadersTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $name, float $points = 0, string $type = User::TYPE_GUEST, bool $verified = true): User
    {
        $u = User::create([
            'name' => $name,
            'email' => 'ld' . uniqid() . '@example.com',
            'phone' => '+99450' . random_int(1000000, 9999999),
            'password' => 'secret123',
        ]);
        $u->forceFill(['phone_verified_at' => $verified ? now() : null, 'type' => $type, 'points' => $points])->save();

        return $u->fresh();
    }

    public function test_stack_leaders_show_five_then_all_ten(): void
    {
        GameResult::where('game', 'stack')->delete(); // yalnız tranzaksiya daxilində
        foreach (range(1, 10) as $i) {
            $u = $this->user("Oyunçu{$i} Soyad{$i}");
            GameResult::create(['user_id' => $u->id, 'game' => 'stack', 'won' => true, 'points' => 200 - $i]);
        }

        $html = $this->get(route('games.index'))->assertOk()
            ->assertSee('Soyad1 O')->assertSee('Soyad10 O')
            ->assertSee('Hamısını göstər (10)')
            ->getContent();
        // stəkan cədvəlində 5 sətir gizli (6–10)
        $stack = substr($html, strpos($html, 'Stəkan qülləsi liderləri'));
        $stack = substr($stack, 0, strpos($stack, '</ol>'));
        $this->assertSame(5, substr_count($stack, 'data-more'));
    }

    public function test_points_leaders_list_guests_with_most_points(): void
    {
        User::query()->update(['points' => 0]); // yalnız tranzaksiya daxilində
        $this->user('Birinci Qonaq', 7.5);
        $this->user('İkinci Qonaq', 3.25);
        $this->user('Admin Adam', 50, User::TYPE_ADMIN);
        $this->user('Ofisiant Adam', 40, User::TYPE_WAITER);
        $this->user('Təsdiqsiz Adam', 30, User::TYPE_GUEST, false);

        $leaders = \App\Http\Controllers\Frontend\GameController::pointsLeaders(10);
        $this->assertSame(['Qonaq B', 'Qonaq İ'], $leaders->map(fn ($r) => $r->user->short_name)->all());

        $this->get(route('games.index'))->assertOk()
            ->assertSee('Xal liderləri')
            ->assertSee('7,5 xal')->assertSee('3,25 xal')
            ->assertDontSee('Adam A')->assertDontSee('Adam O')->assertDontSee('Adam T');
    }
}
