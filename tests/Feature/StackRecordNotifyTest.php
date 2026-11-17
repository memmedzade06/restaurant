<?php

namespace Tests\Feature;

use App\Models\GameResult;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Stəkan qülləsi: birinci yer əldən gedəndə köhnə liderə Telegram mesajı.
 * Telegram API saxtalaşdırılır (Http::fake).
 */
class StackRecordNotifyTest extends TestCase
{
    use DatabaseTransactions;

    /** Bazadakı mövcud ən yüksək nəticə — testdəki xallar bunun üstünə gəlinir */
    private int $b = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->b = (int) GameResult::where('game', 'stack')->max('points');

        config(['services.telegram.bot_token' => 'TEST', 'games.stack.prizes_per_day' => 1]);
        $this->app->forgetInstance(\App\Services\TelegramBot::class);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    }

    private function player(string $name, ?string $chatId): User
    {
        $u = User::create([
            'name' => $name,
            'email' => 'n' . uniqid() . '@example.com',
            'phone' => '+99455' . random_int(1000000, 9999999),
            'password' => 'secret123',
        ]);
        $u->forceFill(['phone_verified_at' => now(), 'telegram_chat_id' => $chatId])->save();

        return $u;
    }

    private function log(int $n): array
    {
        $offsets = [3.2, -4.1, 1.7, -2.6, 5.3, -0.9];
        $log = [];
        for ($i = 0, $t = 0; $i < $n; $i++) {
            $t += $i % 2 ? 1000 : 600;
            $log[] = [$t, $offsets[$i % 6]];
        }

        return $log;
    }

    /** $score — mövcud ən yüksək nəticənin üstünə neçə xal */
    private function play(User $u, int $score, string $what = 'netice')
    {
        $score += $this->b;

        return $this->actingAs($u)->withSession(['stack_started_at' => microtime(true) - ($score * 0.8 + 5)])
            ->postJson('/oyunlar/stekan/' . $what, ['score' => $score, 'log' => $this->log($score)]);
    }

    /** Bu söhbətə göndərilmiş "Rekordunuz qırıldı" mesajları */
    private function alerts(string $chatId): array
    {
        return Http::recorded(fn (HttpRequest $r) => str_contains($r->url(), 'sendMessage')
            && (string) $r['chat_id'] === $chatId && str_contains($r['text'], 'Rekordunuz qırıldı'))->values()->all();
    }

    private function sentCount(): int
    {
        return Http::recorded(fn (HttpRequest $r) => str_contains($r->url(), 'sendMessage'))->count();
    }

    public function test_old_leader_is_told_who_beat_the_record(): void
    {
        $ali = $this->player('Əli Məmmədov', '111');
        $leyla = $this->player('Leyla Həsənova', '222');

        $this->play($ali, 30)->assertOk();
        $this->assertCount(0, $this->alerts('111'));

        // Leyla Əlini keçir — Əliyə mesaj gedir: kim, neçə xal
        $this->play($leyla, 40)->assertOk();
        $alerts = $this->alerts('111');
        $this->assertCount(1, $alerts);
        $text = $alerts[0][0]['text'];
        $this->assertStringContainsString($leyla->short_name, $text);
        $this->assertStringContainsString(($this->b + 40) . ' xal', $text);
        $this->assertStringContainsString('Sizin rekordunuz: ' . ($this->b + 30) . ' xal', $text);
        $this->assertStringNotContainsString('Qazandığı', $text); // hədiyyə götürməyib

        // Leyla öz rekordunu yeniləyir — Əli artıq birinci deyildi, təkrar mesaj yoxdur
        $this->play($leyla, 45)->assertOk();
        $this->assertCount(1, $this->alerts('111'));

        // Birinciliyə çatmayan nəticə — heç kimə mesaj yoxdur
        $this->play($ali, 44)->assertOk();
        $this->assertCount(0, $this->alerts('222'));

        // Əli birinciliyi geri alır — indi Leylaya mesaj gedir
        $this->play($ali, 46)->assertOk();
        $this->assertCount(1, $this->alerts('222'));
    }

    public function test_message_says_what_the_new_leader_won(): void
    {
        $ali = $this->player('Əli Məmmədov', '111');
        $leyla = $this->player('Leyla Həsənova', null);

        $this->play($ali, 30)->assertOk();
        $label = $this->play($leyla, 50, 'hediyye')->assertOk()->json('prize.label');
        $this->assertNotEmpty($label);

        $alerts = $this->alerts('111');
        $this->assertCount(1, $alerts);
        $this->assertStringContainsString(($this->b + 50) . ' xal', $alerts[0][0]['text']);
        $this->assertStringContainsString('Qazandığı: <b>' . $label . '</b>', $alerts[0][0]['text']);
    }

    public function test_nothing_is_sent_without_telegram_or_bot(): void
    {
        // Köhnə liderin Telegram-ı bağlı deyil
        $ali = $this->player('Əli Məmmədov', null);
        $leyla = $this->player('Leyla Həsənova', '222');
        $this->play($ali, 30)->assertOk(); // (bazada başqa lider varsa, ona mesaj gedə bilər — bu, düzgündür)
        $before = $this->sentCount();
        $this->play($leyla, 40)->assertOk();
        $this->assertSame($before, $this->sentCount());

        // Bot qoşulmayıb: Leylanın Telegram-ı olsa da, heç nə göndərilmir
        config(['services.telegram.bot_token' => null]);
        $this->app->forgetInstance(\App\Services\TelegramBot::class);
        $this->play($ali, 50)->assertOk();
        $this->assertSame($before, $this->sentCount());
    }
}
