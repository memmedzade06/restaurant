<?php

namespace Tests\Feature;

use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Adminlə yazışma: istifadəçi sual yazır, admin paneldə görüb cavab verir */
class SupportMessagesTest extends TestCase
{
    use DatabaseTransactions;

    private function user(bool $admin = false, bool $verified = true): User
    {
        $u = User::create([
            'name' => $admin ? 'Admin Testi' : 'Sual Testi',
            'email' => 'sm' . uniqid() . '@example.com',
            'phone' => '+99450' . random_int(1000000, 9999999),
            'password' => 'secret123',
        ]);
        $u->forceFill(['phone_verified_at' => $verified ? now() : null, 'is_admin' => $admin])->save();

        return $u;
    }

    public function test_guest_is_sent_to_login_and_sees_corner_invite(): void
    {
        $this->get(route('support'))->assertRedirect(route('login'));
        $this->postJson(route('support.store'), ['body' => 'Salam'])->assertUnauthorized();

        $this->get(route('home'))->assertOk()
            ->assertSee('id="chatWidget"', false)
            ->assertSee('Adminə yazmaq üçün hesabınıza daxil olun.');
    }

    public function test_user_writes_admin_reads_and_replies(): void
    {
        $user = $this->user();
        $admin = $this->user(true);

        // istifadəçi yazır (həm səhifədən, həm küncdəki pəncərədən eyni ünvan)
        $this->actingAs($user)->postJson(route('support.store'), ['body' => "Rezervasiya ləğv etmək olar?\nSabah üçün"])
            ->assertCreated()->assertJsonPath('message.mine', true)->assertJsonPath('message.body', "Rezervasiya ləğv etmək olar?\nSabah üçün");

        $this->assertSame(1, SupportMessage::unreadForAdmin()->where('user_id', $user->id)->count());

        // adminin siyahısında oxunmamış kimi görünür
        $this->actingAs($admin)->get(route('admin.support', ['filter' => 'unread']))->assertOk()
            ->assertSee($user->name)->assertSee('Rezervasiya ləğv etmək olar?')
            ->assertSee(route('admin.support.show', $user));

        // söhbəti açanda oxunmuş olur
        $this->actingAs($admin)->get(route('admin.support.show', $user))->assertOk()->assertSee('Sabah üçün');
        $this->assertSame(0, SupportMessage::unreadForAdmin()->where('user_id', $user->id)->count());

        // admin cavab yazır
        $this->actingAs($admin)->postJson(route('admin.support.reply', $user), ['body' => 'Bəli, zəng edin.'])
            ->assertCreated()->assertJsonPath('message.mine', true);

        // istifadəçidə nişan: 1 oxunmamış cavab
        $this->actingAs($user)->getJson(route('support.unread'))->assertOk()->assertJson(['unread' => 1]);
        $this->actingAs($user)->get(route('home'))->assertOk()->assertSeeInOrder(['class="chat-fab-badge"', '>1</span>'], false);

        // pəncərə açılanda cavab gəlir və oxunmuş olur
        $first = SupportMessage::where('user_id', $user->id)->min('id');
        $this->actingAs($user)->getJson(route('support.poll', ['after' => $first]))->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.body', 'Bəli, zəng edin.')
            ->assertJsonPath('messages.0.mine', false)
            ->assertJsonPath('messages.0.author', '50 Qəpik');
        $this->actingAs($user)->getJson(route('support.unread'))->assertJson(['unread' => 0]);
    }

    public function test_read_ticks_and_admin_online_status(): void
    {
        $user = $this->user();
        $admin = $this->user(true);

        // göndərilən mesaj: ✓ (oxunmayıb)
        $sent = $this->actingAs($user)->postJson(route('support.store'), ['body' => 'Salam'])
            ->assertCreated()->assertJsonPath('message.read', false)->json('message.id');
        $this->actingAs($user)->getJson(route('support.poll', ['after' => $sent]))->assertJson(['read_until' => 0]);
        $this->actingAs($user)->get(route('support'))->assertOk()->assertSee('fa-check"', false)->assertDontSee('fa-check-double');

        // admin söhbəti açdı — istifadəçidə ✓✓
        $this->actingAs($admin)->get(route('admin.support.show', $user))->assertOk();
        $this->actingAs($user)->getJson(route('support.poll', ['after' => $sent]))->assertJson(['read_until' => $sent]);
        $this->actingAs($user)->get(route('support'))->assertSee('fa-check-double', false);

        // admin cavab yazır: istifadəçi oxuyana qədər adminə ✓, oxuyandan sonra ✓✓
        $reply = $this->actingAs($admin)->postJson(route('admin.support.reply', $user), ['body' => 'Buyurun'])->json('message.id');
        $this->actingAs($admin)->getJson(route('admin.support.poll', [$user, 'after' => $reply]))->assertJson(['read_until' => 0]);
        $this->actingAs($user)->getJson(route('support.poll', ['after' => $sent]))->assertOk();
        $this->actingAs($admin)->getJson(route('admin.support.poll', [$user, 'after' => $reply]))->assertJson(['read_until' => $reply]);

        // admin saytdadırsa — "Admin onlayndır"
        \App\Models\User::where('is_admin', true)->update(['last_seen_at' => now()->subMinutes(10)]);
        $this->actingAs($user)->getJson(route('support.unread'))->assertJson(['admin_online' => false]);
        \App\Models\User::whereKey($admin->id)->update(['last_seen_at' => now()]); // "indi saytda" siqnalı
        $this->actingAs($user)->getJson(route('support.unread'))->assertJson(['admin_online' => true]);
        $this->actingAs($user)->getJson(route('support.poll', ['after' => 0]))->assertJson(['admin_online' => true]);
        $this->actingAs($user)->get(route('home'))->assertSee('chat-status online', false);
    }

