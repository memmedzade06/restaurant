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

    public function test_registration_needs_only_name_phone_and_one_password(): void
    {
        // formada e-poçt və "şifrənin təkrarı" yoxdur; şifrə sahəsinə göz düyməsi skripti qoşulur
        $this->get('/qeydiyyat')->assertOk()
            ->assertDontSee('name="email"', false)
            ->assertDontSee('password_confirmation', false)
            ->assertSee('js/password-toggle.js', false);

        $this->post('/qeydiyyat', ['name' => 'Sadə Qeydiyyat', 'phone' => '055 444 33 22', 'password' => 'secret123'])
            ->assertRedirect(route('verify.telegram'));
        $user = User::where('phone', '+994554443322')->firstOrFail();
        $this->assertNull($user->email);
        $this->assertFalse($user->hasVerifiedPhone()); // yalnız Telegram ilə təsdiqlənir

        // qısa şifrə qəbul olunmur
        $this->post('/cixis');
        $this->post('/qeydiyyat', ['name' => 'Qısa', 'phone' => '055 444 33 23', 'password' => '123'])
            ->assertSessionHasErrors('password');
    }

    public function test_success_message_has_link_to_site(): void
    {
        // canlı saytda (https) — mesajın altında "Sayta daxil ol" düyməsi
        \Illuminate\Support\Facades\URL::forceRootUrl('https://50qepik.test');
        $user = $this->register('050 222 11 00');
        $this->get('/tesdiq');
        $this->webhook($this->msg(['text' => '/start ' . $user->fresh()->telegram_token], 501));
        $this->webhook($this->msg(['contact' => ['phone_number' => '994502221100', 'user_id' => 501]], 501));
        $this->assertTrue($user->fresh()->hasVerifiedPhone());

        Http::assertSent(fn ($r) => str_contains($r->url(), 'sendMessage') && str_contains($r['text'] ?? '', 'təsdiqləndi')
            && ($r['reply_markup']['inline_keyboard'][0][0]['url'] ?? null) === 'https://50qepik.test/hesabim'
            && ($r['reply_markup']['inline_keyboard'][0][0]['text'] ?? null) === '🌐 Sayta daxil ol');
    }

    public function test_success_message_has_text_link_without_https(): void
    {
        // lokal mühitdə (http) Telegram düyməni qəbul etmir — link mətnin içində verilir
        \Illuminate\Support\Facades\URL::forceRootUrl('http://localhost');
        $user = $this->register('050 222 11 01');
        $this->get('/tesdiq');
        $this->webhook($this->msg(['text' => '/start ' . $user->fresh()->telegram_token], 502));
        $this->webhook($this->msg(['contact' => ['phone_number' => '994502221101', 'user_id' => 502]], 502));

        Http::assertSent(fn ($r) => str_contains($r->url(), 'sendMessage') && str_contains($r['text'] ?? '', 'təsdiqləndi')
            && str_contains($r['text'], 'Sayta daxil olun: http://localhost/hesabim')
            && ! isset($r['reply_markup']['inline_keyboard']));
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
