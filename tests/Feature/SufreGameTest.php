<?php

namespace Tests\Feature;

use App\Models\GameResult;
use App\Models\PointLog;
use App\Models\User;
use App\Services\SufreGame;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Süfrəni doldur: hesabı server özü hesablayır, saxta gedişat rədd olunur, bonus gündə bir dəfə */
class SufreGameTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(bool $verified = true): User
    {
        $u = User::create([
            'name' => 'Şar Testi',
            'email' => 'bl' . uniqid() . '@example.com',
            'phone' => '+99451' . random_int(1000000, 9999999),
            'password' => 'secret123',
        ]);
        $u->forceFill(['phone_verified_at' => $verified ? now() : null, 'points' => 0])->save();

        return $u->fresh();
    }

    private function cfg(): array
    {
        return config('games.sufre');
    }

    /** Hər saniyədə bir əşya, hamısı düz 1 saniyəyə xəttə çatır (538 px / 538 px/s) */
    private function item(float $s, string $kind, float $x = 180): array
    {
        // adi nemət testlərdə qənddir (1 xal), şokolad isə 5-ci növdür
        return [$s, $kind, $x, (float) (SufreGame::CATCH_Y - SufreGame::START_Y), 0.0, $kind === 'c' ? 5 : 2];
    }

    /** Sinini əşyanın xəttə çatdığı andakı yerindən $off px kənarda tutur (insan heç vaxt düz ortaya tutmur) */
    private function at(array $item, float $off = 10): float
    {
        return round(SufreGame::itemX($item, SufreGame::crossTime($item)) + $off, 1);
    }

    public function test_server_counts_score_with_combo_and_ends_on_first_caught_bomb(): void
    {
        $items = [];
        $basket = [];
        $s = 1;
        // 9 şirniyyat ardıcıl: 4 × x1 + 4 × x2 + 1 × x3 = 15
        for ($i = 0; $i < 9; $i++, $s++) {
            $items[] = $it = $this->item($s, 'n');
            $basket[] = $this->at($it);
        }
        // bir şirniyyat yerə düşür (sini 100 px kənarda) — kombo sıfırlanır
        $items[] = $it = $this->item($s++, 'n', 300);
        $basket[] = $this->at($it, -100);
        // qızıl çaynik: 5 × x1
        $items[] = $it = $this->item($s++, 'g');
        $basket[] = $this->at($it);
        // yerə düşən kömür heç nə etmir
        $items[] = $it = $this->item($s++, 'b', 300);
        $basket[] = $this->at($it, -100);
        // kömür siniyə düşür — oyun bitir (bir can)
        $items[] = $it = $this->item($s++, 'b');
        $basket[] = $this->at($it, 0);
        // oyun bitəndən sonrakı əşya üçün sini yeri olmamalıdır
        $items[] = $this->item($s, 'n');

        $r = SufreGame::referee($items, $basket, 100, $this->cfg());
        $this->assertSame(['score' => 20, 'caught' => 10, 'bombs' => 1, 'seconds' => 14.0], $r);

        // sonrakı əşya üçün yer göndərilsə — rədd
        $r = SufreGame::referee($items, [...$basket, 180], 100, $this->cfg());
        $this->assertArrayHasKey('problem', $r);
    }

    public function test_cheating_patterns_are_rejected(): void
    {
        $items = [$this->item(1, 'n', 60), $this->item(1.05, 'n', 300), $this->item(3, 'n')];
        $ok = [$this->at($items[0]), $this->at($items[1]), $this->at($items[2])];

        // sini 50 ms-də 240 px "sıçrayır" — bot
        $this->assertSame('sini həddən artıq sürətlidir', SufreGame::referee($items, $ok, 100, $this->cfg())['problem']);

        $items[1] = $this->item(2, 'n', 300);
        $ok[1] = $this->at($items[1]);
        // vaxt bitməmiş əşya buraxılıb (oyunçu istədiyi nemətləri "unudub")
        $this->assertSame('əşya buraxılıb', SufreGame::referee($items, [$ok[0], null, $ok[2]], 100, $this->cfg())['problem']);
        // oyun 4 saniyə çəkib, amma serverdə cəmi 0,5 saniyə keçib — sürətləndirilmiş simulyasiya
        $this->assertSame('oyun vaxtı real vaxtdan uzundur', SufreGame::referee($items, $ok, 0.5, ['max_seconds' => 4] + $this->cfg())['problem']);
        // vaxt bitəndə əşyalar qurtarır: normal oyun
        $this->assertSame(['score' => 3, 'caught' => 3, 'bombs' => 0, 'seconds' => 4.0], SufreGame::referee($items, $ok, 100, ['max_seconds' => 4] + $this->cfg()));
    }

    public function test_each_sweet_has_its_own_points(): void
    {
        $sweet = fn (float $s, int $sub) => [$s, 'n', 180.0, (float) (SufreGame::CATCH_Y - SufreGame::START_Y), 0.0, $sub];
        $items = [$sweet(1, 0), $sweet(2, 1), $sweet(3, 2), $sweet(4, 3)]; // paxlava, şəkərbura, qənd, limon
        $basket = array_map(fn ($it) => $this->at($it), $items);
        $this->assertSame(2 + 2 + 1 + 1, SufreGame::referee($items, $basket, 100, ['max_seconds' => 6] + $this->cfg())['score']);
        $this->assertSame(1, SufreGame::value($sweet(1, 4))); // qoz
    }

    public function test_chocolate_gives_two_points_and_falls_as_often_as_other_sweets(): void
    {
        $c = $this->item(1, 'c');
        $this->assertSame(2, SufreGame::referee([$c], [$this->at($c)], 100, ['max_seconds' => 3] + $this->cfg())['score']);

        $count = ['c' => 0];
        $sweets = array_fill(0, 5, 0);
        foreach ([11, 22, 33, 44, 55, 66] as $seed) {
            foreach (SufreGame::items($seed, 600) as $it) {
                if ($it[1] === 'c') {
                    $count['c']++;
                    $this->assertSame(5, $it[5]);
                } elseif ($it[1] === 'n') {
                    $this->assertLessThan(5, $it[5]); // adi nemətlər arasında şokolad qalmayıb
                    $sweets[$it[5]]++;
                }
            }
        }
        $this->assertEqualsWithDelta(1.0, $count['c'] / (array_sum($sweets) / 5), 0.2); // hər adi nemət qədər
    }

    public function test_hookah_gives_ten_points_and_falls_as_often_as_the_golden_teapot(): void
    {
        $h = $this->item(1, 'h');
        $this->assertSame(10, SufreGame::referee([$h], [$this->at($h)], 100, ['max_seconds' => 3] + $this->cfg())['score']);

        $count = ['g' => 0, 'h' => 0];
        foreach ([11, 22, 33, 44, 55] as $seed) {
            foreach (SufreGame::items($seed, 600) as $it) {
                if (isset($count[$it[1]])) {
                    $count[$it[1]]++;
                }
            }
        }
        $this->assertGreaterThan(0, $count['h']);
        $this->assertEqualsWithDelta(1.0, $count['h'] / $count['g'], 0.2); // təxminən eyni sayda
    }

    public function test_tray_shrinks_as_the_game_goes_on(): void
    {
        $this->assertSame(48.0, (float) SufreGame::halfAt(10));   // əvvəlcə tam eni
        $this->assertSame(48.0, (float) SufreGame::halfAt(30));
        $this->assertSame(42.0, SufreGame::halfAt(60));             // 30 s-dən sonra saniyədə 0,2 px
        $this->assertSame(30.0, (float) SufreGame::halfAt(500));  // ən kiçiyi

        // eyni məsafə (40 px kənarda): əvvəldə tutulur, sonra sini kiçildiyi üçün yerə düşür
        $early = $this->item(5, 'n');
        $late = $this->item(150, 'n');   // xəttə 151-ci saniyədə çatır: sininin yarı eni 30
        $this->assertSame(1, SufreGame::referee([$early], [$this->at($early, 40)], 100, ['max_seconds' => 7] + $this->cfg())['caught']);
        $r = SufreGame::referee([$early, $late], [$this->at($early), $this->at($late, 40)], 1000, ['max_seconds' => 152] + $this->cfg());
        $this->assertSame(1, $r['caught']); // yalnız birincisi tutulub, gec gələn 40 px kənardan yerə düşüb
    }

    public function test_difficulty_rises_from_10_seconds_and_keeps_rising(): void
    {
        $this->assertEqualsWithDelta(0.0, SufreGame::level(10), 1e-9);   // ilk 10 saniyə asan
        $this->assertEqualsWithDelta(0.5, SufreGame::level(20), 1e-9);
        $this->assertEqualsWithDelta(1.0, SufreGame::level(30), 1e-9);
        $this->assertEqualsWithDelta(2.0, SufreGame::level(90), 1e-9);
        $this->assertEqualsWithDelta(SufreGame::MAX_LEVEL, SufreGame::level(1000), 1e-9);

        $items = SufreGame::items(4242, 600);
        $avg = fn ($from, $to, $col) => (fn ($l) => array_sum(array_column($l, $col)) / count($l))(array_filter($items, fn ($it) => $it[0] >= $from && $it[0] < $to));
        $this->assertGreaterThan($avg(0, 10, 3) + 60, $avg(30, 40, 3));   // 30 s-də ilk 10 saniyədəkindən xeyli sürətli
        $this->assertGreaterThan($avg(30, 40, 3) + 40, $avg(150, 200, 3)); // və artmağa davam edir
    }

    public function test_perfectly_centered_catches_look_like_a_bot(): void
    {
        $items = [];
        $basket = [];
        for ($i = 0; $i < 40; $i++) {
            $items[] = $it = $this->item($i + 1, 'n');
            $basket[] = $this->at($it, 0);
        }
        $this->assertSame('tutuşlar insan üçün həddən artıq dəqiqdir', SufreGame::referee($items, $basket, 1000, ['max_seconds' => 42] + $this->cfg())['problem']);
    }

    public function test_items_are_deterministic_and_get_harder(): void
    {
        $a = SufreGame::items(12345, 600);
        $this->assertSame($a, SufreGame::items(12345, 600));
        $this->assertNotSame($a, SufreGame::items(54321, 600));
        $early = array_filter($a, fn ($it) => $it[0] < 20);
        $late = array_filter($a, fn ($it) => $it[0] > 60 && $it[0] < 120);
        $share = fn ($list) => count(array_filter($list, fn ($it) => $it[1] === 'b')) / count($list);
        $this->assertGreaterThan($share($early), $share($late)); // sonra kömür çoxdur
        foreach ($a as $it) {
            $this->assertGreaterThanOrEqual(30, $it[2]);
            $this->assertLessThanOrEqual(SufreGame::WIDTH - 30, $it[2]);
        }
    }

    /** Toxumdan qurulan oyunu sabit sini ilə (ortada) oynayır: kömür siniyə düşənə qədər */
    private function playCentered(array $items): array
    {
        $order = array_keys($items);
        usort($order, fn ($x, $y) => SufreGame::crossTime($items[$x]) <=> SufreGame::crossTime($items[$y]));
        $basket = [];
        $bombs = 0;
        foreach ($order as $i) {
            $tc = SufreGame::crossTime($items[$i]);
            if ($tc > config('games.sufre.max_seconds')) {
                break; // vaxt bitdi
            }
            $basket[$i] = 187.3;
            if ($items[$i][1] === 'b' && abs(SufreGame::itemX($items[$i], $tc) - 187.3) <= SufreGame::halfAt($tc) && ++$bombs >= SufreGame::LIVES) {
                break;
            }
        }
        $out = array_fill(0, max(array_keys($basket)) + 1, null);
        foreach ($basket as $i => $x) {
            $out[$i] = $x;
        }

        return $out;
    }

    /**
     * Oyunu ortada dayanan sini ilə $minScore xala çatana qədər oynayır (kömür siniyə düşməmiş).
     * Qaytarır: [sini yerləri, dayanma anı, xal] və ya kömür əvvəl düşsə null.
     */
    private function playUntilScore(array $items, int $minScore): ?array
    {
        $order = array_keys($items);
        usort($order, fn ($x, $y) => SufreGame::crossTime($items[$x]) <=> SufreGame::crossTime($items[$y]));
        $basket = [];
        $score = $streak = 0;
        foreach ($order as $i) {
            $tc = SufreGame::crossTime($items[$i]);
            if ($tc > config('games.sufre.max_seconds')) {
                return null; // vaxt bitdi
            }
            $basket[$i] = 187.3;
            $hit = abs(SufreGame::itemX($items[$i], $tc) - 187.3) <= SufreGame::halfAt($tc);
            if ($items[$i][1] === 'b') {
                if ($hit) {
                    return null;
                }
                continue;
            }
            if (! $hit) {
                $streak = 0;
                continue;
            }
            $score += SufreGame::value($items[$i]) * min(SufreGame::MAX_COMBO, 1 + intdiv($streak++, SufreGame::COMBO_STEP));
            if ($score >= $minScore) {
                $out = array_fill(0, max(array_keys($basket)) + 1, null);
                foreach ($basket as $k => $x) {
                    $out[$k] = $x;
                }

                return [$out, $tc, $score];
            }
        }

        return null;
    }

    /** Yeni oyun başladır və $minScore xala çatan gedişat tapılana qədər təkrarlayır */
    private function startAndReach(int $minScore): array
    {
        for ($try = 0; $try < 40; $try++) {
            $items = $this->postJson('/oyunlar/sufreni-doldur/basla')->assertOk()->json('items');
            if ($run = $this->playUntilScore($items, $minScore)) {
                session()->put('sufre.started', microtime(true) - 10000); // oyun real vaxtda oynanıb kimi
                return $run;
            }
        }
        $this->fail('Xala çatan oyun tapılmadı');
    }

    public function test_referee_can_stop_the_game_midway(): void
    {
        $items = [$this->item(1, 'n'), $this->item(2, 'n'), $this->item(3, 'n')]; // xəttə 2, 3, 4-cü saniyədə çatır
        $two = [$this->at($items[0]), $this->at($items[1])];
        $r = SufreGame::referee($items, $two, 100, $this->cfg(), 3.5);
        $this->assertSame(2, $r['score']);
        $this->assertSame(3.5, $r['seconds']);
        // dayandırılan andan sonrakı əşya üçün sini yeri göndərilə bilməz
        $this->assertArrayHasKey('problem', SufreGame::referee($items, [...$two, $this->at($items[2])], 100, $this->cfg(), 3.5));
        // dayandırılan andan əvvəlki əşya buraxıla bilməz
        $this->assertArrayHasKey('problem', SufreGame::referee($items, [$this->at($items[0])], 100, $this->cfg(), 3.5));
    }

    public function test_prize_is_given_for_reached_score_once_per_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-26 12:00', 'Asia/Baku'));
        config()->set('games.sufre.prizes', [
            3 => ['key' => 'sufre_tea', 'label' => 'Çaynik Çay Hədiyyə'],
            6 => ['key' => 'sufre_hookah', 'label' => 'Qəlyan Hədiyyə'],
            200000 => ['key' => 'sufre_discount_30', 'label' => '30% endirim kuponu Hədiyyə'],
        ]);
        $u = $this->user();
        $this->actingAs($u);

        // xal hələ çatmayıb — hədiyyə yoxdur
        [$basket, $until, $score] = $this->startAndReach(1);
        if ($score < 3) {
            $this->postJson('/oyunlar/sufreni-doldur/hediyye', ['basket' => $basket, 'until' => $until])
                ->assertStatus(422)->assertJson(['message' => 'Bu xal hədiyyə üçün kifayət deyil.']);
        }

        // çatılan ən böyük hədiyyə verilir, oyun bitir
        [$basket, $until, $score] = $this->startAndReach(6);
        $res = $this->postJson('/oyunlar/sufreni-doldur/hediyye', ['basket' => $basket, 'until' => $until])->assertOk();
        $this->assertSame($score, $res->json('score'));
        $this->assertSame('Qəlyan Hədiyyə', $res->json('prize.label'));
        $spin = \App\Models\WheelSpin::where('user_id', $u->id)->where('game', 'sufre')->first();
        $this->assertSame('sufre_hookah', $spin->prize_key);
        $this->assertSame($res->json('prize.code'), $spin->code);
        $this->assertTrue((bool) GameResult::where('user_id', $u->id)->where('game', 'sufre')->value('won'));
        $this->assertNull(session('sufre')); // eyni oyunla ikinci dəfə olmaz
        $this->postJson('/oyunlar/sufreni-doldur/hediyye', ['basket' => $basket, 'until' => $until])->assertStatus(422);

        // gündə bir hədiyyə: yeni oyunda da xala çatsa, verilmir
        [$basket, $until] = $this->startAndReach(3);
        $this->postJson('/oyunlar/sufreni-doldur/hediyye', ['basket' => $basket, 'until' => $until])->assertStatus(429);
        $this->assertSame(1, \App\Models\WheelSpin::where('user_id', $u->id)->where('game', 'sufre')->count());

        // ertəsi gün yenə olar
        Carbon::setTestNow(Carbon::parse('2026-11-27 12:00', 'Asia/Baku'));
        [$basket, $until, $score] = $this->startAndReach(3);
        // bir tutuş xalı birdən artıra bilər (qəlyan 10 xal) — çatılan ən böyük hədiyyə verilir
        $this->postJson('/oyunlar/sufreni-doldur/hediyye', ['basket' => $basket, 'until' => $until])->assertOk()
            ->assertJson(['prize' => ['label' => $score >= 6 ? 'Qəlyan Hədiyyə' : 'Çaynik Çay Hədiyyə']]);

        // qonaq hədiyyə ala bilməz
        auth()->logout();
        $this->postJson('/oyunlar/sufreni-doldur/hediyye', ['basket' => [], 'until' => 1])->assertStatus(401);
    }

    public function test_prize_thresholds_are_shown_in_rules(): void
    {
        $this->get('/oyunlar/sufreni-doldur')->assertOk()
            ->assertSee('450 xal')->assertSee('Çaynik Çay Hədiyyə')
            ->assertSee('500 xal')->assertSee('Qəlyan Hədiyyə')
            ->assertSee('550 xal')->assertSee('30% endirim kuponu Hədiyyə');
    }

    public function test_full_game_over_http_saves_server_score_and_bonus_once_a_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-26 12:00', 'Asia/Baku'));
        config()->set('games.sufre.bonus.tiers', [1 => 0.3]);
        $u = $this->user();

        $this->get('/oyunlar/sufreni-doldur')->assertOk()->assertSee('Süfrəni doldur');
        $this->get('/oyunlar')->assertOk()->assertSee('Süfrəni doldur');

        // toxum təsadüfidir: bir canla ortada dayanan sini bəzən ilk nemətdən əvvəl kömür tutur (0 xal) —
        // xallı oyun düşənə qədər yeni oyun başladırıq
        for ($try = 0; $try < 30; $try++) {
            $items = $this->actingAs($u)->postJson('/oyunlar/sufreni-doldur/basla')->assertOk()->json('items');
            $this->assertEquals(SufreGame::items(session('sufre.seed'), config('games.sufre.max_seconds')), $items); // JSON-da 236.0 → 236
            $basket = $this->playCentered($items);
            $expected = SufreGame::referee($items, $basket, 10000, $this->cfg());
            if ($expected['score'] > 0) {
                break;
            }
        }
        $this->assertGreaterThan(0, $expected['score']);

        // oyun real vaxtda oynanıb kimi: başlanğıcı keçmişə çəkirik
        session()->put('sufre.started', microtime(true) - 10000);
        $this->postJson('/oyunlar/sufreni-doldur/netice', ['basket' => $basket])
            ->assertOk()->assertJson(['saved' => true, 'score' => $expected['score'], 'record' => true, 'bonus' => 0.3]);
        $this->assertSame($expected['score'], (int) GameResult::where('user_id', $u->id)->where('game', 'sufre')->value('points'));
        $this->assertEqualsWithDelta(0.3, $u->fresh()->points, 0.0001);
        $this->assertSame('sufre', PointLog::where('user_id', $u->id)->value('source'));

        // eyni nəticəni ikinci dəfə göndərmək olmur (oyun sessiyadan götürülüb)
        $this->postJson('/oyunlar/sufreni-doldur/netice', ['basket' => $basket])->assertStatus(422);

        // 48 saat keçməyib (ertəsi gün də): mərhələ bonusu yenidən verilmir
        $items = $this->postJson('/oyunlar/sufreni-doldur/basla')->json('items');
        session()->put('sufre.started', microtime(true) - 10000);
        $this->postJson('/oyunlar/sufreni-doldur/netice', ['basket' => $this->playCentered($items)])->assertOk()->assertJson(['bonus' => 0]);
        $this->assertEqualsWithDelta(0.3, $u->fresh()->points, 0.0001);
    }

    public function test_game_played_too_fast_is_rejected_and_guests_cannot_save(): void
    {
        $u = $this->user();
        $items = $this->actingAs($u)->postJson('/oyunlar/sufreni-doldur/basla')->json('items');
        // başlanğıcdan dərhal sonra uzun oyunun nəticəsi — server vaxtı uyğun gəlmir
        $this->postJson('/oyunlar/sufreni-doldur/netice', ['basket' => $this->playCentered($items)])->assertStatus(422);
        $this->assertSame(0, GameResult::where('user_id', $u->id)->count());

        auth()->logout();
        $this->postJson('/oyunlar/sufreni-doldur/netice', ['basket' => []])->assertStatus(401);
        // qonaq oynaya bilər (əşyalar gəlir), sadəcə nəticə yazılmır
        $this->postJson('/oyunlar/sufreni-doldur/basla')->assertOk()->assertJsonStructure(['items']);
    }

    public function test_game_lasts_90_seconds(): void
    {
        $this->assertSame(90, config('games.sufre.max_seconds'));
        $items = $this->actingAs($this->user())->postJson('/oyunlar/sufreni-doldur/basla')->json('items');
        $this->assertLessThan(90, max(array_column($items, 0))); // 90 saniyədən sonra əşya çıxmır

        // vaxt bitəndə: 90-cı saniyəyə qədər xəttə çatanlar sayılır, sonrakılar üçün sini yeri olmur
        $a = $this->item(80, 'n'); // xəttə 81-ci saniyədə çatır
        $b = $this->item(95, 'n'); // 96-cı saniyədə — vaxtdan sonra
        $r = SufreGame::referee([$a, $b], [$this->at($a)], 1000, $this->cfg());
        $this->assertSame(['score' => 1, 'caught' => 1, 'bombs' => 0, 'seconds' => 90.0], $r);
        $this->assertArrayHasKey('problem', SufreGame::referee([$a, $b], [$this->at($a), $this->at($b)], 1000, $this->cfg()));
    }

    public function test_record_and_rank_are_shown_on_game_page_profile_and_admin(): void
    {
        $a = $this->user();
        $b = $this->user();
        $me = $this->user();
        // reytinqdə yer — başqa testlərin nəticələrindən asılı olmasın deyə ən yüksək nəticələr
        GameResult::create(['user_id' => $a->id, 'game' => 'sufre', 'won' => false, 'points' => 60000]);
        GameResult::create(['user_id' => $b->id, 'game' => 'sufre', 'won' => false, 'points' => 59000]);
        GameResult::create(['user_id' => $me->id, 'game' => 'sufre', 'won' => false, 'points' => 59500]);
        GameResult::create(['user_id' => $me->id, 'game' => 'sufre', 'won' => false, 'points' => 100]); // ən yaxşısı sayılır

        $this->assertSame(1, \App\Http\Controllers\Frontend\SufreController::rank($a->id));
        $this->assertSame(2, \App\Http\Controllers\Frontend\SufreController::rank($me->id));
        $this->assertSame(3, \App\Http\Controllers\Frontend\SufreController::rank($b->id));
        $this->assertNull(\App\Http\Controllers\Frontend\SufreController::rank($this->user()->id)); // oynamayıb

        $this->actingAs($me)->get('/oyunlar/sufreni-doldur')->assertOk()->assertSee('Reytinqdə yeriniz: <b>2</b>', false);
        $this->actingAs($me)->get(route('account'))->assertOk()
            ->assertSee('Süfrəni doldur')->assertSee('59500')->assertSee('reytinqdə yeriniz');

        $admin = $this->user();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin)->get(route('admin.users', ['q' => $me->email]))->assertOk()
            ->assertSee('Süfrəni doldur rekordu');
    }

    public function test_disabled_game_is_hidden(): void
    {
        config()->set('games.sufre.enabled', false);
        $this->get('/oyunlar/sufreni-doldur')->assertNotFound();
        $this->get('/oyunlar')->assertOk()->assertDontSee('Süfrəni doldur');
    }
}
