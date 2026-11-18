<?php

namespace Tests\Feature;

use App\Models\GameResult;
use App\Models\User;
use App\Models\WheelSpin;
use App\Services\BakuWeather;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GamesTest extends TestCase
{
    use DatabaseTransactions;

    private function user(bool $verified = true): User
    {
        $u = User::create([
            'name' => 'Oyunçu Test',
            'email' => 'g' . uniqid() . '@example.com',
            'phone' => '+99455' . random_int(1000000, 9999999),
            'password' => 'secret123',
        ]);
        if ($verified) {
            $u->forceFill(['phone_verified_at' => now()])->save();
        }

        return $u;
    }

    public function test_pages_open_for_everyone(): void
    {
        $this->get('/oyunlar')->assertOk()->assertSee('Uzun nərd')->assertSee('Çarx oyunu');
        $this->get('/oyunlar/nerd')->assertOk()->assertSee('data-can-save="0"', false);
        $this->get('/oyunlar/carx-oyunu')->assertOk()->assertSee('<b>sınaq</b>', false);
        // köhnə ünvan (QR kodlar, köhnə linklər) yeni səhifəyə yönləndirilir
        $this->get('/oyunlar/bext-carxi')->assertStatus(301)->assertRedirect('/oyunlar/carx-oyunu');
        // Hədiyyə Kranı müvəqqəti bağlıdır: kartı yoxdur, səhifəsi açılmır
        $this->get('/oyunlar')->assertDontSee(route('games.claw'));
        $this->get('/oyunlar/hediyye-krani')->assertNotFound();
        // çarxın qaydalarında da kran xatırlanmır; kran açılanda qayda geri qayıdır
        $this->get('/oyunlar/carx-oyunu')->assertDontSee('Hədiyyə Kranında')->assertSee('çarxı əyləncə üçün');
        config(['games.claw.enabled' => true]);
        $this->get('/oyunlar/carx-oyunu')->assertSee('Çarx oyununda və ya Hədiyyə Kranında');
    }

    public function test_each_game_opens_with_rules_popup(): void
    {
        // oyuna girəndə əvvəlcə qaydalar pəncərəsi açılır (js/games/rules.js qaydaları bu blokdan götürür)
        foreach (['/oyunlar/stekan' => 'Stəkan qülləsi', '/oyunlar/carx-oyunu' => 'Çarx oyunu',
                  '/oyunlar/nerd' => 'Uzun nərd', '/oyunlar/krossvord' => 'Krossvord'] as $url => $name) {
            $this->get($url)->assertOk()
                ->assertSee('data-game-rules="' . $name . '"', false)
                ->assertSee('js/games/rules.js', false);
        }
        // oyunlar siyahısında və marketdə pəncərə yoxdur
        $this->get('/oyunlar')->assertOk()->assertDontSee('data-game-rules', false);
        $this->get('/hediyye-marketi')->assertOk()->assertDontSee('data-game-rules', false);
    }

    public function test_wheel_fifty_percent_is_the_narrowest_sector(): void
    {
        $segments = collect(config('games.wheel.segments'));
        $fifty = $segments->firstWhere('key', 'discount_50');

        $this->assertNotNull($fifty);
        $this->assertTrue($fifty['prize']);
        // məharət oyunu: ən böyük hədiyyə ən dar sektordur, sektorların cəmi 360°
        $this->assertSame($fifty['size'], $segments->min('size'));
        $this->assertEquals(360, $segments->sum('size'));
        // fərqli hədiyyələrin rəngləri fərqlidir (eyni nəticə — eyni rəng)
        $colors = $segments->groupBy('label')->map(fn ($g) => $g->pluck('color')->unique());
        $this->assertTrue($colors->every(fn ($c) => $c->count() === 1));
        $this->assertSame($colors->count(), $colors->flatten()->unique()->count());
        // qəlyan da dar sektordur (25%-dən dar)
        $this->assertLessThan($segments->firstWhere('key', 'discount_25')['size'], $segments->firstWhere('key', 'free_hookah')['size']);
        // sektorların mərkəzləri (dərəcə)
        $centers = [];
        $edge = -$segments[0]['size'] / 2;
        foreach ($segments as $i => $s) {
            $centers[$i] = $edge + $s['size'] / 2;
            $edge += $s['size'];
        }
        // üç "Şansını bir daha sına" sektoru var
        $this->assertCount(3, $segments->where('prize', false));
        // iki "Çaynik çay" sektoru düz üzbəüzdür: mərkəzləri arasında 180°
        $tea = $segments->where('label', 'Çaynik çay Hədiyyə')->keys();
        $this->assertCount(2, $tea);
        $this->assertEquals(180, $centers[$tea[1]] - $centers[$tea[0]]);
        // 50% və qəlyan boz sektorun yanındadır
        $n = $segments->count();
        foreach (['discount_50', 'free_hookah'] as $key) {
            $i = $segments->search(fn ($s) => $s['key'] === $key);
            $this->assertTrue(! $segments[($i + 1) % $n]['prize'] || ! $segments[($i - 1 + $n) % $n]['prize'], $key);
        }
        $this->get('/oyunlar/carx-oyunu')->assertOk()->assertSee('50% endirim')->assertSee('/oyunlar/carx-oyunu/dayan');
    }

    public function test_guest_spin_is_demo_and_not_saved(): void
    {
        $before = WheelSpin::count();
        $this->postJson('/oyunlar/carx-oyunu/firlat')->assertOk()->assertJson(['demo' => true])
            ->assertJsonStructure(['run' => ['a0', 'dir', 'v', 'k', 'period', 'phase', 'max_ms']]);
        $this->postJson('/oyunlar/carx-oyunu/dayan', ['ms' => 0])->assertOk()->assertJson(['demo' => true]);
        $this->assertSame($before, WheelSpin::count());
    }

    /** Nəticəni proqnozlaşdırmaq üçün çarxda yalnız bir növ sektor qoyuruq */
    private function onlySegment(bool $prize): void
    {
        config(['games.wheel.attempts_per_day' => 3, 'games.wheel.segments' => [
            $prize
                ? ['key' => 'discount_10', 'label' => '10% endirim', 'color' => '#000', 'size' => 360, 'prize' => true]
                : ['key' => 'try_again', 'label' => 'Şansını bir daha sına', 'color' => '#000', 'size' => 360, 'prize' => false],
        ]]);
    }

    /** "Fırlat", sonra "Dayan" — dayan cavabını qaytarır */
    private function play(User $u, int $ms = 0)
    {
        $this->actingAs($u)->postJson('/oyunlar/carx-oyunu/firlat')->assertOk();

        return $this->actingAs($u)->postJson('/oyunlar/carx-oyunu/dayan', ['ms' => $ms]);
    }

    public function test_three_attempts_then_blocked(): void
    {
        $this->onlySegment(false);
        $u = $this->user();

        foreach ([2, 1, 0] as $left) {
            $this->play($u)->assertOk()
                ->assertJson(['demo' => false, 'prize' => false, 'state' => ['attemptsLeft' => $left]]);
        }
        // cəhdlər hədiyyəsiz bitdi — əyləncə üçün limitsiz fırlatmaq olar, hədiyyə verilmir
        $this->actingAs($u)->postJson('/oyunlar/carx-oyunu/firlat')->assertOk()
            ->assertJson(['fun' => true, 'state' => ['attemptsLeft' => 0, 'fun' => true, 'funReason' => 'attempts']]);
        $this->actingAs($u)->postJson('/oyunlar/carx-oyunu/dayan', ['ms' => 0])->assertOk()->assertJson(['fun' => true]);
        $this->actingAs($u)->postJson('/oyunlar/carx-oyunu/gotur')->assertStatus(422);
        // fırlatmalar bazaya yazılmır — yalnız götürülən hədiyyə yazılır
        $this->assertSame(0, WheelSpin::where('user_id', $u->id)->count());
    }

    public function test_attempt_counts_when_spin_starts(): void
    {
        $this->onlySegment(true);
        $u = $this->user();

        // fırlatmanı başladıb dayandırmadan yenisini başlatmaq — əvvəlki cəhd yanır
        $this->actingAs($u)->postJson('/oyunlar/carx-oyunu/firlat')->assertOk()->assertJson(['state' => ['attemptsLeft' => 2]]);
        $this->actingAs($u)->postJson('/oyunlar/carx-oyunu/firlat')->assertOk()->assertJson(['state' => ['attemptsLeft' => 1]]);
        // son cəhd fırlanarkən əyləncə rejiminə keçmir — nəticə gözlənilir
        $this->actingAs($u)->postJson('/oyunlar/carx-oyunu/firlat')->assertOk()->assertJson(['state' => ['attemptsLeft' => 0, 'fun' => false]]);
        $this->actingAs($u)->postJson('/oyunlar/carx-oyunu/dayan', ['ms' => 0])->assertOk()
            ->assertJson(['prize' => true, 'state' => ['last' => ['label' => '10% endirim', 'prize' => true]]]);
        // dayan iki dəfə işləmir
        $this->actingAs($u)->postJson('/oyunlar/carx-oyunu/dayan', ['ms' => 0])->assertStatus(422);
    }

    public function test_result_depends_on_stop_time(): void
    {
        config(['games.wheel.attempts_per_day' => 9, 'games.wheel.segments' => [
            ['key' => 'try_again', 'label' => 'Boş', 'color' => '#000', 'size' => 180, 'prize' => false],
            ['key' => 'discount_10', 'label' => '10% endirim', 'color' => '#000', 'size' => 180, 'prize' => true],
        ]]);
        $u = $this->user();
        // sabit sürətli (90°/san) fırlanma, 10 saniyə əvvəl başlayıb
        $fixed = fn () => session()->put('wheel_run', [
            'a0' => 0, 'dir' => 1, 'v' => 90, 'k' => 0, 'period' => 2, 'phase' => 0, 'max_ms' => 15000,
            'started' => microtime(true) - 10, 'mode' => 'real', 'user_id' => $u->id, 'day' => now('Asia/Baku')->toDateString(),
        ]);

        // 0.5 san → 45° döndü: göstəricinin altında hələ 0-cı sektor (boş)
        $this->actingAs($u)->postJson('/oyunlar/carx-oyunu/firlat')->assertOk();
        $fixed();
        $this->actingAs($u)->postJson('/oyunlar/carx-oyunu/dayan', ['ms' => 500])->assertOk()
            ->assertJson(['index' => 0, 'prize' => false, 'angle' => 45]);

        // 1.5 san → 135°: 1-ci sektor (10% endirim)
        $this->actingAs($u)->postJson('/oyunlar/carx-oyunu/firlat')->assertOk();
        $fixed();
        $this->actingAs($u)->postJson('/oyunlar/carx-oyunu/dayan', ['ms' => 1500])->assertOk()
            ->assertJson(['index' => 1, 'prize' => true, 'state' => ['last' => ['label' => '10% endirim']]]);
    }

    public function test_wheel_reverses_direction(): void
    {
        // hər fırlatmada dönüş anları serverdən gəlir
        $run = $this->postJson('/oyunlar/carx-oyunu/firlat')->assertOk()->json('run');
        $this->assertNotEmpty($run['flips']);
        $this->assertGreaterThan(0, $run['flip_ms']);

        // 90°/san, 1-ci saniyədə dönüş: 0.9 san irəli, hamar dönüş, 0.9 san geri — başlanğıca qayıdır
        $r = ['a0' => 10, 'dir' => 1, 'v' => 90, 'k' => 0, 'period' => 2, 'phase' => 0, 'flips' => [1000], 'flip_ms' => 200];
        $c = \App\Http\Controllers\Frontend\GameController::class;
        $this->assertEqualsWithDelta(10 + 81, $c::runAngle($r, 900), 0.01);           // dönüşə qədər
        $this->assertEqualsWithDelta(10 + 81 + 18 / M_PI, $c::runAngle($r, 1000), 0.01); // dönüş anında dayanıb
        $this->assertEqualsWithDelta(0, $c::runSpeed($r, 1000), 1e-9);
        $this->assertEqualsWithDelta(-90, $c::runSpeed($r, 1500), 1e-9);              // əks tərəfə
        $this->assertEqualsWithDelta(10, $c::runAngle($r, 2000), 0.01);
    }

    public function test_wheel_not_stopped_in_time_gives_no_prize(): void
    {
        $this->onlySegment(true); // hər yer hədiyyədir — amma vaxt bitəndə yenə də verilmir
        $u = $this->user();
        $this->actingAs($u)->postJson('/oyunlar/carx-oyunu/firlat')->assertOk();
        $run = session('wheel_run');
        session()->put('wheel_run', ['started' => microtime(true) - 10] + $run); // vaxt keçib

        $this->actingAs($u)->postJson('/oyunlar/carx-oyunu/dayan', ['ms' => $run['max_ms']])->assertOk()
            ->assertJson(['timeout' => true, 'prize' => false, 'label' => 'Vaxt bitdi',
                'state' => ['attemptsLeft' => 2, 'last' => ['label' => 'Vaxt bitdi', 'prize' => false]]]);
        $this->actingAs($u)->postJson('/oyunlar/carx-oyunu/gotur')->assertStatus(422);

        // vaxt bitməmiş basılan "Dayan" isə hədiyyə verir
        $this->play($u)->assertOk()->assertJson(['timeout' => false, 'prize' => true]);
        $this->assertSame(5000, $run['max_ms']);
    }

    public function test_abandoned_last_spin_counts_as_failed(): void
    {
        $this->onlySegment(true);
        $u = $this->user();
        $this->play($u);
        $this->play($u);
        // son cəhd başladı, səhifə bağlandı — "Dayan" heç gəlmədi
        $this->actingAs($u)->postJson('/oyunlar/carx-oyunu/firlat')->assertOk()->assertJson(['state' => ['fun' => false]]);
        $this->travel(30)->seconds();
        $this->actingAs($u)->get('/oyunlar/carx-oyunu')->assertSee('"attemptsLeft":0', false)->assertSee('"funReason":"attempts"', false);
    }

    public function test_stop_time_cannot_be_in_the_future(): void
    {
        $this->onlySegment(true);
        $u = $this->user();
        $this->actingAs($u)->postJson('/oyunlar/carx-oyunu/firlat')->assertOk();
        // yenicə başlayıb — "5 saniyə sonra basdım" demək olmaz
        $this->actingAs($u)->postJson('/oyunlar/carx-oyunu/dayan', ['ms' => 5000])->assertStatus(422);
        // cəhd yanıb, nəticə yoxdur
        $this->actingAs($u)->postJson('/oyunlar/carx-oyunu/gotur')->assertStatus(422);
        $this->actingAs($u)->get('/oyunlar/carx-oyunu')->assertSee('"attemptsLeft":2', false);
        $this->actingAs($u)->postJson('/oyunlar/carx-oyunu/dayan', ['ms' => 0])->assertStatus(422); // fırlanma yoxdur
    }

    public function test_daily_cap_closes_sector_for_everyone(): void
    {
        $this->onlySegment(true);
        config(['games.wheel.daily_caps' => ['discount_10' => 1]]);
        $a = $this->user();
        $this->play($a)->assertOk()->assertJson(['prize' => true, 'closed' => false]);
        $this->actingAs($a)->postJson('/oyunlar/carx-oyunu/gotur')->assertOk();

        // limit doldu: başqa oyunçu eyni sektora düşür, amma hədiyyə almır
        $b = $this->user();
        $this->actingAs($b)->get('/oyunlar/carx-oyunu')->assertSee('data-closed=\'["discount_10"]\'', false);
        $this->play($b)->assertOk()->assertJson(['prize' => false, 'closed' => true, 'state' => ['last' => ['prize' => false]]]);
        $this->actingAs($b)->postJson('/oyunlar/carx-oyunu/gotur')->assertStatus(422);

        // kupon istifadə olunub silinsə də, sektor gün sonunadək bağlı qalır
        WheelSpin::where('user_id', $a->id)->delete();
        $this->play($b)->assertOk()->assertJson(['closed' => true]);
    }

    public function test_code_is_created_only_when_prize_is_claimed(): void
    {
        $this->onlySegment(true);
        $u = $this->user();

        // fırlatma özü kod yaratmır
        $this->play($u)->assertOk()
            ->assertJson(['prize' => true, 'state' => ['attemptsLeft' => 2, 'last' => ['label' => '10% endirim'], 'claimed' => null]]);
        $this->assertSame(0, WheelSpin::where('user_id', $u->id)->whereNotNull('code')->count());

        // oyunçu yenidən fırlatmağı seçir — əvvəlki nəticə itir, hələ də kod yoxdur
        $this->play($u)->assertOk()->assertJson(['state' => ['attemptsLeft' => 1]]);

        // "Hədiyyəni götür" — kod yaranır, raund bağlanır
        $r = $this->actingAs($u)->postJson('/oyunlar/carx-oyunu/gotur')->assertOk();
        $this->assertMatchesRegularExpression('/^QP-[A-Z0-9]{6}$/', $r->json('state.claimed.code'));
        $this->assertSame(now('Asia/Baku')->format('d.m.Y H:i'), $r->json('state.claimed.claimed_at')); // götürülmə vaxtı
        $this->assertSame(0, $r->json('state.attemptsLeft'));
        $this->assertSame(1, WheelSpin::where('user_id', $u->id)->count()); // 2 fırlatma, bazada 1 sətir
        $this->assertNotNull(WheelSpin::where('user_id', $u->id)->first()->code);

        // çarx masada oynanılır — kod yalnız bu ziyarət üçün (10 dəqiqə) etibarlıdır
        $spin = WheelSpin::where('user_id', $u->id)->first();
        $this->assertEqualsWithDelta(now()->addMinutes(10)->timestamp, $spin->expires_at->timestamp, 60);

        // götürəndən sonra yalnız əyləncə üçün fırlatmaq olar (yeni hədiyyə yoxdur), ikinci "götür" eyni kodu qaytarır
        $this->actingAs($u)->postJson('/oyunlar/carx-oyunu/firlat')->assertOk()->assertJson(['fun' => true, 'state' => ['attemptsLeft' => 0]]);
        $this->actingAs($u)->postJson('/oyunlar/carx-oyunu/dayan', ['ms' => 0])->assertOk()->assertJson(['fun' => true]);
        $this->assertSame(1, WheelSpin::where('user_id', $u->id)->count());
        $this->actingAs($u)->postJson('/oyunlar/carx-oyunu/gotur')->assertOk()->assertJson(['state' => ['claimed' => ['code' => $r->json('state.claimed.code')]]]);

        $this->actingAs($u)->get('/hesabim')->assertSee($r->json('state.claimed.code'));
    }

    public function test_expired_wheel_code_is_deleted_but_day_stays_closed(): void
    {
        $this->onlySegment(true);
        $u = $this->user();
        $this->play($u)->assertOk();
        $code =$this->actingAs($u)->postJson('/oyunlar/carx-oyunu/gotur')->assertOk()->json('state.claimed.code');

        // 11 dəqiqə sonra kodun vaxtı bitib və bazadan silinir
        $this->travel(11)->minutes();
        $this->artisan('prizes:prune')->assertSuccessful();
        $this->assertNull(WheelSpin::where('code', $code)->first());

        // amma bu gün yeni hədiyyə olmur (yalnız əyləncə) — "götürülüb" qeydi gün sonunadək qalır
        $this->actingAs($u)->postJson('/oyunlar/carx-oyunu/firlat')->assertOk()
            ->assertJson(['fun' => true, 'state' => ['attemptsLeft' => 0, 'claimed' => ['code' => $code, 'expired' => true]]]);
        $this->actingAs($u)->postJson('/oyunlar/carx-oyunu/gotur')->assertOk()->assertJson(['state' => ['claimed' => ['expired' => true]]]);
        $this->assertSame(0, WheelSpin::where('user_id', $u->id)->count()); // yeni kod yaranmayıb
    }

    public function test_prune_deletes_only_expired_unused_coupons(): void
    {
        $u = $this->user();
        $make = fn (string $key, $expires, $redeemed = null) => WheelSpin::create([
            'user_id' => $u->id, 'game' => 'wheel', 'prize_key' => $key, 'prize_label' => $key,
            'code' => WheelSpin::newCode(), 'spun_on' => now()->toDateString(),
            'expires_at' => $expires, 'redeemed_at' => $redeemed,
        ]);
        $expired = $make('expired', now()->subMinute());
        $active = $make('active', now()->addMinutes(5));
        $usedExpired = $make('used', now()->subDay(), now()->subDay()->addMinute());   // istifadə olunub, müddəti bitib — QALIR
        $usedActive = $make('used2', now()->addMinutes(5), now());                     // istifadə olunub — QALIR

        // yalnız istifadə olunmamış, vaxtı keçmiş kupon silinir; istifadə olunmuşlar admin üçün saxlanılır
        $this->assertSame(1, WheelSpin::pruneExpired());
        $this->assertNull($expired->fresh());
        $this->assertNotNull($usedExpired->fresh());
        $this->assertNotNull($usedActive->fresh());
        $this->assertNotNull($active->fresh());

        // sayt açılanda da silinir (cron olmasa belə), dəqiqədə ən çox bir dəfə
        $again = $make('expired2', now()->subMinute());
        \Illuminate\Support\Facades\Cache::forget('prizes:pruned');
        $this->get('/oyunlar')->assertOk();
        $this->assertNull($again->fresh());
    }

    public function test_cannot_claim_try_again_or_as_guest(): void
    {
        $this->postJson('/oyunlar/carx-oyunu/gotur')->assertUnauthorized();

        $this->onlySegment(false);
        $u = $this->user();
        $this->actingAs($u)->postJson('/oyunlar/carx-oyunu/gotur')->assertStatus(422); // heç fırlatmayıb
        $this->play($u)->assertOk();
        $this->actingAs($u)->postJson('/oyunlar/carx-oyunu/gotur')->assertStatus(422); // "Şansını bir daha sına"
    }

    public function test_stack_page_passes_baku_weather(): void
    {
        Cache::forget('baku_weather');
        Http::fake(['api.open-meteo.com/*' => Http::response(['current' => [
            'temperature_2m' => 11.6, 'weather_code' => 63, 'wind_speed_10m' => 38, 'wind_direction_10m' => 340, 'is_day' => 1,
        ]])]);

        // səhifə xidməti gözləmir — hava ayrıca sorğu ilə gəlir
        $this->get('/oyunlar/stekan')->assertOk()->assertSee("data-weather='null'", false)->assertSee('/oyunlar/stekan/hava');
        Http::assertSentCount(0);

        $this->getJson('/oyunlar/stekan/hava')->assertOk()->assertJson(['weather' => [
            'kind' => 'rain', 'windy' => true, 'label' => 'Yağışlı, küləkli', 'temp' => 12,
        ]]);

        // keşdən gəlir — ikinci dəfə xidmətə sorğu getmir
        $this->getJson('/oyunlar/stekan/hava')->assertOk()->assertJsonPath('weather.kind', 'rain');
        Http::assertSentCount(1);
    }

    public function test_stack_weather_fails_quietly_and_has_preview(): void
    {
        Cache::forget('baku_weather');
        Http::fake(['api.open-meteo.com/*' => Http::response('', 500)]);

        $this->getJson('/oyunlar/stekan/hava')->assertOk()->assertExactJson(['weather' => null]);
        $this->get('/oyunlar/stekan?hava=storm')->assertOk()->assertViewHas('weather', fn ($w) => $w['kind'] === 'storm');
        $this->get('/oyunlar/stekan?hava=clear-wind')->assertOk()
            ->assertViewHas('weather', fn ($w) => $w['label'] === 'Günəşli, küləkli' && $w['windy'] === true);
    }

    public function test_weather_codes_map_to_effects(): void
    {
        $this->assertSame('clear', BakuWeather::kindFromCode(0));
        $this->assertSame('clouds', BakuWeather::kindFromCode(3));
        $this->assertSame('fog', BakuWeather::kindFromCode(45));
        $this->assertSame('rain', BakuWeather::kindFromCode(61));
        $this->assertSame('rain', BakuWeather::kindFromCode(81));
        $this->assertSame('snow', BakuWeather::kindFromCode(73));
        $this->assertSame('storm', BakuWeather::kindFromCode(95));
    }

    /**
     * İnsan oyununa oxşayan gediş: stəkanlar arası 600/1000 ms növbələşir (orta 0.8 san),
     * sapmalar bir neçə piksel. [başlanğıcdan ms, sapma px]
     */
    private function stackLog(int $n): array
    {
        $offsets = [3.2, -4.1, 1.7, -2.6, 5.3, -0.9];
        $log = [];
        $t = 0;
        for ($i = 0; $i < $n; $i++) {
            $t += $i % 2 ? 1000 : 600;
            $log[] = [$t, $offsets[$i % 6]];
        }

        return $log;
    }

    /** Oyun n stəkana yetəcək qədər əvvəl başlayıb */
    private function playedFor(int $n): array
    {
        return ['stack_started_at' => microtime(true) - ($n * 0.8 + 5)];
    }

    private function stackPost(User $u, string $what, int $score, ?array $log = null, ?array $session = null)
    {
        return $this->actingAs($u)->withSession($session ?? $this->playedFor($score))
            ->postJson('/oyunlar/stekan/' . $what, ['score' => $score, 'log' => $log ?? $this->stackLog($score)]);
    }

    public function test_stack_game_saves_best_score_for_verified_only(): void
    {
        $this->get('/oyunlar/stekan')->assertOk()->assertSee('data-can-save="0"', false);
        $this->postJson('/oyunlar/stekan/netice', ['score' => 5])->assertUnauthorized();
        $this->actingAs($this->user(false))->postJson('/oyunlar/stekan/netice', ['score' => 5])->assertForbidden();

        $u = $this->user();
        $this->stackPost($u, 'netice', 12)->assertOk()->assertJson(['best' => 12, 'record' => true]);
        $this->stackPost($u, 'netice', 7)->assertOk()->assertJson(['best' => 12, 'record' => false]);
        $this->stackPost($u, 'netice', -1, [])->assertStatus(422);

        // liderlər cədvəli ən yüksək nəticəni göstərir (cəmi yox)
        $this->get('/oyunlar')->assertSee('Stəkan qülləsi liderləri')->assertSee('12 xal');
        $this->actingAs($u)->get('/oyunlar/stekan')->assertSee('data-best="12"', false);
        $this->actingAs($u)->get('/hesabim')->assertSee('Stəkan qülləsi');
    }

    public function test_unverified_user_gets_demo_spin(): void
    {
        $u = $this->user(false);
        $this->actingAs($u)->postJson('/oyunlar/carx-oyunu/firlat')->assertOk()->assertJson(['demo' => true]);
        $this->actingAs($u)->postJson('/oyunlar/carx-oyunu/dayan', ['ms' => 0])->assertOk()->assertJson(['demo' => true])->assertJsonMissingPath('state');
    }

    public function test_nard_vs_computer_gives_no_points(): void
    {
        $this->postJson('/oyunlar/nerd/netice', ['won' => true])->assertUnauthorized();
        $this->actingAs($this->user(false))->postJson('/oyunlar/nerd/netice', ['won' => true])->assertForbidden();

        // kompüterə qarşı qələbə xal vermir — xal yalnız dostla oyunda
        $u = $this->user();
        $this->actingAs($u)->postJson('/oyunlar/nerd/netice', ['won' => true, 'mars' => true])
            ->assertOk()->assertJson(['saved' => false, 'points' => 0]);
        $this->assertSame(0, GameResult::where('user_id', $u->id)->count());
        $this->actingAs($u)->get('/hesabim')->assertOk()->assertSee('Nərd (dostla oyun)')->assertSee('Hədiyyələrim');
    }

    public function test_games_page_lists_online_users(): void
    {
        $online = $this->user();
        $online->forceFill(['name' => 'Onlayn Oyunçu', 'last_seen_at' => now()->subSeconds(20)])->save();
        $stale = $this->user();
        $stale->forceFill(['name' => 'Köhnə Ziyarətçi', 'last_seen_at' => now()->subMinutes(3)])->save();
        $unverified = $this->user(false);
        $unverified->forceFill(['name' => 'Təsdiqsiz Adam', 'last_seen_at' => now()])->save();

        $this->get('/oyunlar')->assertOk()
            ->assertSee('İndi saytda')
            ->assertSee('Oyunçu O')
            ->assertDontSee('Ziyarətçi K')
            ->assertDontSee('Adam T');
    }

    public function test_visiting_updates_last_seen(): void
    {
        $u = $this->user();
        $this->assertNull($u->last_seen_at);

        $this->actingAs($u)->get('/oyunlar')->assertOk()->assertSee($u->short_name);
        $this->assertNotNull($u->fresh()->last_seen_at);
    }

    public function test_presence_ping_leave_and_logout(): void
    {
        $u = $this->user();
        $u->forceFill(['name' => 'Canlı Oyunçu'])->save();

        $this->actingAs($u)->postJson('/aktiv')->assertNoContent();
        $this->getJson('/oyunlar/onlayn')->assertOk()->assertJsonFragment(['name' => 'Oyunçu C', 'me' => true]);

        // Səhifə bağlandı — qısa müddətdən sonra siyahıdan düşür
        $this->actingAs($u)->postJson('/aktiv/cixis')->assertNoContent();
        $this->travel(15)->seconds();
        $this->assertFalse(User::online()->whereKey($u->id)->exists());
        $this->travelBack();

        // Çıxış edən dərhal düşür
        $this->actingAs($u)->postJson('/aktiv');
        $this->actingAs($u)->post('/cixis');
        $this->assertNull($u->fresh()->last_seen_at);
    }

    public function test_guest_cannot_ping(): void
    {
        $this->postJson('/aktiv')->assertUnauthorized();
    }

    public function test_stack_prize_requires_started_game_and_enough_time(): void
    {
        $u = $this->user();

        // Oyun başlamayıb
        $this->actingAs($u)->postJson('/oyunlar/stekan/hediyye', ['score' => 50, 'log' => $this->stackLog(50)])->assertStatus(422);

        // Çox tez — 50 xal üçün ən azı 50 stəkan × 0.8 san
        $this->actingAs($u)->postJson('/oyunlar/stekan/basla')->assertOk();
        $this->actingAs($u)->postJson('/oyunlar/stekan/hediyye', ['score' => 50, 'log' => $this->stackLog(50)])->assertStatus(422);

        // gedişat (log) olmadan qəbul olunmur
        $this->actingAs($u)->withSession($this->playedFor(50))->postJson('/oyunlar/stekan/hediyye', ['score' => 50])->assertStatus(422);
        $this->assertSame(0, WheelSpin::where('user_id', $u->id)->count());
    }

    public function test_stack_rejects_bot_like_games(): void
    {
        config(['games.stack.prizes_per_day' => 10]);
        $u = $this->user();
        $before = GameResult::count();

        // xal ilə stəkan sayı uyğun deyil
        $this->stackPost($u, 'hediyye', 50, $this->stackLog(30))->assertStatus(422);
        // stəkanlar insan üçün mümkün olmayan sürətlə qoyulub (hər 100 ms)
        $fast = array_map(fn ($i) => [($i + 1) * 100, 2.5], range(0, 49));
        $this->stackPost($u, 'hediyye', 50, $fast)->assertStatus(422);
        // maşın ritmi: hər stəkan düz 900 ms-dən bir
        $even = array_map(fn ($i) => [($i + 1) * 900, [2.1, -3.4, 1.2][$i % 3]], range(0, 49));
        $this->stackPost($u, 'hediyye', 50, $even, ['stack_started_at' => microtime(true) - 60])->assertStatus(422);
        // hər stəkan pikselinə qədər düz ortaya
        $exact = array_map(fn ($e) => [$e[0], 0.0], $this->stackLog(50));
        $this->stackPost($u, 'hediyye', 50, $exact)->assertStatus(422);
        // qülləni aşdıracaq sapma
        $bad = $this->stackLog(50);
        $bad[10][1] = 40;
        $this->stackPost($u, 'hediyye', 50, $bad)->assertStatus(422);
        // nəticə də yoxlanır: oyun başlamadan liderlər cədvəlinə xal yazmaq olmur
        $this->actingAs($u)->postJson('/oyunlar/stekan/netice', ['score' => 999, 'log' => $this->stackLog(999)])->assertStatus(422);

        $this->assertSame(0, WheelSpin::where('user_id', $u->id)->count());
        $this->assertSame($before, GameResult::count());

        // bir oyunda yalnız bir hədiyyə
        $this->stackPost($u, 'hediyye', 50)->assertOk();
        $this->actingAs($u)->postJson('/oyunlar/stekan/hediyye', ['score' => 60, 'log' => $this->stackLog(60)])->assertStatus(422);
        $this->assertSame(1, WheelSpin::where('user_id', $u->id)->count());
    }

    public function test_stack_test_hook_is_off_outside_local(): void
    {
        $this->get('/oyunlar/stekan')->assertOk()->assertSee('data-debug="0"', false);
    }

    public function test_stack_prize_gives_tea_then_daily_limit(): void
    {
        $u = $this->user();

        // 49 xal — hədiyyə yoxdur
        $this->stackPost($u, 'hediyye', 49)->assertStatus(422);

        $r = $this->stackPost($u, 'hediyye', 50)->assertOk()
            ->assertJsonPath('prize.label', 'Çaynik Çay Hədiyyə');
        $spin = WheelSpin::where('user_id', $u->id)->first();
        $this->assertSame('stack', $spin->game);
        $this->assertSame($r->json('prize.code'), $spin->code);
        $this->assertSame(50, (int) GameResult::where('user_id', $u->id)->where('game', 'stack')->max('points'));

        // Gündə bir hədiyyə (yeni oyunda)
        $this->stackPost($u, 'hediyye', 55, null, $this->playedFor(55) + ['stack_prize_claimed' => false])->assertStatus(429);

        // kod restoranda istifadə olunub bazadan silinsə də, bu gün yeni hədiyyə verilmir
        $spin->delete();
        $this->stackPost($u, 'hediyye', 55, null, $this->playedFor(55) + ['stack_prize_claimed' => false])->assertStatus(429);

        // Stəkan hədiyyəsi Çarx oyununu bloklamır
        $this->onlySegment(true);
        $this->actingAs($u)->postJson('/oyunlar/carx-oyunu/firlat')->assertOk()->assertJson(['demo' => false]);
    }

    public function test_stack_prize_sixty_points_is_hookah(): void
    {
        $u = $this->user();
        $this->stackPost($u, 'hediyye', 60)->assertOk()
            ->assertJsonPath('prize.label', 'Qəlyan Hədiyyə');
    }

    public function test_stack_prize_seventy_points_is_30_percent_off(): void
    {
        $u = $this->user();
        $this->stackPost($u, 'hediyye', 70)->assertOk()
            ->assertJsonPath('prize.label', 'Hesaba 30% endirim');
        $this->assertSame('stack_discount_30', WheelSpin::where('user_id', $u->id)->latest('id')->value('prize_key'));
    }

    public function test_stack_finish_at_hundred_gives_30_azn_coupon(): void
    {
        $u = $this->user();
        // oyun 130 stəkana yetəcək qədər əvvəl başlayıb, 100-də hədiyyə götürülür
        $this->stackPost($u, 'hediyye', 100, null, $this->playedFor(130))->assertOk()
            ->assertJsonPath('prize.label', '30 AZN kupon');
        $this->assertSame('stack_coupon_30', WheelSpin::where('user_id', $u->id)->latest('id')->value('prize_key'));

        // hədiyyədən sonra oyun davam edir — sonda daha yüksək nəticə rekord kimi yazılır
        $this->actingAs($u)->postJson('/oyunlar/stekan/netice', ['score' => 130, 'log' => $this->stackLog(130)])->assertOk()
            ->assertJson(['saved' => true, 'best' => 130, 'record' => true]);
    }

    public function test_stack_prize_needs_verified_user(): void
    {
        $this->postJson('/oyunlar/stekan/hediyye', ['score' => 8])->assertUnauthorized();
        $this->actingAs($this->user(false))->postJson('/oyunlar/stekan/hediyye', ['score' => 8])->assertForbidden();
    }
}
