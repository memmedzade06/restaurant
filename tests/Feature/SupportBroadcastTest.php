<?php

namespace Tests\Feature;

use App\Models\SupportBroadcast;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Hamıya mesaj: admin bir mesajı seçilmiş istifadəçilərin hamısının söhbətinə göndərir */
class SupportBroadcastTest extends TestCase
{
    use DatabaseTransactions;

    private function user(array $attrs = [], bool $verified = true): User
    {
        $u = User::create([
            'name' => 'Yayım Testi',
            'email' => 'br' . uniqid() . '@example.com',
            'phone' => '+99450' . random_int(1000000, 9999999),
            'password' => 'secret123',
        ]);
        $u->forceFill($attrs + ['phone_verified_at' => $verified ? now() : null, 'last_seen_at' => now()])->save();

        return $u->fresh();
    }

    public function test_only_admin_can_broadcast(): void
    {
        $user = $this->user();
        $this->actingAs($user)->get(route('admin.support.broadcast'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.support.broadcast.store'), ['body' => 'x', 'audience' => 'all'])->assertForbidden();
    }

    public function test_broadcast_reaches_verified_guests_only_and_shows_like_a_normal_message(): void
    {
        $admin = $this->user(['is_admin' => true]);
        $guest = $this->user();
        $unverified = $this->user([], false);
        $waiter = $this->user(['type' => User::TYPE_WAITER]);
        $expected = SupportBroadcast::recipientsQuery('all')->count();

        $this->actingAs($admin)->get(route('admin.support.broadcast'))->assertOk()
            ->assertSee('Bütün təsdiqlənmiş qonaqlar')->assertSee($expected . ' nəfər');
        $this->actingAs($admin)->get(route('admin.support'))->assertSee(route('admin.support.broadcast'));

        $this->actingAs($admin)->post(route('admin.support.broadcast.store'), ['body' => 'Bu axşam canlı musiqi! 🎶', 'audience' => 'all'])
            ->assertRedirect(route('admin.support.broadcast'))->assertSessionHas('success', "Mesaj {$expected} istifadəçinin söhbətinə göndərildi.");

        $b = SupportBroadcast::latest('id')->first();
        $this->assertSame($expected, $b->recipients);
        $this->assertSame($expected, SupportMessage::where('broadcast_id', $b->id)->count());
        $this->assertSame(1, SupportMessage::where('broadcast_id', $b->id)->where('user_id', $guest->id)->count());
        $this->assertSame(0, SupportMessage::where('broadcast_id', $b->id)->whereIn('user_id', [$unverified->id, $waiter->id, $admin->id])->count());

        // istifadəçi: adi mesaj kimi — profildə "1 yeni", küncdə nişan, söhbətdə "50 Qəpik"
        $this->actingAs($guest)->get(route('account'))->assertSee('id="mesajlar"', false)->assertSee('1 yeni')->assertSee('Bu axşam canlı musiqi!');
        $this->actingAs($guest)->getJson(route('support.unread'))->assertJson(['unread' => 1]);
        $this->actingAs($guest)->getJson(route('support.poll', ['after' => 0]))
            ->assertJsonPath('messages.0.body', 'Bu axşam canlı musiqi! 🎶')->assertJsonPath('messages.0.author', '50 Qəpik');

        // admin Mesajlar siyahısı yalnız yayımla dolmur; tarixçədə var
        $this->actingAs($admin)->get(route('admin.support'))->assertDontSee(route('admin.support.show', $guest));
        $this->actingAs($admin)->get(route('admin.support.broadcast'))->assertSee('Bu axşam canlı musiqi!')->assertSee($expected . ' nəfər</b>', false);

        // istifadəçi cavab yazanda söhbət siyahıda görünür, yayım "Hamıya" qeydi ilə
        $this->actingAs($guest)->postJson(route('support.store'), ['body' => 'Saat neçədə başlayır?'])->assertCreated();
        $this->actingAs($admin)->get(route('admin.support'))->assertSee(route('admin.support.show', $guest));
        $this->actingAs($admin)->get(route('admin.support.show', $guest))->assertSee('Hamıya ·')->assertSee('Saat neçədə başlayır?');
    }

    public function test_active30_audience_skips_long_inactive_users(): void
    {
        $admin = $this->user(['is_admin' => true]);
        $active = $this->user(['last_seen_at' => now()->subDays(3)]);
        $old = $this->user(['last_seen_at' => now()->subDays(45)]);

        $this->actingAs($admin)->post(route('admin.support.broadcast.store'), ['body' => 'Sizi darıxmışıq!', 'audience' => 'active30'])
            ->assertSessionHas('success');
        $b = SupportBroadcast::latest('id')->first();
        $this->assertSame('active30', $b->audience);
        $this->assertSame(1, SupportMessage::where('broadcast_id', $b->id)->where('user_id', $active->id)->count());
        $this->assertSame(0, SupportMessage::where('broadcast_id', $b->id)->where('user_id', $old->id)->count());
    }

    public function test_validation(): void
    {
        $admin = $this->user(['is_admin' => true]);
        $this->actingAs($admin)->post(route('admin.support.broadcast.store'), ['body' => '  ', 'audience' => 'all'])->assertSessionHasErrors('body');
        $this->actingAs($admin)->post(route('admin.support.broadcast.store'), ['body' => 'Salam', 'audience' => 'hamı'])->assertSessionHasErrors('audience');
        $this->assertSame(0, SupportBroadcast::where('admin_id', $admin->id)->count());
    }
}
