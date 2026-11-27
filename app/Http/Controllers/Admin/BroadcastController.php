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
 * Admin: hamıya mesaj — bir mesaj seçilmiş istifadəçilərin hamısının söhbətinə gedir
 * (onlar onu adi mesaj kimi görür: profildə "Mesajlar", küncdə "Yeni mesajınız var!").
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
            ]),
            'history' => SupportBroadcast::with('admin:id,name')->latest('id')->paginate(20),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required_without:file', 'nullable', 'string', 'max:' . SupportMessage::MAX_LENGTH],
            'file' => SupportMessage::fileRules(),
            'audience' => ['required', Rule::in(array_keys(SupportBroadcast::AUDIENCES))],
            // link: saytın səhifəsi ("/hediyye-marketi") və ya tam ünvan (https://...)
            'link_url' => ['nullable', 'string', 'max:500', 'regex:~^(/(?!/)\S*|https?://\S+)$~'],
            'link_label' => ['nullable', 'string', 'max:60'],
        ], [
            'link_url.regex' => 'Link "/" ilə (saytın səhifəsi, məs. /hediyye-marketi) və ya https:// ilə başlamalıdır.',
            'link_url.max' => 'Link çox uzundur.',
            'link_label.max' => 'Düymənin yazısı ən çox 60 simvol ola bilər.',
            'body.required_without' => 'Mesajı yazın və ya fayl əlavə edin.',
            'body.max' => 'Mesaj ən çox ' . SupportMessage::MAX_LENGTH . ' simvol ola bilər.',
            'audience.*' => 'Kimə göndəriləcəyini seçin.',
        ] + SupportMessage::fileMessages());

        $admin = $request->user();
        $body = trim((string) ($data['body'] ?? ''));
        // fayl bir dəfə saxlanılır — bütün istifadəçilərin mesajı eyni fayla istinad edir
        $file = $request->hasFile('file') ? SupportMessage::storeUpload($request->file('file')) : [];
        $link = trim((string) ($data['link_url'] ?? '')) ?: null;
        $label = $link ? (trim((string) ($data['link_label'] ?? '')) ?: null) : null;

        $broadcast = DB::transaction(function () use ($admin, $body, $link, $label, $file, $data) {
            $broadcast = SupportBroadcast::create([
                'admin_id' => $admin->id, 'body' => $body, 'link_url' => $link, 'link_label' => $label, 'audience' => $data['audience'],
            ] + $file);
            $sent = 0;

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
            'admin_id' => $admin->id, 'broadcast_id' => $broadcast->id, 'audience' => $broadcast->audience, 'recipients' => $broadcast->recipients,
        ]);

        return redirect()->route('admin.support.broadcast')->with($broadcast->recipients ? 'success' : 'error', $broadcast->recipients
            ? "Mesaj {$broadcast->recipients} istifadəçinin söhbətinə göndərildi."
            : 'Seçilmiş qrupda istifadəçi yoxdur — mesaj heç kimə getmədi.');
    }
}
