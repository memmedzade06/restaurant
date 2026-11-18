<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WheelSpin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Hədiyyə qazanan istifadəçiyə Telegram botu ilə təbrik və kupon kodu göndərilir.
 */
class PrizeNotificationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.telegram.bot_token' => 'TEST', 'services.telegram.bot_username' => 'QepikTestBot']);
        $this->app->forgetInstance(\App\Services\TelegramBot::class);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    }

    private function user(?string $chatId = '9001'): User
    {
        $u = User::create(['name' => 'Qalib Test', 'phone' => '+99455' . random_int(1000000, 9999999), 'password' => 'secret123']);
        $u->forceFill(['phone_verified_at' => now(), 'telegram_chat_id' => $chatId, 'points' => 50])->save();

        return $u;
    }

    private function sentTo(string $chatId): array
    {
        return Http::recorded(fn ($r) => str_contains($r->url(), 'sendMessage') && (string) ($r['chat_id'] ?? '') === $chatId)
            ->map(fn ($pair) => $pair[0]['text'])->values()->all();
    }

    public function test_wheel_winner_gets_telegram_message_with_code(): void
    {
        config(['games.wheel.segments' => [
            ['key' => 'discount_10', 'label' => '10% endirim', 'color' => '#000', 'size' => 360, 'prize' => true],
        ]]);
        $u = $this->user('9001');

        $this->actingAs($u)->postJson('/oyunlar/carx-oyunu/firlat')->assertOk();
        $this->actingAs($u)->postJson('/oyunlar/carx-oyunu/dayan', ['ms' => 0])->assertOk();
        // fırlatma özü mesaj göndərmir — yalnız "Hədiyyəni götür"
        $this->assertSame([], $this->sentTo('9001'));

        $code = $this->actingAs($u)->postJson('/oyunlar/carx-oyunu/gotur')->assertOk()->json('state.claimed.code');

        $texts = $this->sentTo('9001');
        $this->assertCount(1, $texts);
        $this->assertStringContainsString('Təbriklər', $texts[0]);
        $this->assertStringContainsString('Çarx oyununda hədiyyə qazandınız: <b>10% endirim</b>', $texts[0]);
        $this->assertStringContainsString("<code>{$code}</code>", $texts[0]);
        $this->assertStringContainsString('tarixinədək etibarlıdır', $texts[0]);
    }

    public function test_market_purchase_sends_coupon(): void
    {
        config(['market.items' => [['key' => 'market_tea', 'label' => 'Çaynik çay', 'cost' => 5, 'icon' => 'fa-mug-hot', 'desc' => 'Çay']]]);
        $u = $this->user('9002');

        $this->actingAs($u)->post('/hediyye-marketi/market_tea')->assertSessionHas('market_bought');
        $code = WheelSpin::where('user_id', $u->id)->value('code');

        $texts = $this->sentTo('9002');
        $this->assertCount(1, $texts);
        $this->assertStringContainsString('Hədiyyəniz hazırdır', $texts[0]);
        $this->assertStringContainsString('<b>Çaynik çay</b>', $texts[0]);
        $this->assertStringContainsString($code, $texts[0]);
    }

    public function test_no_message_without_telegram_or_bot(): void
    {
        config(['market.items' => [['key' => 'market_tea', 'label' => 'Çaynik çay', 'cost' => 1, 'icon' => 'fa-mug-hot', 'desc' => 'Çay']]]);

        // botla söhbəti olmayan istifadəçi (məs. admin) — mesaj yoxdur, alış yenə işləyir
        $u = $this->user(null);
        $this->actingAs($u)->post('/hediyye-marketi/market_tea')->assertSessionHas('market_bought');
        Http::assertNothingSent();

        // bot qoşulmayıb — mesaj yoxdur
        config(['services.telegram.bot_token' => null]);
        $this->app->forgetInstance(\App\Services\TelegramBot::class);
        $v = $this->user('9003');
        $this->actingAs($v)->post('/hediyye-marketi/market_tea')->assertSessionHas('market_bought');
        Http::assertNothingSent();
    }

    public function test_telegram_failure_does_not_break_the_prize(): void
    {
        Http::fake(['api.telegram.org/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout')]);
        config(['market.items' => [['key' => 'market_tea', 'label' => 'Çaynik çay', 'cost' => 5, 'icon' => 'fa-mug-hot', 'desc' => 'Çay']]]);
        $u = $this->user('9004');

        $this->actingAs($u)->post('/hediyye-marketi/market_tea')->assertSessionHas('market_bought');
        $this->assertSame(1, WheelSpin::where('user_id', $u->id)->count());
        $this->assertEquals(45, $u->fresh()->points);
    }
}
