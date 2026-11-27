<?php

namespace Tests\Feature;

use App\Models\SupportBroadcast;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            ->assertRedirect(route('admin.support.broadcast'))->assertSessionHas('success', "Mesaj: {$expected} istifadəçinin söhbətinə göndərildi.");

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
        $this->actingAs($admin)->get(route('admin.support.broadcast'))->assertSee('Bu axşam canlı musiqi!')->assertSee('Söhbət: <b>' . $expected . '</b>', false);

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

    public function test_broadcast_with_link_shows_a_button_in_the_chat(): void
    {
        $admin = $this->user(['is_admin' => true]);
        $guest = $this->user();
        $this->actingAs($admin)->get(route('admin.support.broadcast'))->assertSee('name="link_url"', false)->assertSee('name="link_label"', false);

        $this->actingAs($admin)->post(route('admin.support.broadcast.store'), [
            'body' => 'Yeni hədiyyələr gəldi!', 'audience' => 'all', 'link_url' => '/hediyye-marketi', 'link_label' => 'Hədiyyə marketinə keç',
        ])->assertSessionHas('success');

        $b = SupportBroadcast::latest('id')->first();
        $this->assertSame('/hediyye-marketi', $b->link_url);
        $msg = SupportMessage::where('broadcast_id', $b->id)->where('user_id', $guest->id)->sole();
        $this->assertSame(['url' => '/hediyye-marketi', 'label' => 'Hədiyyə marketinə keç', 'external' => false], $msg->toChat(false)['link']);

        // söhbətdə düymə (sayt daxilində — eyni pəncərədə açılır); poll cavabında da link var
        $this->actingAs($guest)->get(route('support'))
            ->assertSee('<a href="/hediyye-marketi" class="chat-link-btn" >Hədiyyə marketinə keç', false);
        $this->actingAs($guest)->getJson(route('support.poll', ['after' => 0]))
            ->assertJsonFragment(['link' => ['url' => '/hediyye-marketi', 'label' => 'Hədiyyə marketinə keç', 'external' => false]]);
        // adminin tarixçəsində də görünür
        $this->actingAs($admin)->get(route('admin.support.broadcast'))->assertSee('Hədiyyə marketinə keç → ', false);

        // yazı boşdursa "Keçid et"; kənar link yeni pəncərədə açılır
        $this->actingAs($admin)->post(route('admin.support.broadcast.store'), ['body' => 'Instagram', 'audience' => 'all', 'link_url' => 'https://instagram.com/x'])
            ->assertSessionHas('success');
        $link = SupportMessage::where('user_id', $guest->id)->latest('id')->first()->toChat(false)['link'];
        $this->assertSame(['url' => 'https://instagram.com/x', 'label' => 'Keçid et', 'external' => true], $link);
    }

    public function test_link_validation_and_plain_urls_in_text_are_clickable_but_safe(): void
    {
        $admin = $this->user(['is_admin' => true]);
        foreach (['javascript:alert(1)', '//evil.com', 'hediyye-marketi', 'ftp://x.az'] as $bad) {
            $this->actingAs($admin)->post(route('admin.support.broadcast.store'), ['body' => 'Salam', 'audience' => 'all', 'link_url' => $bad])
                ->assertSessionHasErrors('link_url');
        }
        $this->assertSame(0, SupportBroadcast::where('admin_id', $admin->id)->count());

        $html = (string) SupportMessage::linkify("Baxın: https://50qepik.az/oyunlar. <b>qalın</b>");
        $this->assertSame('Baxın: <a href="https://50qepik.az/oyunlar" target="_blank" rel="noopener nofollow">https://50qepik.az/oyunlar</a>. &lt;b&gt;qalın&lt;/b&gt;', $html);
    }

    public function test_admin_can_attach_a_file_to_a_broadcast(): void
    {
        Storage::fake('local');
        $admin = $this->user(['is_admin' => true]);
        $guest = $this->user();
        $other = $this->user();
        $this->actingAs($admin)->get(route('admin.support.broadcast'))->assertSee('enctype="multipart/form-data"', false)->assertSee('name="file"', false);

        // yalnız fayl da olar (mətnsiz)
        $this->actingAs($admin)->post(route('admin.support.broadcast.store'), [
            'body' => '', 'audience' => 'all', 'file' => UploadedFile::fake()->create('Menyu.pdf', 300, 'application/pdf'),
        ])->assertSessionHas('success');

        $b = SupportBroadcast::latest('id')->first();
        $this->assertSame('Menyu.pdf', $b->attachment_name);
        Storage::disk('local')->assertExists($b->attachment_path);
        // fayl bir dəfə saxlanılır, bütün mesajlar ona istinad edir
        $this->assertSame(1, count(Storage::disk('local')->allFiles('support')));
        $this->assertSame($b->recipients, SupportMessage::where('broadcast_id', $b->id)->where('attachment_path', $b->attachment_path)->count());

        $msg = SupportMessage::where('broadcast_id', $b->id)->where('user_id', $guest->id)->sole();
        $file = $msg->toChat(false)['file'];
        $this->assertSame(['url' => route('support.file', $msg), 'name' => 'Menyu.pdf', 'size' => '300 KB', 'image' => false, 'video' => false], $file);
        $this->actingAs($guest)->get(route('support'))->assertSee('class="chat-file" download="Menyu.pdf"', false);
        $this->actingAs($admin)->get(route('admin.support.broadcast'))->assertSee('Menyu.pdf (300 KB)');

        // fayl yalnız mesajın sahibinə və adminə açılır
        $this->actingAs($guest)->get(route('support.file', $msg))->assertOk()->assertDownload('Menyu.pdf');
        $this->actingAs($admin)->get(route('support.file', $msg))->assertOk();
        $this->actingAs($other)->get(route('support.file', $msg))->assertNotFound();
        auth()->logout();
        $this->get(route('support.file', $msg))->assertRedirect(route('login'));
    }

    public function test_admin_reply_with_image_and_file_rules(): void
    {
        Storage::fake('local');
        $admin = $this->user(['is_admin' => true]);
        $guest = $this->user();
        $this->actingAs($admin)->get(route('admin.support.show', $guest))->assertSee('class="chat-attach"', false);

        $res = $this->actingAs($admin)->post(route('admin.support.reply', $guest), [
            'body' => 'Bu axşamın menyusu', 'file' => UploadedFile::fake()->image('menyu.jpg', 600, 400),
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->assertTrue($res->json('message.file.image'));
        $msg = SupportMessage::where('user_id', $guest->id)->latest('id')->first();
        $this->assertSame('menyu.jpg', $msg->attachment_name);

        // şəkil brauzerdə açılır (yüklənmir); istifadəçinin söhbətində görünür
        $this->actingAs($guest)->get(route('support.file', $msg))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->actingAs($guest)->get(route('support'))->assertSee('class="chat-file-img"', false)->assertSee('Bu axşamın menyusu')
            ->assertDontSee('class="chat-attach"', false); // istifadəçinin öz söhbətində fayl düyməsi yoxdur

        // qadağan olunan növ və çox böyük fayl
        $this->actingAs($admin)->post(route('admin.support.reply', $guest), ['file' => UploadedFile::fake()->create('x.exe', 10)], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->actingAs($admin)->post(route('admin.support.reply', $guest), ['file' => UploadedFile::fake()->create('big.pdf', 20000, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        // nə mətn, nə fayl
        $this->actingAs($admin)->post(route('admin.support.reply', $guest), ['body' => ''], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('body');
        // istifadəçi fayl göndərə bilmir (sahə nəzərə alınmır)
        $this->actingAs($guest)->post(route('support.store'), ['body' => 'salam', 'file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertCreated();
        $this->assertNull(SupportMessage::where('user_id', $guest->id)->latest('id')->first()->attachment_path);
    }

    public function test_admin_can_send_a_video_that_plays_in_the_chat(): void
    {
        Storage::fake('local');
        $admin = $this->user(['is_admin' => true]);
        $guest = $this->user();

        // 30 MB video — olar (video üçün hədd 50 MB)
        $res = $this->actingAs($admin)->post(route('admin.support.reply', $guest), [
            'body' => 'Yeni menyumuz', 'file' => UploadedFile::fake()->create('menyu.mp4', 30 * 1024, 'video/mp4'),
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->assertTrue($res->json('message.file.video'));
        $this->assertFalse($res->json('message.file.image'));
        $msg = SupportMessage::where('user_id', $guest->id)->latest('id')->first();

        // söhbətdə oynadılan video və böyük ekran düyməsi
        $this->actingAs($guest)->get(route('support'))
            ->assertSee('<video src="' . route('support.file', $msg) . '" controls playsinline', false)
            ->assertSee('class="chat-video-full"', false);
        // fayl hissə-hissə verilir (irəli-geri çəkmək olur), yüklənmir (saxta faylın içi boşdur — 1000 bayt yazırıq)
        Storage::disk('local')->put($msg->attachment_path, str_repeat('v', 1000));
        $this->actingAs($guest)->get(route('support.file', $msg), ['Range' => 'bytes=0-99'])
            ->assertStatus(206)->assertHeader('Content-Range', 'bytes 0-99/1000')->assertHeader('Content-Type', 'video/mp4');
        $this->actingAs($this->user())->get(route('support.file', $msg))->assertNotFound(); // başqası açmır

        // video 50 MB-dan böyük olmaz; adi fayl isə 10 MB-dan böyük olmaz
        $this->actingAs($admin)->post(route('admin.support.reply', $guest), ['file' => UploadedFile::fake()->create('big.mp4', 60 * 1024, 'video/mp4')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->actingAs($admin)->post(route('admin.support.reply', $guest), ['file' => UploadedFile::fake()->create('big.pdf', 20 * 1024, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('file');

        // hamıya mesajda da video olar
        $this->actingAs($admin)->get(route('admin.support.broadcast'))->assertSee('video (mp4, mov, webm');
        $this->actingAs($admin)->post(route('admin.support.broadcast.store'), [
            'body' => 'Video', 'audience' => 'all', 'file' => UploadedFile::fake()->create('reklam.mp4', 1024, 'video/mp4'),
        ])->assertSessionHas('success');
        $this->assertTrue(SupportMessage::where('user_id', $guest->id)->latest('id')->first()->isVideoAttachment());
    }

    public function test_broadcast_can_go_to_telegram_and_email_instead_of_chat(): void
    {
        config(['services.telegram.bot_token' => 'test-token']);
        $this->app->forgetInstance(\App\Services\TelegramBot::class);
        \Illuminate\Support\Facades\Http::fake(['api.telegram.org/*' => \Illuminate\Support\Facades\Http::response(['ok' => true, 'result' => []])]);
        \Illuminate\Support\Facades\Mail::fake();

        $admin = $this->user(['is_admin' => true]);
        $tg = $this->user(['telegram_chat_id' => '777001']);
        $mail = $this->user(['email_verified_at' => now()]);
        $plain = $this->user(); // nə Telegram, nə təsdiqlənmiş e-poçt

        $this->actingAs($admin)->get(route('admin.support.broadcast'))->assertOk()
            ->assertSee('name="channels[]"', false)->assertSee('Telegram')->assertSee('E-poçt')->assertSee('Sayt söhbəti');

        $tgTotal = \App\Models\SupportBroadcast::channelQuery('all', 'telegram')->count();
        $mailTotal = \App\Models\SupportBroadcast::channelQuery('all', 'email')->count();

        $this->actingAs($admin)->post(route('admin.support.broadcast.store'), [
            'body' => "Bu axşam <canlı> musiqi!", 'audience' => 'all', 'channels' => ['telegram', 'email'],
            'link_url' => '/hesabim', 'link_label' => 'Hesabıma keç',
        ])->assertSessionHas('success', fn ($m) => str_contains($m, "Telegram-a {$tgTotal} nəfərə") && str_contains($m, "e-poçtla {$mailTotal} nəfərə"));

        $b = SupportBroadcast::latest('id')->first();
        $this->assertSame('telegram,email', $b->channels);
        // sayt söhbəti seçilməyib — mesaj yazılmır
        $this->assertSame(0, SupportMessage::where('broadcast_id', $b->id)->count());
        $this->assertSame(0, (int) $b->recipients);
        // arxa plandakı göndəriş: Telegram-a mətn (HTML ekranlanıb) və link, e-poçt məktubu
        $this->assertSame($tgTotal, (int) $b->fresh()->telegram_sent);
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => str_contains($r->url(), '/sendMessage')
            && $r['chat_id'] === '777001' && str_contains($r['text'], 'Bu axşam &lt;canlı&gt; musiqi!') && str_contains($r['text'], '/hesabim'));
        $this->assertSame($mailTotal, (int) $b->fresh()->email_sent);
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\BroadcastMail::class, fn ($m) => $m->hasTo($mail->email));
        \Illuminate\Support\Facades\Mail::assertNotSent(\App\Mail\BroadcastMail::class, fn ($m) => $m->hasTo($plain->email));

        // tarixçədə kanalların nəticəsi
        $this->actingAs($admin)->get(route('admin.support.broadcast'))
            ->assertSee('Telegram: <b>' . $tgTotal . '</b> / ' . $tgTotal, false)->assertSee('E-poçt: <b>' . $mailTotal . '</b> / ' . $mailTotal, false);
    }

    public function test_channel_rules(): void
    {
        $admin = $this->user(['is_admin' => true]);
        // heç bir kanal və ya naməlum kanal — xəta
        $this->actingAs($admin)->post(route('admin.support.broadcast.store'), ['body' => 'x', 'audience' => 'all', 'channels' => []])
            ->assertSessionHasErrors('channels');
        $this->actingAs($admin)->post(route('admin.support.broadcast.store'), ['body' => 'x', 'audience' => 'all', 'channels' => ['sms']])
            ->assertSessionHasErrors('channels.0');
        // böyük fayl yalnız e-poçtla — olmaz (məktuba əlavə olunmur), sayt söhbəti ilə birlikdə olar
        Storage::fake('local');
        $big = fn () => UploadedFile::fake()->create('video.mp4', 20 * 1024, 'video/mp4');
        $this->actingAs($admin)->post(route('admin.support.broadcast.store'), ['body' => 'x', 'audience' => 'all', 'channels' => ['email'], 'file' => $big()])
            ->assertSessionHasErrors('channels');
        $this->assertSame(0, SupportBroadcast::where('admin_id', $admin->id)->count());
        \Illuminate\Support\Facades\Mail::fake();
        $this->actingAs($admin)->post(route('admin.support.broadcast.store'), ['body' => 'x', 'audience' => 'all', 'channels' => ['chat', 'email'], 'file' => $big()])
            ->assertSessionHas('success');
    }
}
