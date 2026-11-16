<?php

namespace Tests\Feature;

use App\Models\NardInvite;
use App\Models\NardMatch;
use App\Models\User;
use App\Services\NardLeaderboard;
use App\Services\NardRules;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class NardOnlineTest extends TestCase
{
    use DatabaseTransactions;

    private function player(bool $online = true): User
    {
        $u = User::create([
            'name' => 'Oyunçu ' . uniqid(),
            'email' => 'n' . uniqid() . '@example.com',
            'phone' => '+99450' . random_int(1000000, 9999999),
            'password' => 'secret123',
        ]);
        $u->forceFill(['phone_verified_at' => now(), 'last_seen_at' => $online ? now() : now()->subHour()])->save();

        return $u;
    }

    /** A B-ni dəvət edir, B qəbul edir — oyun yaranır */
    private function startMatch(User $a, User $b): NardMatch
    {
        $id = $this->actingAs($a)->postJson('/oyunlar/nerd/devet', ['user_id' => $b->id])->assertOk()->json('invite.id');
        $this->actingAs($b)->postJson("/oyunlar/nerd/devet/{$id}/qebul")->assertOk()->assertJsonStructure(['match_url']);

        return NardMatch::latest('id')->first();
    }

    public function test_rules_start_and_head_rule(): void
    {
        $s = NardRules::newGame(fn () => 1 + (static::$seq++ % 2)); // 1, 2 → oyunçu 1 başlayır
        $this->assertSame(1, $s['turn']);
        $this->assertSame([0, 15], $s['pts'][0]);
        $this->assertSame([1, 15], $s['pts'][12]);

        // 5-3: başdan yalnız bir daş götürmək olar
        $s = NardRules::roll($s, 5, 3);
        $s = NardRules::move($s, 12, 17);
        $this->assertNotNull($s);
        $this->assertNull(NardRules::move($s, 12, 15), 'başdan ikinci daş götürmək olmaz');
        $s = NardRules::move($s, 17, 20);
        $this->assertNotNull($s);
        $this->assertSame(0, $s['turn'], 'zərlər bitəndə növbə rəqibə keçir');
        $this->assertSame([[12, 17], [17, 20]], $s['last']);
    }

    private static int $seq = 0;

    public function test_invite_accept_and_play_from_both_sides(): void
    {
        [$a, $b] = [$this->player(), $this->player()];
        $this->actingAs($b)->getJson('/oyunlar/nerd/devetler')->assertOk()->assertJsonCount(0, 'incoming');

        $id = $this->actingAs($a)->postJson('/oyunlar/nerd/devet', ['user_id' => $b->id])->assertOk()->json('invite.id');
        $this->actingAs($b)->getJson('/oyunlar/nerd/devetler')->assertOk()->assertJsonPath('incoming.0.id', $id);

        $url = $this->actingAs($b)->postJson("/oyunlar/nerd/devet/{$id}/qebul")->assertOk()->json('match_url');
        $match = NardMatch::latest('id')->first();
        $this->assertSame((int) $a->id, (int) $match->player0_id);
        $this->actingAs($a)->getJson('/oyunlar/nerd/devetler')->assertJsonPath('outgoing.status', 'accepted')->assertJsonPath('outgoing.match_url', $url);

        // oyun səhifəsi yalnız oyunçulara açıqdır
        $this->actingAs($a)->get($url)->assertOk()->assertSee('data-match-url', false);
        $this->actingAs($this->player())->get($url)->assertForbidden();

        // növbəsi olan oyunçu zər atır və serverin verdiyi gedişlərdən birini edir — hər iki tərəfdən
        foreach (range(1, 6) as $i) {
            $match->refresh();
            $seat = $match->state['turn'];
            $user = $seat === 0 ? $a : $b;
            $other = $seat === 0 ? $b : $a;

            $this->actingAs($other)->postJson("/oyunlar/nerd/oyun/{$match->id}/zer", [])->assertStatus(422);
            $d = $this->actingAs($user)->postJson("/oyunlar/nerd/oyun/{$match->id}/zer", [])->assertOk()->json();
            $this->assertSame(0, $d['turn'], 'oyunçu öz baxışında həmişə 0-dır');
            $this->assertSame(15, $d['pts'][0][1] + $this->onBoardFor($d), 'öz başı həmişə 0-cı xanadadır');

            while ($d['turn'] === 0 && $d['legal']) {
                [$from, $to, $die] = $d['legal'][0];
                $d = $this->actingAs($user)->postJson("/oyunlar/nerd/oyun/{$match->id}/gedis", ['from' => $from, 'to' => $to, 'die' => $die])->assertOk()->json();
            }
            $this->assertSame(1, $d['turn'], 'gedişlər bitəndə növbə rəqibə keçir');
        }

        // qaydaya zidd gediş qəbul olunmur
        $match->refresh();
        $seat = $match->state['turn'];
        $user = $seat === 0 ? $a : $b;
        $this->actingAs($user)->postJson("/oyunlar/nerd/oyun/{$match->id}/zer", [])->assertOk();
        $this->actingAs($user)->postJson("/oyunlar/nerd/oyun/{$match->id}/gedis", ['from' => 12, 'to' => 13])->assertStatus(422);
    }

    /** Oyunçunun öz baxışında başdan başqa xanalardakı ağ daşlar (yoxlama üçün) */
    private function onBoardFor(array $d): int
    {
        $n = 0;
        foreach ($d['pts'] as $b => [$o, $c]) {
            if ($o === 0 && $b !== 0) {
                $n += $c;
            }
        }

        return $n + $d['off'][0];
    }

    public function test_resign_and_opponent_leaving_end_the_game(): void
    {
        [$a, $b] = [$this->player(), $this->player()];
        $match = $this->startMatch($a, $b);

        $this->actingAs($a)->postJson("/oyunlar/nerd/oyun/{$match->id}/teslim", [])->assertOk()
            ->assertJsonPath('result.won', false)->assertJsonPath('result.reason', 'resigned');
        $this->actingAs($b)->getJson("/oyunlar/nerd/oyun/{$match->id}/veziyyet")->assertJsonPath('result.won', true);

        // yeni oyun: B uzun müddət görünmür — A qalib sayılır
        $match = $this->startMatch($a, $b);
        $match->forceFill(['player1_seen_at' => now()->subMinutes(5)])->save();
        $this->actingAs($a)->getJson("/oyunlar/nerd/oyun/{$match->id}/veziyyet")->assertOk()
            ->assertJsonPath('result.won', true)->assertJsonPath('result.reason', 'left');
    }

    public function test_invite_rules(): void
    {
        [$a, $b] = [$this->player(), $this->player()];
        $offline = $this->player(false);

        $this->actingAs($a)->postJson('/oyunlar/nerd/devet', ['user_id' => $a->id])->assertStatus(422);
        $this->actingAs($a)->postJson('/oyunlar/nerd/devet', ['user_id' => $offline->id])->assertStatus(422);

        // rədd etmə
        $id = $this->actingAs($a)->postJson('/oyunlar/nerd/devet', ['user_id' => $b->id])->json('invite.id');
        $this->actingAs($a)->postJson("/oyunlar/nerd/devet/{$id}/qebul")->assertForbidden(); // özü qəbul edə bilməz
        $this->actingAs($b)->postJson("/oyunlar/nerd/devet/{$id}/redd")->assertOk();
        $this->actingAs($a)->getJson('/oyunlar/nerd/devetler')->assertJsonPath('outgoing.status', 'declined');
        $this->actingAs($b)->postJson("/oyunlar/nerd/devet/{$id}/qebul")->assertStatus(422);

        // vaxtı keçmiş dəvət qəbul olunmur
        $id = $this->actingAs($a)->postJson('/oyunlar/nerd/devet', ['user_id' => $b->id])->json('invite.id');
        NardInvite::whereKey($id)->update(['expires_at' => now()->subSecond()]);
        $this->actingAs($b)->postJson("/oyunlar/nerd/devet/{$id}/qebul")->assertStatus(422);

        // oyunda olan oyunçunu dəvət etmək olmur
        $c = $this->player();
        $this->startMatch($a, $b);
        $this->actingAs($c)->postJson('/oyunlar/nerd/devet', ['user_id' => $a->id])->assertStatus(422);
        $this->actingAs($c)->getJson('/oyunlar/nerd/oyuncular')->assertOk()->assertJsonFragment(['id' => $a->id, 'busy' => true]);
        $this->actingAs($a)->getJson('/oyunlar/nerd/devetler')->assertJsonPath('match_url', route('games.nard.match', NardMatch::latest('id')->first()));
    }

    /** Oyunçu 0-ın son daşı evdə, bir gedişlə çıxıb udur; oyunçu 1 heç daş çıxarmayıb (mars olsun) */
    private function nearWin(NardMatch $match, bool $mars = true): void
    {
        $s = $match->state;
        $s['pts'] = array_fill(0, 24, [-1, 0]);
        $s['pts'][23] = [0, 1];
        $s['pts'][12] = [1, $mars ? 15 : 14];
        $s['off'] = [14, $mars ? 0 : 1];
        $s['turn'] = 0;
        $s['rolled'] = [1, 2];
        $s['dice'] = [1]; // 2 artıq oynanıb — qalan 1 zəri ilə son daş çıxır
        $s['headUsed'] = 0;
        $s['headLimit'] = 1;
        $s['turnMoves'] = [];
        $match->forceFill(['state' => $s])->save();
    }

    public function test_finished_match_gives_weekly_points_to_winner(): void
    {
        [$a, $b] = [$this->player(), $this->player()];
        $match = $this->startMatch($a, $b);
        $this->nearWin($match);

        $this->actingAs($a)->postJson("/oyunlar/nerd/oyun/{$match->id}/gedis", ['from' => 23, 'to' => 'off', 'die' => 1])->assertOk()
            ->assertJsonPath('result.won', true)->assertJsonPath('result.mars', true)->assertJsonPath('result.points', 2);
        $this->actingAs($b)->getJson("/oyunlar/nerd/oyun/{$match->id}/veziyyet")->assertJsonPath('result.won', false)->assertJsonPath('result.points', 0);

        $this->assertSame(['games' => 1, 'wins' => 1, 'points' => 2], NardLeaderboard::myToday($a->id));
        $this->assertSame(['games' => 1, 'wins' => 0, 'points' => 0], NardLeaderboard::myToday($b->id));
        $this->get('/oyunlar')->assertOk()->assertSee('bu günün liderləri')->assertSee($a->short_name)->assertSee('2 xal');
    }

    public function test_resign_gives_no_points_and_pair_has_daily_limit(): void
    {
        [$a, $b] = [$this->player(), $this->player()];
        $match = $this->startMatch($a, $b);
        $this->actingAs($b)->postJson("/oyunlar/nerd/oyun/{$match->id}/teslim", [])->assertJsonPath('result.points', 0);
        $this->actingAs($a)->getJson("/oyunlar/nerd/oyun/{$match->id}/veziyyet")->assertJsonPath('result.won', true)->assertJsonPath('result.points', 0);
        $this->assertSame(0, NardLeaderboard::myToday($a->id)['points']);

        // eyni iki oyunçu: gündə yalnız ilk N tam oyun xal verir
        $limit = (int) config('games.nard.pair_daily_limit');
        $got = [];
        for ($i = 0; $i <= $limit; $i++) {
            $match = $this->startMatch($a, $b);
            $this->nearWin($match, false);
            $got[] = $this->actingAs($a)->postJson("/oyunlar/nerd/oyun/{$match->id}/gedis", ['from' => 23, 'to' => 'off', 'die' => 1])->json('result.points');
        }
        $this->assertSame(array_merge(array_fill(0, $limit, 1), [0]), $got);
        $this->assertSame($limit, NardLeaderboard::myToday($a->id)['points']);
    }

    private function dayResult(User $u, int $points, $at): void
    {
        $r = \App\Models\GameResult::create(['user_id' => $u->id, 'game' => NardLeaderboard::GAME, 'won' => $points > 0, 'points' => $points]);
        $r->forceFill(['created_at' => $at])->save();
    }

    public function test_day_end_gives_discount_codes_and_clears_results(): void
    {
        [$a, $b, $c, $d] = [$this->player(), $this->player(), $this->player(), $this->player()];
        $yesterday = NardLeaderboard::dayStart(1)->addHours(15);
        foreach ([[$a, 5], [$b, 3], [$c, 2], [$d, 1]] as [$u, $pts]) {
            $this->dayResult($u, $pts, $yesterday);
        }
        $this->dayResult($d, 4, NardLeaderboard::dayStart()->addMinute()); // bu günün nəticəsi qalmalıdır

        // səhifə açılanda dünənə yekun vurulur
        $this->actingAs($a)->get('/oyunlar/nerd')->assertOk()
            ->assertSee('Dünənin qalibləri')->assertSee($a->short_name)->assertSee('Hesaba 50% endirim')
            ->assertSee('siz qaliblərdəsiniz', false);

        $winners = NardLeaderboard::yesterdayWinners();
        $this->assertSame([$a->id, $b->id, $c->id], $winners->pluck('user_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame(['nard_week_50', 'nard_week_25', 'nard_week_10'], $winners->map(fn ($w) => $w->prize->prize_key)->all());
        $this->assertSame('Hesaba 50% endirim (1-ci yer)', $winners[0]->prize->prize_label);
        $this->assertTrue($winners[0]->prize->isActive());
        $this->actingAs($a)->get('/hediyyelerim')->assertOk()->assertSee($winners[0]->prize->code);

        // dünənin nəticələri silindi, bu günün nəticəsi qaldı
        $this->assertSame(0, \App\Models\GameResult::where('game', NardLeaderboard::GAME)->where('created_at', '<', NardLeaderboard::dayStart())->count());
        $this->assertSame(4, NardLeaderboard::myToday($d->id)['points']);

        // təkrar çağırış heç nəyi ikiləşdirmir
        $codes = \App\Models\WheelSpin::where('game', 'nard')->count();
        NardLeaderboard::finalizePastDays();
        $this->artisan('nard:finalize-day')->assertSuccessful();
        $this->assertSame($codes, \App\Models\WheelSpin::where('game', 'nard')->count());
        $this->assertSame(3, \App\Models\NardDailyWinner::count());
    }

    public function test_frequent_state_polling_does_not_block_other_requests(): void
    {
        [$a, $b] = [$this->player(), $this->player()];
        $match = $this->startMatch($a, $b);
        // oyun ~1.5 saniyədən bir soruşur — dəqiqədə ~40; bu, dəvət yoxlamasını və onlayn siqnalı bağlamamalıdır
        for ($i = 0; $i < 60; $i++) {
            $this->actingAs($a)->getJson("/oyunlar/nerd/oyun/{$match->id}/veziyyet")->assertOk();
        }
        $this->actingAs($a)->getJson('/oyunlar/nerd/devetler')->assertOk();
        $this->actingAs($a)->postJson('/aktiv')->assertNoContent();
    }

    public function test_guests_and_unverified_cannot_use_online_nard(): void
    {
        $this->getJson('/oyunlar/nerd/devetler')->assertUnauthorized();
        $u = User::create(['name' => 'Yeni', 'email' => 'u' . uniqid() . '@example.com', 'phone' => '+994551112233', 'password' => 'secret123']);
        $this->actingAs($u)->getJson('/oyunlar/nerd/devetler')->assertForbidden();
    }
}
