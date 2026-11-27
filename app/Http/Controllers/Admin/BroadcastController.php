<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportBroadcast;
use App\Models\SupportMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin: hamıya mesaj — bir mesaj seçilmiş istifadəçilərə gedir. Hara (bir və ya bir neçəsi):
 * sayt söhbəti (onlar onu adi mesaj kimi görür: profildə "Mesajlar", küncdə "Yeni mesajınız var!"),
 * Telegram (Telegram ilə təsdiqlənənlərə bot yazır), e-poçt. Telegram və e-poçt arxa planda göndərilir.
 */
class BroadcastController extends Controller
{
    /** Bir sorğuda neçə istifadəçiyə yazılır (çox istifadəçi olanda bazanı yükləməmək üçün) */
    private const CHUNK = 500;

    public function index(): View
    {
        return view('frontend.admin.broadcast', [
            'audiences' => collect(SupportBroadcast::AUDIENCES)->map(fn ($a, $key) => [
                'label' => $a[0], 'hint' => $a[1], 'count' => SupportBroadcast::recipientsQuery($key)->count(),
                // hər kanalda neçə nəfərə çatar (Telegram — bota yazmışlar, e-poçt — təsdiqlənmiş e-poçtu olanlar)
                'channels' => collect(array_keys(SupportBroadcast::CHANNELS))
                    ->mapWithKeys(fn ($c) => [$c => SupportBroadcast::channelQuery($key, $c)->count()])->all(),
            ]),
            'telegramReady' => app(\App\Services\TelegramBot::class)->configured(),
            'history' => SupportBroadcast::with('admin:id,name')->latest('id')->paginate(20),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->mergeIfMissing(['channels' => ['chat']]); // kanal göstərilməyibsə — əvvəlki kimi yalnız sayt söhbəti
        $data = $request->validate([
            'body' => ['required_without:file', 'nullable', 'string', 'max:' . SupportMessage::MAX_LENGTH],
            'file' => SupportMessage::fileRules(),
            'audience' => ['required', Rule::in(array_keys(SupportBroadcast::AUDIENCES))],
            // link: saytın səhifəsi ("/hediyye-marketi") və ya tam ünvan (https://...)
            'link_url' => ['nullable', 'string', 'max:500', 'regex:~^(/(?!/)\S*|https?://\S+)$~'],
            'link_label' => ['nullable', 'string', 'max:60'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => [Rule::in(array_keys(SupportBroadcast::CHANNELS))],
        ], [
            'channels.*' => 'Hara göndəriləcəyini seçin: sayt söhbəti, Telegram və ya e-poçt.',
            'link_url.regex' => 'Link "/" ilə (saytın səhifəsi, məs. /hediyye-marketi) və ya https:// ilə başlamalıdır.',
            'link_url.max' => 'Link çox uzundur.',
            'link_label.max' => 'Düymənin yazısı ən çox 60 simvol ola bilər.',
            'body.required_without' => 'Mesajı yazın və ya fayl əlavə edin.',
            'body.max' => 'Mesaj ən çox ' . SupportMessage::MAX_LENGTH . ' simvol ola bilər.',
            'audience.*' => 'Kimə göndəriləcəyini seçin.',
        ] + SupportMessage::fileMessages());

        $channels = array_values(array_intersect(array_keys(SupportBroadcast::CHANNELS), $data['channels']));
        // böyük fayl məktuba əlavə olunmur — e-poçtda "saytda baxın" deyilir, ona görə sayt söhbəti də seçilməlidir
        if (in_array('email', $channels, true) && ! in_array('chat', $channels, true)
            && $request->hasFile('file') && $request->file('file')->getSize() > \App\Mail\BroadcastMail::ATTACH_MAX_BYTES) {
            return back()->withInput()->withErrors(['channels' => 'Fayl ' . (\App\Mail\BroadcastMail::ATTACH_MAX_BYTES / 1048576)
                . ' MB-dan böyükdür — məktuba əlavə olunmur. E-poçtla göndərmək üçün "Sayt söhbəti"ni də seçin (məktubda saytdakı mesaja link olacaq).']);
        }

        $admin = $request->user();
        $body = trim((string) ($data['body'] ?? ''));
        // fayl bir dəfə saxlanılır — bütün istifadəçilərin mesajı eyni fayla istinad edir
        $file = $request->hasFile('file') ? SupportMessage::storeUpload($request->file('file')) : [];
        $link = trim((string) ($data['link_url'] ?? '')) ?: null;
        $label = $link ? (trim((string) ($data['link_label'] ?? '')) ?: null) : null;

        $broadcast = DB::transaction(function () use ($admin, $body, $link, $label, $file, $data, $channels) {
            $broadcast = SupportBroadcast::create([
                'admin_id' => $admin->id, 'body' => $body, 'link_url' => $link, 'link_label' => $label, 'audience' => $data['audience'],
                'channels' => implode(',', $channels),
                'telegram_total' => in_array('telegram', $channels, true) ? SupportBroadcast::channelQuery($data['audience'], 'telegram')->count() : null,
                'email_total' => in_array('email', $channels, true) ? SupportBroadcast::channelQuery($data['audience'], 'email')->count() : null,
            ] + $file);
            $sent = 0;

            if (! in_array('chat', $channels, true)) {
                return $broadcast; // sayt söhbətinə yazılmır
            }

            SupportBroadcast::recipientsQuery($data['audience'])->select('id')
                ->chunkById(self::CHUNK, function ($users) use ($broadcast, $admin, $body, $link, $label, $file, &$sent) {
                    $now = now();
                    SupportMessage::insert($users->map(fn ($u) => [
                        'user_id' => $u->id,
                        'sender_id' => $admin->id,
                        'from_admin' => true,
                        'broadcast_id' => $broadcast->id,
                        'body' => $body,
                        'link_url' => $link,
                        'link_label' => $label,
                        'attachment_path' => $file['attachment_path'] ?? null,
                        'attachment_name' => $file['attachment_name'] ?? null,
                        'attachment_mime' => $file['attachment_mime'] ?? null,
                        'attachment_size' => $file['attachment_size'] ?? null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all());
                    $sent += $users->count();
                });

            $broadcast->update(['recipients' => $sent]);

            return $broadcast;
        });

        logger()->info('Admin hamıya mesaj göndərdi', [
            'admin_id' => $admin->id, 'broadcast_id' => $broadcast->id, 'audience' => $broadcast->audience, 'channels' => $broadcast->channels,
            'recipients' => $broadcast->recipients, 'telegram_total' => $broadcast->telegram_total, 'email_total' => $broadcast->email_total,
        ]);

        // Telegram və e-poçt: cavab brauzerə gedəndən sonra arxa planda (səhifə gözləmir)
        if ($broadcast->telegram_total || $broadcast->email_total) {
            $id = $broadcast->id;
            dispatch(function () use ($id) {
                if ($b = SupportBroadcast::find($id)) {
                    app(\App\Services\BroadcastSender::class)->run($b);
                }
            })->afterResponse();
        }

        $parts = [];
        if (in_array('chat', $channels, true)) {
            $parts[] = "{$broadcast->recipients} istifadəçinin söhbətinə göndərildi";
        }
        if (in_array('telegram', $channels, true)) {
            $parts[] = "Telegram-a {$broadcast->telegram_total} nəfərə göndərilir";
        }
        if (in_array('email', $channels, true)) {
            $parts[] = "e-poçtla {$broadcast->email_total} nəfərə göndərilir";
        }
        $any = $broadcast->recipients || $broadcast->telegram_total || $broadcast->email_total;

        return redirect()->route('admin.support.broadcast')->with($any ? 'success' : 'error', $any
            ? 'Mesaj: ' . implode('; ', $parts) . '.' . ($broadcast->telegram_total || $broadcast->email_total ? ' Nəticə aşağıdakı tarixçədə yenilənəcək.' : '')
            : 'Seçilmiş qrupda və kanallarda alıcı yoxdur — mesaj heç kimə getmədi.');
    }
}
