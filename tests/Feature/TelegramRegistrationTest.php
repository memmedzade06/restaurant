<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Phone;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Qeydiyyat → Telegram bot ilə nömrə təsdiqi → hesab.
 * Telegram API saxtalaşdırılır (Http::fake), real bot lazım deyil.
 */
class TelegramRegistrationTest extends TestCase
{
    use DatabaseTransactions;

    private const SECRET = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.bot_token' => 'TEST',
            'services.telegram.bot_username' => 'QepikTestBot',
            'services.telegram.webhook_secret' => self::SECRET,
        ]);
        $this->app->forgetInstance(\App\Services\TelegramBot::class);

        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    }

    private function register(string $phone = '050 123 45 67'): User
    {
        $this->post('/qeydiyyat', [
            'name' => 'Test İstifadəçi',
            'phone' => $phone,
            'email' => 'test' . uniqid() . '@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertRedirect(route('verify.telegram'));

        return User::where('phone', Phone::normalize($phone))->firstOrFail();
    }

    private function webhook(array $message): void
    {
        $this->postJson('/api/telegram/webhook', ['update_id' => random_int(1, 1e6), 'message' => $message], [
            'X-Telegram-Bot-Api-Secret-Token' => self::SECRET,
        ])->assertOk();
    }

    private function msg(array $extra, int $tgUserId = 777): array
    {
        return ['message_id' => 1, 'from' => ['id' => $tgUserId], 'chat' => ['id' => $tgUserId, 'type' => 'private']] + $extra;
    }

    public function test_phone_is_normalized(): void
    {
        $this->assertSame('+994501234567', Phone::normalize('050 123 45 67'));
        $this->assertSame('+994501234567', Phone::normalize('+994 (50) 123-45-67'));
        $this->assertSame('+994701234567', Phone::normalize('994701234567'));
        $this->assertNull(Phone::normalize('12345'));
        $this->assertNull(Phone::normalize('+994 40 123 45 67')); // mövcud olmayan operator kodu
    }

    public function test_full_flow_verifies_phone_via_telegram(): void
    {
        $user = $this->register();
        $this->assertFalse($user->hasVerifiedPhone());

        // Təsdiqlənməmiş istifadəçi hesab səhifəsinə buraxılmır
        $this->get('/hesabim')->assertRedirect(route('verify.telegram'));

        // Təsdiq səhifəsində Telegram linki var
        $page = $this->get('/tesdiq')->assertOk()->assertSee('t.me/QepikTestBot?start=', false);
        $token = $user->fresh()->telegram_token;
        $this->assertNotEmpty($token);

        // Bot: /start <kod>, sonra istifadəçi öz kontaktını paylaşır
        $this->webhook($this->msg(['text' => "/start {$token}"]));
        $this->assertSame('777', $user->fresh()->telegram_chat_id);

        $this->webhook($this->msg(['contact' => ['phone_number' => '994501234567', 'user_id' => 777]]));

        $this->assertTrue($user->fresh()->hasVerifiedPhone());
        $this->assertNull($user->fresh()->telegram_token);
        $this->getJson('/tesdiq/status')->assertJson(['verified' => true, 'redirect' => route('account')])
            ->assertSessionHas('registered', true);
        // Testdə daxil olmuş istifadəçi yaddaşda qalır; real sorğuda hər dəfə bazadan oxunur
        $this->actingAs($user->fresh())->get('/hesabim')->assertOk()
            ->assertSee('+994 50 123 45 67')
            ->assertSee('registeredModal', false);
        // Pəncərə yalnız bir dəfə görünür
        $this->get('/hesabim')->assertOk()->assertDontSee('registeredModal', false);

        // Eyni linki yenidən açanda "etibarsız" yox, "artıq təsdiqlənib" deyilir
        $this->webhook($this->msg(['text' => "/start {$token}"]));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'sendMessage') && str_contains($r['text'], 'artıq təsdiqlənib'));

        // Bot istifadəçiyə nömrə düyməsini və uğur mesajını göndərib
        Http::assertSent(fn ($r) => str_contains($r->url(), 'sendMessage') && ($r['reply_markup']['keyboard'][0][0]['request_contact'] ?? false));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'sendMessage') && str_contains($r['text'], 'təsdiqləndi'));
    }

    public function test_wrong_number_is_rejected(): void
    {
        $user = $this->register('055 765 43 21');
        $this->get('/tesdiq');
        $this->webhook($this->msg(['text' => '/start ' . $user->fresh()->telegram_token], 888));
        $this->webhook($this->msg(['contact' => ['phone_number' => '+994501112233', 'user_id' => 888]], 888));

        $this->assertFalse($user->fresh()->hasVerifiedPhone());
    }

    public function test_someone_elses_contact_is_rejected(): void
    {
        $user = $this->register('070 111 22 33');
        $this->get('/tesdiq');
        $this->webhook($this->msg(['text' => '/start ' . $user->fresh()->telegram_token], 999));
        // Kontakt istifadəçinin özünə aid deyil (user_id fərqlidir)
        $this->webhook($this->msg(['contact' => ['phone_number' => '994701112233', 'user_id' => 12345]], 999));

        $this->assertFalse($user->fresh()->hasVerifiedPhone());
    }

    public function test_webhook_requires_secret(): void
    {
        $this->postJson('/api/telegram/webhook', ['update_id' => 1], ['X-Telegram-Bot-Api-Secret-Token' => 'wrong'])->assertForbidden();
    }

    public function test_duplicate_phone_and_login_by_phone(): void
    {
        $user = $this->register('051 222 33 44');
        $this->post('/cixis');

        $this->post('/qeydiyyat', [
            'name' => 'Başqası', 'phone' => '+994512223344', 'email' => 'other' . uniqid() . '@example.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertSessionHasErrors('phone');

        $this->post('/giris', ['login' => '0512223344', 'password' => 'secret123'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }
}