    public function test_admin_starts_conversation_and_user_sees_it_in_profile_and_widget(): void
    {
        $user = $this->user();
        $admin = $this->user(true);

        // adminin giriş nöqtələri: istifadəçilər siyahısı və düzəliş səhifəsi
        $this->actingAs($admin)->get(route('admin.users'))->assertOk()->assertSee(route('admin.support.show', $user));
        $this->actingAs($admin)->get(route('admin.users.edit', $user))->assertOk()->assertSee('Mesaj yaz');
        $this->actingAs($admin)->get(route('admin.support'))->assertOk()->assertSee('Yeni mesaj');

        // yazışma olmadan profildə Mesajlar kartı yoxdur
        $this->actingAs($user)->get(route('account'))->assertOk()->assertDontSee('id="mesajlar"', false);

        // istifadəçi heç yazmayıb — admin ilk mesajı göndərir
        $this->actingAs($admin)->get(route('admin.support.show', $user))->assertOk()->assertSee('Bu istifadəçi ilə yazışma yoxdur.');
        $this->actingAs($admin)->postJson(route('admin.support.reply', $user), ['body' => 'Salam! Bu gün sizə 20% endirim var.'])->assertCreated();

        // profildə: Mesajlar kartı, "1 yeni" və mesajın mətni; küncdə: "Yeni mesajınız var!"
        $this->actingAs($user)->get(route('account'))->assertOk()
            ->assertSee('id="mesajlar"', false)->assertSee('1 yeni')->assertSee('Bu gün sizə 20% endirim var.')
            ->assertSeeInOrder(['id="xallar"', 'id="mesajlar"'], false);
        // küncdəki yazı: hər iki variant səhifədədir (JS dəyişir), görünəni "hidden" olmayandır
        $label = function (): string {
            $html = $this->get(route('home'))->getContent();
            $this->assertStringContainsString('Yeni mesajınız var!', $html);
            $newHidden = (bool) preg_match('/class="chat-label-new"\s+hidden/', $html);
            $defaultHidden = (bool) preg_match('/class="chat-label-default"\s+hidden/', $html);
            $this->assertNotSame($newHidden, $defaultHidden, 'yazılardan biri görünməlidir');

            return $newHidden ? 'default' : 'new';
        };
        $this->actingAs($user);
        $this->assertSame('new', $label());

        // söhbəti açanda oxunur — kart qalır, amma "yeni" yox; küncdə adi yazı
        $this->actingAs($user)->get(route('support'))->assertOk()->assertSee('Bu gün sizə 20% endirim var.');
        $this->actingAs($user)->get(route('account'))->assertSee('id="mesajlar"', false)->assertDontSee('1 yeni');
        $this->actingAs($user);
        $this->assertSame('default', $label());

        // admin hesabına söhbət açılmır
        $this->actingAs($admin)->get(route('admin.support.show', $admin))->assertNotFound();
        $this->actingAs($admin)->postJson(route('admin.support.reply', $admin), ['body' => 'x'])->assertNotFound();
    }

    public function test_users_only_see_their_own_conversation(): void
    {
        $a = $this->user();
        $b = $this->user();
        SupportMessage::create(['user_id' => $a->id, 'sender_id' => $a->id, 'from_admin' => false, 'body' => 'A-nın gizli sualı']);

        $this->actingAs($b)->getJson(route('support.poll', ['after' => 0]))->assertOk()->assertJsonCount(0, 'messages');
        $this->actingAs($b)->get(route('support'))->assertOk()->assertDontSee('A-nın gizli sualı');
    }

    public function test_admin_pages_are_admin_only_and_admin_has_no_user_chat(): void
    {
        $user = $this->user();
        $admin = $this->user(true);

        $this->actingAs($user)->get(route('admin.support'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.support.show', $user))->assertForbidden();
        $this->actingAs($user)->postJson(route('admin.support.reply', $user), ['body' => 'x'])->assertForbidden();

        $this->actingAs($admin)->get(route('support'))->assertRedirect(route('admin.support'));

        // adminin küncdəki düyməsi də hərəkətlidir və yeni mesajların sayını yazır
        SupportMessage::create(['user_id' => $user->id, 'sender_id' => $user->id, 'from_admin' => false, 'body' => 'Sual']);
        $this->actingAs($admin)->get(route('home'))->assertOk()
            ->assertSee('chat-fab chat-fab-pulse', false)
            ->assertSee('yeni mesaj');
        $this->actingAs($admin)->postJson(route('support.store'), ['body' => 'x'])->assertForbidden();
    }

    public function test_unverified_user_can_ask_too(): void
    {
        // təsdiq problemi olan istifadəçi də yaza bilməlidir
        $this->actingAs($this->user(false, false))->postJson(route('support.store'), ['body' => 'Kod gəlmir'])->assertCreated();
    }

    public function test_validation_and_escaping(): void
    {
        $user = $this->user();
        $admin = $this->user(true);

        $this->actingAs($user)->postJson(route('support.store'), ['body' => '   '])->assertUnprocessable();
        $this->actingAs($user)->postJson(route('support.store'), ['body' => str_repeat('a', SupportMessage::MAX_LENGTH + 1)])->assertUnprocessable();

        $this->actingAs($user)->postJson(route('support.store'), ['body' => '<script>alert(1)</script>'])->assertCreated();
        $this->actingAs($admin)->get(route('admin.support.show', $user))->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);

        // JS olmadan adi forma da işləyir
        $this->actingAs($user)->post(route('support.store'), ['body' => 'Formadan'])->assertRedirect(route('support'));
    }
}
