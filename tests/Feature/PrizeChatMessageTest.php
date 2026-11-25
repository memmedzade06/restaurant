<?php

namespace Tests\Feature;

use App\Models\SupportMessage;
use App\Models\User;
use App\Models\WheelSpin;
use App\Support\Birthday;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Hədiyyə kodu yarananda istifadəçinin chat-inə avtomatik mesaj: nə qazandı, kod, müddət, minimal masa hesabı */
class PrizeChatMessageTest extends TestCase
{
    use DatabaseTransactions;

    private function user(array $attrs = []): User
    {
        $u = User::create([
            'name' => 'Hədiyyə Testi',
            'email' => 'pc' . uniqid() . '@example.com',
            'phone' => '+99450' . random_int(1000000, 9999999),
            'password' => 'secret123',
        ]);
        $u->forceFill($attrs + ['phone_verified_at' => now()])->save();

        return $u->fresh();
    }

    private function prize(User $u, string $game, string $label): WheelSpin
    {
        return WheelSpin::create([
            'user_id' => $u->id, 'game' => $game, 'prize_key' => $game . '_test', 'prize_label' => $label,
            'code' => WheelSpin::newCode(), 'spun_on' => now()->toDateString(), 'expires_at' => now()->addDay(),
        ]);
    }

    public function test_game_prize_sends_chat_message_with_code_expiry_and_min_bill(): void
    {
        config(['games.min_bill.stack' => 10]);
        $u = $this->user();
        $spin = $this->prize($u, 'stack', 'Çaynik Çay Hədiyyə');

        $msg = SupportMessage::where('user_id', $u->id)->sole();
        $this->assertTrue($msg->from_admin);
        $this->assertNull($msg->sender_id);
        $this->assertNull($msg->read_at);
        $this->assertStringContainsString('🎉 Təbriklər!', $msg->body);
        $this->assertStringContainsString('Stəkan qülləsində hədiyyə qazandınız: Çaynik Çay Hədiyyə', $msg->body);
        $this->assertStringContainsString('Kod: ' . $spin->code, $msg->body);
        $this->assertStringContainsString($spin->expires_at->timezone('Asia/Baku')->format('d.m.Y H:i') . ' tarixinədək etibarlıdır', $msg->body);
        $this->assertStringContainsString('minimal masa hesabı 10 AZN olmalıdır', $msg->body);

        // istifadəçi: chat-də "50 Qəpik"dən, profildə Mesajlar kartı və "1 yeni"
        $this->actingAs($u)->getJson(route('support.unread'))->assertJson(['unread' => 1]);
        $this->actingAs($u)->get(route('account'))->assertSee('id="mesajlar"', false)->assertSee('1 yeni');
        // chat açılanda oxunur
        $this->actingAs($u)->getJson(route('support.poll', ['after' => 0]))->assertJsonPath('messages.0.author', '50 Qəpik');
        $this->actingAs($u)->getJson(route('support.unread'))->assertJson(['unread' => 0]);
    }

    public function test_min_bill_differs_per_game_and_matches_rules_page(): void
    {
        config(['games.min_bill.wheel' => 35]);
        $u = $this->user();
        $this->prize($u, 'wheel', '20% endirim');
        $this->assertStringContainsString('Çarx oyununda hədiyyə qazandınız: 20% endirim', SupportMessage::where('user_id', $u->id)->latest('id')->value('body'));
        $this->assertStringContainsString('minimal masa hesabı 35 AZN', SupportMessage::where('user_id', $u->id)->latest('id')->value('body'));
        // oyunun qaydalarında da eyni rəqəm
        $this->get('/oyunlar/carx-oyunu')->assertOk()->assertSee('<b>35 AZN</b>', false);

        // market: "Hədiyyə marketindən aldınız", market.min_bill
        config(['market.min_bill' => 12]);
        $this->prize($u, 'market', 'Mürəbbə');
        $body = SupportMessage::where('user_id', $u->id)->latest('id')->value('body');
        $this->assertStringContainsString('Hədiyyə marketindən aldınız: Mürəbbə', $body);
        $this->assertStringContainsString('minimal masa hesabı 12 AZN', $body);
    }

    public function test_birthday_coupon_message_without_min_bill(): void
    {
        $this->travelTo(now('Asia/Baku')->setTime(12, 0));
        $u = $this->user(['birth_date' => now('Asia/Baku')->subYears(30)->toDateString(), 'birth_date_set_at' => now()->subDays(60)]);

        $this->assertNotNull(Birthday::claim($u));
        $body = SupportMessage::where('user_id', $u->id)->latest('id')->value('body');
        $this->assertStringContainsString('🎂 Ad gününüz mübarək!', $body);
        $this->assertStringContainsString('Ad günü hədiyyəniz: Masaya 50% endirim', $body);
        $this->assertStringNotContainsString('minimal masa hesabı', $body);
    }

    public function test_admin_inbox_is_not_flooded_and_admins_get_no_message(): void
    {
        $admin = $this->user(['is_admin' => true]);
        $u = $this->user();
        $this->prize($u, 'stack', 'Qəlyan Hədiyyə');

        // avtomatik mesaj adminin siyahısını doldurmur; söhbətdə "Avtomatik" kimi görünür
        $this->actingAs($admin)->get(route('admin.support'))->assertDontSee(route('admin.support.show', $u));
        $this->actingAs($admin)->get(route('admin.support.show', $u))->assertSee('Avtomatik')->assertSee('Qəlyan Hədiyyə');

        // istifadəçi cavab yazanda söhbət siyahıya düşür
        $this->actingAs($u)->postJson(route('support.store'), ['body' => 'Kodu nə vaxt istifadə edə bilərəm?'])->assertCreated();
        $this->actingAs($admin)->get(route('admin.support'))->assertSee(route('admin.support.show', $u));

        // admin hesabına kupon yazılsa (test və s.) — chat mesajı yoxdur
        $this->prize($admin, 'stack', 'Test');
        $this->assertSame(0, SupportMessage::where('user_id', $admin->id)->count());
    }
}
