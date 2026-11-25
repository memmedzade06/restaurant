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
            'body' => ['required', 'string', 'max:' . SupportMessage::MAX_LENGTH],
            'audience' => ['required', Rule::in(array_keys(SupportBroadcast::AUDIENCES))],
        ], [
            'body.required' => 'Mesajı yazın.',
            'body.max' => 'Mesaj ən çox ' . SupportMessage::MAX_LENGTH . ' simvol ola bilər.',
            'audience.*' => 'Kimə göndəriləcəyini seçin.',
        ]);

        $admin = $request->user();
        $body = trim($data['body']);

        $broadcast = DB::transaction(function () use ($admin, $body, $data) {
            $broadcast = SupportBroadcast::create(['admin_id' => $admin->id, 'body' => $body, 'audience' => $data['audience']]);
            $sent = 0;

            SupportBroadcast::recipientsQuery($data['audience'])->select('id')
                ->chunkById(self::CHUNK, function ($users) use ($broadcast, $admin, $body, &$sent) {
                    $now = now();
                    SupportMessage::insert($users->map(fn ($u) => [
                        'user_id' => $u->id,
                        'sender_id' => $admin->id,
                        'from_admin' => true,
                        'broadcast_id' => $broadcast->id,
                        'body' => $body,
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
