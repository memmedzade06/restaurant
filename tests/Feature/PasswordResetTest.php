<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Şifrəmi unutdum: nömrə → Telegram ilə təsdiq → yeni şifrə.
 * Telegram API saxtalaşdırılır (Http::fake).
 */
class PasswordResetTest extends TestCase
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

    private function user(?string $chatId = '777', bool $verified = true): User
    {
        $u = User::create([
            'name' => 'Bərpa Test',
            'email' => 'r' . uniqid() . '@example.com',
            'phone' => '+994551234567',
            'password' => 'old-secret1',
        ]);
        $u->forceFill(['phone_verified_at' => $verified ? now() : null, 'telegram_chat_id' => $chatId])->save();

        return $u;
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

    /** 1-ci addım: nömrəni yazır, Telegram səhifəsinə keçir və bərpa kodunu qaytarır */
    private function requestReset(string $phone = '055 123 45 67'): string
    {
        $this->post('/sifremi-unutdum', ['phone' => $phone])->assertRedirect(route('password.telegram'));
        $token = session('pw_reset_token');
        $this->get('/sifremi-unutdum/telegram')->assertOk()->assertSee("t.me/QepikTestBot?start=r_{$token}", false);

        return $token;
    }

    public function test_login_page_has_forgot_link(): void
    {
        $this->get('/giris')->assertOk()->assertSee('Şifrəmi unutdum')->assertSee(route('password.request'), false);
        $this->get('/sifremi-unutdum')->assertOk()->assertSee('Qeydiyyatdakı telefon nömrəniz');
    }

    public function test_full_flow_with_linked_telegram(): void
    {
        $user = $this->user('777');
        $token = $this->requestReset();

        // Təsdiqdən əvvəl yeni şifrə formu açılmır
        $this->getJson('/sifremi-unutdum/status')->assertJson(['confirmed' => false]);
        $this->get('/sifremi-unutdum/yeni')->assertRedirect(route('password.request'));
        $token = $this->requestReset(); // yuxarıdakı yönləndirmə kodu sildi — yenisini alırıq

        // Qeydiyyatda bağlanmış Telegram söhbəti — nömrə soruşulmadan təsdiqlənir
        $this->webhook($this->msg(['text' => "/start r_{$token}"]));
        $this->getJson('/sifremi-unutdum/status')->assertJson(['confirmed' => true, 'redirect' => route('password.reset')]);

        $this->get('/sifremi-unutdum/yeni')->assertOk()->assertSee('Yeni şifrə təyin edin');

        // Şifrələr eyni deyil
        $this->post('/sifremi-unutdum/yeni', ['password' => 'new-secret1', 'password_confirmation' => 'other'])
            ->assertSessionHasErrors('password');

        $this->post('/sifremi-unutdum/yeni', ['password' => 'new-secret1', 'password_confirmation' => 'new-secret1'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->assertTrue(Hash::check('new-secret1', $user->fresh()->password));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'sendMessage') && str_contains($r['text'], 'şifrəsi dəyişdirildi'));

        // Kod bir dəfəlikdir
        $this->get('/sifremi-unutdum/yeni')->assertRedirect(route('password.request'));

        // Köhnə şifrə ilə giriş olmur, yenisi ilə olur
        $this->post('/giris', ['login' => '055 123 45 67', 'password' => 'old-secret1'])->assertSessionHasErrors('login');
        $this->post('/giris', ['login' => '055 123 45 67', 'password' => 'new-secret1'])->assertRedirect(route('games.index'));
    }

    public function test_other_telegram_must_share_matching_phone(): void
    {
        $user = $this->user(null);
        $token = $this->requestReset();

        // Bağlanmamış söhbət: bot nömrə istəyir, hələ təsdiq yoxdur
        $this->webhook($this->msg(['text' => "/start r_{$token}"], 888));
        $this->getJson('/sifremi-unutdum/status')->assertJson(['confirmed' => false]);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'sendMessage') && ($r['reply_markup']['keyboard'][0][0]['request_contact'] ?? false));

        // Başqasının kontaktı qəbul olunmur
        $this->webhook($this->msg(['contact' => ['phone_number' => '994551234567', 'user_id' => 999]], 888));
        $this->getJson('/sifremi-unutdum/status')->assertJson(['confirmed' => false]);

        // Öz nömrəsi — təsdiqlənir və söhbət hesaba bağlanır
        $this->webhook($this->msg(['contact' => ['phone_number' => '+994 55 123 45 67', 'user_id' => 888]], 888));
        $this->getJson('/sifremi-unutdum/status')->assertJson(['confirmed' => true]);
        $this->assertSame('888', $user->fresh()->telegram_chat_id);
    }

    public function test_wrong_phone_in_telegram_is_rejected(): void
    {
        $this->user('777');
        $token = $this->requestReset();

        // Yad Telegram hesabı linki açır və öz (başqa) nömrəsini paylaşır
        $this->webhook($this->msg(['text' => "/start r_{$token}"], 555));
        $this->webhook($this->msg(['contact' => ['phone_number' => '994701112233', 'user_id' => 555]], 555));

        $this->getJson('/sifremi-unutdum/status')->assertJson(['confirmed' => false]);
        $this->get('/sifremi-unutdum/yeni')->assertRedirect(route('password.request'));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'sendMessage') && str_contains($r['text'], 'eyni deyil'));
    }

    public function test_confirmed_code_works_only_in_the_same_browser(): void
    {
        $this->user('777');
        $token = $this->requestReset();
        $this->webhook($this->msg(['text' => "/start r_{$token}"]));

        // Başqa brauzer (sessiyada kod yoxdur) yeni şifrə təyin edə bilmir
        $this->flushSession();
        $this->get('/sifremi-unutdum/yeni')->assertRedirect(route('password.request'));
        $this->post('/sifremi-unutdum/yeni', ['password' => 'hacked-123', 'password_confirmation' => 'hacked-123'])
            ->assertRedirect(route('password.request'));
    }

    public function test_unknown_or_unverified_phone_is_rejected(): void
    {
        $this->post('/sifremi-unutdum', ['phone' => '055 123 45 67'])->assertSessionHasErrors('phone');
        $this->post('/sifremi-unutdum', ['phone' => '12345'])->assertSessionHasErrors('phone');

        $this->user('777', verified: false);
        $this->post('/sifremi-unutdum', ['phone' => '055 123 45 67'])->assertSessionHasErrors('phone');
        $this->assertNull(session('pw_reset_token'));
    }

    public function test_expired_or_fake_link_in_bot(): void
    {
        $this->user('777');
        $this->webhook($this->msg(['text' => '/start r_nonexistenttoken']));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'sendMessage') && str_contains($r['text'], 'etibarsızdır'));

        $this->get('/sifremi-unutdum/telegram')->assertRedirect(route('password.request'));
    }

    public function test_logged_in_user_cannot_open_forgot_page(): void
    {
        Auth::login($this->user('777'));
        $this->get('/sifremi-unutdum')->assertRedirect();
    }
}
